#!/usr/bin/env bash
#
# Restaura los datos críticos de MySQL y Redis desde un respaldo (D19).
#
# La restauración se realiza en tres pasos:
#
#   - se verifica la integridad del respaldo comparando los resúmenes SHA-256
#     declarados en `manifest.sha256` con los de los ficheros, y el respaldo se
#     rechaza si alguno no coincide o falta;
#   - se repone el volcado SQL con el cliente `mysql`;
#   - se restituye el artefacto de la aplicación (`php artisan
#     restore:critical-data`), que repone las filas de las tablas críticas y
#     las claves de Redis con `RESTORE` tras verificar sus resúmenes.
#
# El fichero `redis/dump.rdb` es la entrada de la recuperación en frío: se
# sustituye con él el volumen de datos de Redis y se inicia el servicio. La
# recuperación en caliente, sin detener Redis, es la que repone las claves
# desde el artefacto.
#
# Ninguna credencial se fija en el script: los parámetros de conexión proceden
# del entorno o del fichero de opciones que indica `MYSQL_DEFAULTS_FILE`, que
# el despliegue entrega desde el gestor de secretos (D16).

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

# Directorio del respaldo que se restaura.
backup_dir="${1:-${BACKUP_DIR:-}}"

# Fichero de entorno del backend, del que se derivan los parámetros de
# conexión que el entorno no proporciona.
backend_env="${BACKEND_ENV_FILE:-$repo_root/backend/.env}"

# Parámetros de la base de datos MySQL.
mysql_host="${MYSQL_HOST:-}"
mysql_port="${MYSQL_PORT:-3306}"
mysql_database="${MYSQL_DATABASE:-}"
mysql_user="${MYSQL_USER:-}"

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
    command -v "$1" >/dev/null 2>&1 || fail "Falta la herramienta «$1»: es necesaria para la restauración."
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
        fail "Falta la herramienta «sha256sum» o «shasum»: es necesaria para la restauración."
    fi
}

[ -n "$backup_dir" ] || fail "Indique el directorio del respaldo que se restaura."

if [ ! -d "$backup_dir" ]; then
    fail "El directorio de respaldo «$backup_dir» no existe."
fi

backup_dir="$(cd "$backup_dir" && pwd)"

require_command mysql

# Verificación de la integridad del respaldo antes de tocar ningún destino.
manifest="$backup_dir/manifest.sha256"

if [ -f "$manifest" ]; then
    missing=()
    mismatched=()

    while IFS= read -r line; do
        [ -n "$line" ] || continue

        expected="${line%% *}"
        relative="${line#*  }"
        file="$backup_dir/$relative"

        if [ ! -f "$file" ]; then
            missing+=("$relative")
            continue
        fi

        if [ "$(checksum_file "$file")" != "$expected" ]; then
            mismatched+=("$relative")
        fi
    done < "$manifest"

    if [ "${#missing[@]}" -gt 0 ] || [ "${#mismatched[@]}" -gt 0 ]; then
        for relative in "${missing[@]:-}"; do
            [ -n "$relative" ] && error "Falta el fichero «$relative» del respaldo."
        done
        for relative in "${mismatched[@]:-}"; do
            [ -n "$relative" ] && error "El fichero «$relative» no es íntegro: su resumen SHA-256 no coincide."
        done
        fail "El respaldo no es íntegro: no se ha restaurado ningún dato."
    fi

    log "La integridad del respaldo está verificada."
else
    error "El respaldo no contiene «manifest.sha256»: se restaura sin verificación de integridad."
fi

# Parámetros de conexión: los del entorno tienen prioridad sobre los del
# respaldo y sobre los del fichero de entorno del backend.
if [ -z "$mysql_database" ]; then
    mysql_database="$(read_env_value "$backup_dir/metadata.env" MYSQL_DATABASE || true)"
fi

if [ -z "$mysql_host" ]; then
    mysql_host="$(read_env_value "$backup_dir/metadata.env" MYSQL_HOST || true)"
fi

if [ -z "$mysql_port" ]; then
    mysql_port="$(read_env_value "$backup_dir/metadata.env" MYSQL_PORT || true)"
fi

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

[ -n "$mysql_port" ] || mysql_port="3306"
[ -n "$mysql_host" ] || mysql_host="127.0.0.1"

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

# Reposición del volcado SQL de MySQL.
dump="$backup_dir/mysql/$mysql_database.sql"

[ -f "$dump" ] || fail "El respaldo no contiene el volcado «mysql/$mysql_database.sql»."

mysql --defaults-extra-file="$defaults_file" "$mysql_database" < "$dump"

log "Volcado de MySQL repuesto."

# Reposición de las filas de las tablas críticas y de las claves de Redis.
artifact_root="$backup_dir/artifact"

if [ -d "$artifact_root" ] && [ -f "$repo_root/backend/vendor/autoload.php" ] && command -v php >/dev/null 2>&1; then
    artifact_id="$(find "$artifact_root" -mindepth 1 -maxdepth 1 -type d | sort | tail -n 1)"

    if [ -n "$artifact_id" ]; then
        (
            cd "$repo_root/backend"
            BACKUP_PATH="$artifact_root" php artisan restore:critical-data --artifact="$(basename "$artifact_id")"
        )
        log "Filas de las tablas críticas y claves de Redis repuestas."
    else
        error "El artefacto de la aplicación está vacío: no se han repuesto las claves de Redis."
    fi
else
    error "No se ha repuesto el artefacto de la aplicación: falta «backend/vendor/autoload.php» o el intérprete de PHP."
    error "Para la recuperación en frío, sustituya el volumen de datos de Redis con «redis/dump.rdb» antes de iniciar el servicio."
fi

if [ -f "$backup_dir/redis/dump.rdb" ]; then
    log "El fichero «redis/dump.rdb» queda disponible para la recuperación en frío de Redis."
fi

log "Restauración completada desde «$backup_dir»."
