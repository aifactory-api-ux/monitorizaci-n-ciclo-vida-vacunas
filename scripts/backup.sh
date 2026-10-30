#!/usr/bin/env bash
#
# Genera el respaldo de los datos críticos de MySQL y Redis (D19).
#
# El respaldo se compone de tres piezas complementarias:
#
#   - el volcado SQL de la base de datos, obtenido con `mysqldump`;
#   - la instantánea RDB del servidor de claves, obtenida con `redis-cli --rdb`,
#     que sirve de entrada a una recuperación en frío al sustituir el volumen
#     de datos de Redis;
#   - el artefacto de la aplicación (`php artisan backup:critical-data`), que
#     contiene el manifiesto con los resúmenes SHA-256, las filas de las tablas
#     críticas y la instantánea de las claves de Redis con su contenido y su
#     tiempo de vida, que la restauración repone con `RESTORE`.
#
# Ninguna credencial se fija en el script: los parámetros de conexión proceden
# del entorno o del fichero de opciones que indica `MYSQL_DEFAULTS_FILE`, que
# el despliegue entrega desde el gestor de secretos (D16). El fichero de
# entorno del backend se consulta únicamente para derivar los parámetros que
# el entorno no proporciona.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

# Directorio raíz donde se depositan los respaldos.
backup_root="${BACKUP_ROOT:-$repo_root/backups}"

# Fichero de entorno del backend, del que se derivan los parámetros de
# conexión que el entorno no proporciona.
backend_env="${BACKEND_ENV_FILE:-$repo_root/backend/.env}"

# Parámetros de la base de datos MySQL.
mysql_host="${MYSQL_HOST:-}"
mysql_port="${MYSQL_PORT:-3306}"
mysql_database="${MYSQL_DATABASE:-}"
mysql_user="${MYSQL_USER:-}"

# Parámetros del servidor de claves Redis.
redis_host="${REDIS_HOST:-127.0.0.1}"
redis_port="${REDIS_PORT:-6379}"
redis_database="${REDIS_DB:-0}"

# Periodo de conservación de los respaldos, en días.
retention_days="${BACKUP_RETENTION_DAYS:-30}"

log() {
    printf '%s\n' "$*"
}

error() {
    printf '%s\n' "$*" >&2
}

fail() {
    error "$*"
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Falta la herramienta «$1»: es necesaria para el respaldo."
}

# Valor de una variable declarada en un fichero de entorno, sin interpretarlo
# como código. Devuelve un código de salida distinto de cero si la variable no
# está declarada.
read_env_value() {
    local file="$1" name="$2" line value

    [ -f "$file" ] || return 1

    line="$(grep -E "^[[:space:]]*(export[[:space:]]+)?${name}=" "$file" | tail -n 1 || true)"

    [ -n "$line" ] || return 1

    value="${line#*=}"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"

    case "$value" in
        \"*\") value="${value#\"}"; value="${value%\"}" ;;
        \'*\') value="${value#\'}"; value="${value%\'}" ;;
    esac

    printf '%s' "$value"
}

# Resumen SHA-256 de un fichero.
checksum_file() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
    elif command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$1" | awk '{print $1}'
    else
        fail "Falta la herramienta «sha256sum» o «shasum»: es necesaria para el respaldo."
    fi
}

require_command mysqldump
require_command redis-cli

# Los parámetros que el entorno no declara se derivan del fichero de entorno
# del backend, de modo que el respaldo usa la misma conexión que la aplicación.
if [ -z "$mysql_host" ]; then
    mysql_host="$(read_env_value "$backend_env" DB_HOST || true)"
fi

if [ -z "$mysql_port" ]; then
    mysql_port="$(read_env_value "$backend_env" DB_PORT || true)"
fi

if [ -z "$mysql_database" ]; then
    mysql_database="$(read_env_value "$backend_env" DB_DATABASE || true)"
fi

if [ -z "$mysql_user" ]; then
    mysql_user="$(read_env_value "$backend_env" DB_USERNAME || true)"
fi

if [ -z "$redis_host" ]; then
    redis_host="$(read_env_value "$backend_env" REDIS_HOST || true)"
fi

if [ -z "$redis_port" ]; then
    redis_port="$(read_env_value "$backend_env" REDIS_PORT || true)"
fi

[ -n "$mysql_port" ] || mysql_port="3306"
[ -n "$redis_host" ] || redis_host="127.0.0.1"
[ -n "$redis_port" ] || redis_port="6379"

[ -n "$mysql_database" ] || fail "No se ha declarado la base de datos: use MYSQL_DATABASE o declare DB_DATABASE en el fichero de entorno del backend."
[ -n "$mysql_user" ] || fail "No se ha declarado el usuario de MySQL: use MYSQL_USER o MYSQL_DEFAULTS_FILE."

# Fichero de opciones del cliente de MySQL. Si el despliegue lo entrega, se
# usa tal cual; si no, se genera uno efímero con permisos restrictivos a partir
# de las variables del entorno. El fichero se elimina al terminar.
defaults_file=""
cleanup() {
    if [ -n "$defaults_file" ] && [ -f "$defaults_file" ]; then
        rm -f "$defaults_file"
    fi
}
trap cleanup EXIT

if [ -n "${MYSQL_DEFAULTS_FILE:-}" ]; then
    [ -r "$MYSQL_DEFAULTS_FILE" ] || fail "No se puede leer el fichero de opciones de MySQL «$MYSQL_DEFAULTS_FILE»."
    defaults_file="$MYSQL_DEFAULTS_FILE"
else
    defaults_file="$(mktemp)"
    chmod 600 "$defaults_file"
    {
        printf '[client]\n'
        printf 'host=%s\n' "$mysql_host"
        printf 'port=%s\n' "$mysql_port"
        printf 'user=%s\n' "$mysql_user"
        printf 'password=%s\n' "${MYSQL_PASSWORD:-}"
    } > "$defaults_file"
fi

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="$backup_root/$timestamp"

mkdir -p "$backup_dir/mysql" "$backup_dir/redis"

log "Respaldo de los datos críticos en «$backup_dir»."

# Volcado SQL de la base de datos.
mysqldump \
    --defaults-extra-file="$defaults_file" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    --set-gtid-purged=OFF \
    "$mysql_database" > "$backup_dir/mysql/$mysql_database.sql"

log "Volcado de MySQL generado."

# Instantánea RDB del servidor de claves.
redis_args=(-h "$redis_host" -p "$redis_port" -n "$redis_database")

if [ -n "${REDIS_PASSWORD:-}" ]; then
    # La contraseña se transmite por el entorno del proceso y no por la línea
    # de órdenes, para que no aparezca en la lista de procesos.
    export REDISCLI_AUTH="$REDIS_PASSWORD"
fi

redis-cli "${redis_args[@]}" --rdb "$backup_dir/redis/dump.rdb" >/dev/null

log "Instantánea RDB de Redis generada."

# Artefacto de la aplicación: manifiesto, filas de las tablas críticas e
# instantánea de las claves con su contenido y su tiempo de vida.
if [ -f "$repo_root/backend/vendor/autoload.php" ] && command -v php >/dev/null 2>&1; then
    (
        cd "$repo_root/backend"
        BACKUP_PATH="$backup_dir/artifact" php artisan backup:critical-data
    )
    log "Artefacto de la aplicación generado."
else
    error "No se ha generado el artefacto de la aplicación: falta «backend/vendor/autoload.php» o el intérprete de PHP."
    error "El volcado SQL y la instantánea RDB están disponibles, pero la restauración de las claves requiere el artefacto."
fi

# Metadatos del respaldo, sin credenciales.
{
    printf 'BACKUP_TIMESTAMP=%s\n' "$timestamp"
    printf 'MYSQL_HOST=%s\n' "$mysql_host"
    printf 'MYSQL_PORT=%s\n' "$mysql_port"
    printf 'MYSQL_DATABASE=%s\n' "$mysql_database"
    printf 'REDIS_HOST=%s\n' "$redis_host"
    printf 'REDIS_PORT=%s\n' "$redis_port"
    printf 'REDIS_DB=%s\n' "$redis_database"
} > "$backup_dir/metadata.env"

# Resúmenes de integridad de todos los ficheros del respaldo.
: > "$backup_dir/manifest.sha256"

while IFS= read -r file; do
    printf '%s  %s\n' "$(checksum_file "$file")" "${file#"$backup_dir"/}" >> "$backup_dir/manifest.sha256"
done < <(find "$backup_dir" -type f ! -name 'manifest.sha256' | sort)

log "Resúmenes de integridad calculados."

# Purga de los respaldos que superan el periodo de conservación.
if [ "$retention_days" -gt 0 ] 2>/dev/null; then
    while IFS= read -r expired; do
        log "Se purga el respaldo «$expired», que supera la conservación de $retention_days días."
        rm -rf "$expired"
    done < <(find "$backup_root" -mindepth 1 -maxdepth 1 -type d -name '[0-9]*T[0-9]*Z' -mtime "+$retention_days" | sort)
fi

log "Respaldo completado en «$backup_dir»."
