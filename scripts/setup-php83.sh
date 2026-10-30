#!/usr/bin/env bash
#
# Prepara el entorno de desarrollo con PHP 8.3 y Composer 2 sin depender de
# un daemon de Docker (tarea 1.1 del grupo 1 de la entrega inicial).
#
# El backend del sistema exige PHP 8.3 con las extensiones `pdo_sqlite`,
# `pdo_mysql`, `mbstring`, `openssl`, `curl`, `bcmath` y `zip`. La imagen
# `php:8.3-fpm-alpine` del despliegue no es utilizable en el entorno de
# verificación, porque no hay daemon de Docker disponible, de modo que el
# interprete se instala como binario independiente dentro del perfil del
# usuario.
#
# El script es idempotente: si el interprete y el gestor de dependencias ya
# están instalados, sólo los verifica. Al terminar ejecuta las tres
# comprobaciones de la tarea: `php -v`, `php -m` y `composer --version`.
#
# Variables de entorno admitidas:
#
#   PHP_VERSION        versión de PHP a instalar (por defecto 8.3.32)
#   PHP_INSTALL_DIR    directorio del interprete (por defecto `$HOME/opt/php83`)
#   COMPOSER_INSTALL_DIR  directorio de Composer (por defecto `$HOME/opt/composer`)
#   TOOLCHAIN_BIN_DIR  directorio de los envoltorios (por defecto `$HOME/bin`)
#   PHP_BASE_URL       origen de las compilaciones estáticas de PHP
#
# La compilación empleada es la variante `common` del proyecto static-php-cli,
# que incluye las extensiones exigidas por el backend (`pdo_sqlite`,
# `pdo_mysql`, `mbstring`, `openssl`, `curl`, `bcmath` y `zip`) en un único
# binario enlazado estáticamente, sin dependencias del sistema.

set -euo pipefail

php_version="${PHP_VERSION:-8.3.32}"
php_install_dir="${PHP_INSTALL_DIR:-$HOME/opt/php83}"
composer_install_dir="${COMPOSER_INSTALL_DIR:-$HOME/opt/composer}"
toolchain_bin_dir="${TOOLCHAIN_BIN_DIR:-$HOME/bin}"
php_base_url="${PHP_BASE_URL:-https://dl.static-php.dev/static-php-cli/common}"
composer_url="${COMPOSER_URL:-https://getcomposer.org/download/latest-stable/composer.phar}"

# Extensiones que el backend necesita para funcionar y para ejecutar la suite.
required_extensions=(pdo_sqlite pdo_mysql mbstring openssl curl bcmath zip)

# Detecta la arquitectura del host para descargar la compilación adecuada.
case "$(uname -m)" in
    x86_64 | amd64) php_arch="x86_64" ;;
    aarch64 | arm64) php_arch="aarch64" ;;
    *)
        echo "Arquitectura no soportada para la compilación estática de PHP: $(uname -m)" >&2
        exit 1
        ;;
esac

if [ "$(uname -s)" != "Linux" ]; then
    echo "El entorno de desarrollo requiere Linux: $(uname -s)" >&2
    exit 1
fi

for tool in curl tar; do
    if ! command -v "$tool" > /dev/null 2>&1; then
        echo "Falta la herramienta «$tool», necesaria para instalar PHP 8.3." >&2
        exit 1
    fi
done

mkdir -p "$php_install_dir" "$composer_install_dir" "$toolchain_bin_dir"

php_binary="$php_install_dir/php"
php_ini="$php_install_dir/php.ini"
php_archive="php-${php_version}-cli-linux-${php_arch}.tar.gz"

# Instala el interprete si todavía no está disponible.
if [ ! -x "$php_binary" ]; then
    echo "Descargando PHP ${php_version} (${php_arch})..."
    curl -fsSL -o "$php_install_dir/$php_archive" "$php_base_url/$php_archive"
    tar xzf "$php_install_dir/$php_archive" -C "$php_install_dir" php
    rm -f "$php_install_dir/$php_archive"
    chmod +x "$php_binary"
fi

# Configuración de la CLI: memoria suficiente para Composer y la suite de
# pruebas, errores visibles en la salida estándar y zona horaria estable.
if [ ! -f "$php_ini" ]; then
    cat > "$php_ini" << 'INI'
; Configuracion CLI de PHP 8.3 para el entorno de desarrollo del backend.
memory_limit = 512M
max_execution_time = 0
date.timezone = UTC
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /dev/stderr
error_reporting = E_ALL & ~E_DEPRECATED
default_charset = "UTF-8"
file_uploads = On
upload_max_filesize = 32M
post_max_size = 32M
serialize_precision = -1
zend.exception_ignore_args = Off
INI
fi

# Instala Composer 2 si todavía no está disponible.
composer_binary="$composer_install_dir/composer"
if [ ! -f "$composer_binary" ]; then
    echo "Descargando Composer 2..."
    curl -fsSL -o "$composer_binary" "$composer_url"
    chmod +x "$composer_binary"
fi

# Envoltorio del interprete: fija el fichero de configuración propio del
# entorno de desarrollo, de modo que la CLI no dependa de la configuración del
# sistema.
cat > "$toolchain_bin_dir/php" << WRAPPER
#!/bin/sh
# Envoltorio del interprete PHP 8.3 del entorno de desarrollo.
PHP_HOME="\${PHP_HOME:-$php_install_dir}"
exec "\$PHP_HOME/php" -c "\$PHP_HOME/php.ini" "\$@"
WRAPPER
chmod +x "$toolchain_bin_dir/php"

# Envoltorio de Composer 2: el phar invoca \`php\` a través de \`env\`, de modo que
# el directorio de envoltorios debe estar en el \`PATH\`.
cat > "$toolchain_bin_dir/composer" << WRAPPER
#!/bin/sh
# Envoltorio de Composer 2 para el entorno de desarrollo.
PATH="$toolchain_bin_dir:\$PATH"
export PATH
exec "$composer_binary" "\$@"
WRAPPER
chmod +x "$toolchain_bin_dir/composer"

cat > "$toolchain_bin_dir/composer.phar" << WRAPPER
#!/bin/sh
# Instalador de Composer 2 (phar) reutilizable en el entorno de desarrollo.
PATH="$toolchain_bin_dir:\$PATH"
export PATH
exec "$composer_binary" "\$@"
WRAPPER
chmod +x "$toolchain_bin_dir/composer.phar"

# Los enlaces de `~/.local/bin` cubren las instalaciones donde el perfil del
# usuario no incorpora `~/bin` en el `PATH`.
mkdir -p "$HOME/.local/bin"
ln -sf "$toolchain_bin_dir/php" "$HOME/.local/bin/php"
ln -sf "$toolchain_bin_dir/composer" "$HOME/.local/bin/composer"
ln -sf "$toolchain_bin_dir/composer.phar" "$HOME/.local/bin/composer.phar"

# El perfil del usuario incorpora los envoltorios al `PATH` para que las
# shells de inicio posteriores dispongan de `php` y `composer`.
profile_file="$HOME/.profile"
if [ -f "$profile_file" ] && ! grep -q "$toolchain_bin_dir" "$profile_file"; then
    {
        echo ""
        echo "# Toolchain de desarrollo (PHP 8.3 y Composer 2)."
        echo "export PATH=\"$toolchain_bin_dir:\$PATH\""
    } >> "$profile_file"
fi

# Verificación 1: versión del interprete.
export PATH="$toolchain_bin_dir:$PATH"

php -v

# Verificación 2: extensiones disponibles frente a las exigidas por el backend.
installed_extensions="$(php -m)"

for extension in "${required_extensions[@]}"; do
    if ! printf '%s\n' "$installed_extensions" | grep -qi "^${extension}$"; then
        echo "Falta la extensión «${extension}» requerida por el backend." >&2
        exit 1
    fi
done

php -m

# Verificación 3: versión del gestor de dependencias.
composer --version
