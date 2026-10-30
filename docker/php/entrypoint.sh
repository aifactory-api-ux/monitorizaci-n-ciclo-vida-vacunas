#!/usr/bin/env sh
#
# Entrypoint del contenedor del BFF. Valida las credenciales obligatorias,
# prepara la aplicación y deja el control en supervisor (php-fpm, cola y
# Reverb).
#
set -eu

log() {
    printf '[entrypoint] %s\n' "$*"
}

die() {
    printf '[entrypoint] ERROR: %s\n' "$*" >&2
    exit 1
}

require_env() {
    name="$1"
    eval "value=\${$name:-}"
    if [ -z "$value" ]; then
        die "la variable de entorno $name es obligatoria en el despliegue de contenedor"
    fi
}

wait_for_database() {
    attempts="${DB_WAIT_ATTEMPTS:-60}"
    attempt=1

    while [ "$attempt" -le "$attempts" ]; do
        if php artisan db:show --json >/dev/null 2>&1; then
            log "la base de datos está disponible"

            return 0
        fi

        log "esperando a la base de datos (intento $attempt de $attempts)"
        attempt=$((attempt + 1))
        sleep 2
    done

    die "la base de datos no está disponible tras $attempts intentos"
}

require_env APP_KEY
require_env DB_DATABASE
require_env DB_USERNAME
require_env DB_PASSWORD
require_env REVERB_APP_ID
require_env REVERB_APP_KEY
require_env REVERB_APP_SECRET

if [ "$#" -eq 0 ]; then
    set -- supervisord -c /etc/supervisor/supervisord.conf
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    log "no hay claves de Passport: se genera un par nuevo"
    php artisan passport:keys --force
fi

wait_for_database

php artisan config:clear
php artisan package:discover --ansi
php artisan migrate --force

if [ ! -e public/storage ]; then
    php artisan storage:link
fi

log "arrancando $*"
exec "$@"
