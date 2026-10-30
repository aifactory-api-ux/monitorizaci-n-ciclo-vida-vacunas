#!/usr/bin/env bash
#
# Analiza las vulnerabilidades de las dependencias del backend y del frontend.
#
# La etapa de seguridad del pipeline ejecuta este script antes de promover la
# entrega a `staging` y a `prod`: si el análisis detecta una vulnerabilidad, el
# script termina con un código distinto de cero y la promoción queda bloqueada
# (véase D20).
#
# El análisis se apoya en las herramientas de cada ecosistema:
#
#   - `composer audit --locked` revisa las dependencias PHP fijadas en el
#     fichero de bloqueo del backend;
#   - `npm audit --audit-level=high` revisa las dependencias Node del
#     frontend, incluida la caché de auditoría.
#
# El script falla en cerrando: si la herramienta de análisis no está disponible
# o el fichero de bloqueo no existe, la etapa se considera fallida y la
# promoción no se realiza.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

# Directorios analizados. Se pueden sobrescribir para verificar el
# comportamiento del script con un conjunto de dependencias vulnerable.
backend_dir="${BACKEND_DIR:-$repo_root/backend}"
frontend_dir="${FRONTEND_DIR:-$repo_root/frontend}"

# Herramientas de análisis.
composer_bin="${COMPOSER_BIN:-composer}"
npm_bin="${NPM_BIN:-npm}"

status=0

log() {
    printf '%s\n' "$*"
}

error() {
    printf '%s\n' "$*" >&2
}

# Analiza las dependencias PHP del backend.
analyze_php_dependencies() {
    if [ ! -d "$backend_dir" ]; then
        error "No se encuentra el directorio del backend «$backend_dir»."
        return 1
    fi

    if ! command -v "$composer_bin" >/dev/null 2>&1; then
        error "Falta la herramienta «$composer_bin»: no se puede analizar las dependencias PHP."
        return 1
    fi

    if [ ! -f "$backend_dir/composer.lock" ]; then
        error "No se encuentra el fichero «composer.lock» en «$backend_dir»."
        return 1
    fi

    log "Análisis de vulnerabilidades de las dependencias PHP en «$backend_dir»."

    if ! (cd "$backend_dir" && "$composer_bin" audit --locked --no-interaction --no-ansi); then
        error "Se han detectado vulnerabilidades en las dependencias PHP."
        return 1
    fi

    log "Las dependencias PHP no presentan vulnerabilidades conocidas."

    return 0
}

# Analiza las dependencias Node del frontend.
analyze_node_dependencies() {
    if [ ! -d "$frontend_dir" ]; then
        error "No se encuentra el directorio del frontend «$frontend_dir»."
        return 1
    fi

    if ! command -v "$npm_bin" >/dev/null 2>&1; then
        error "Falta la herramienta «$npm_bin»: no se puede analizar las dependencias Node."
        return 1
    fi

    if [ ! -f "$frontend_dir/package-lock.json" ]; then
        error "No se encuentra el fichero «package-lock.json» en «$frontend_dir»."
        return 1
    fi

    log "Análisis de vulnerabilidades de las dependencias Node en «$frontend_dir»."

    if ! (cd "$frontend_dir" && "$npm_bin" audit --audit-level=high --no-fund); then
        error "Se han detectado vulnerabilidades en las dependencias Node."
        return 1
    fi

    log "Las dependencias Node no presentan vulnerabilidades conocidas."

    return 0
}

if analyze_php_dependencies; then
    :
else
    status=1
fi

if analyze_node_dependencies; then
    :
else
    status=1
fi

if [ "$status" -eq 0 ]; then
    log "No se han detectado vulnerabilidades en las dependencias de PHP y Node."
fi

exit "$status"
