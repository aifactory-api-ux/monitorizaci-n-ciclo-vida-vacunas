#!/usr/bin/env python3
"""Verificación estática de los ficheros de contenedores del repositorio.

El script **no** invoca el demonio de Docker: ni `docker build` ni
`docker compose up`. Sólo analiza el `Dockerfile`, el `docker-compose.yml` y el
resto de la configuración del despliegue, y comprueba que son coherentes con el
estado real del repositorio.

Comprobaciones:

1. `docker-compose.yml` es YAML válido y declara los servicios `bff`,
   `frontend`, `mysql` y `redis`.
2. `mysql` y `redis` están declarados entre las dependencias de `bff`.
3. `bff` y `frontend` se construyen desde el `Dockerfile` de la raíz con los
   targets `bff` y `frontend` respectivamente.
4. Todo origen de `COPY` declarado en el `Dockerfile` existe en el
   repositorio; los que proceden de otro stage se validan contra el nombre
   del stage.
5. Ninguna regla de `.dockerignore` excluye el origen de un `COPY`, ni en su
   forma literal ni en su forma relativa al `WORKDIR` del stage.
6. Todo montaje de volumen con ruta relativa declarado en `docker-compose.yml`
   existe en el repositorio.
7. Las rutas del SPA declaradas en `frontend/nginx.conf` existen en el
   repositorio y su upstream es un servicio del despliegue con el puerto
   publicado.
8. `docker/mysql/my.cnf` y `docker/redis/redis.conf` declaran la persistencia
   del despliegue (InnoDB y AppendOnly).
9. `README.md` documenta el despliegue On-Premise o en nube privada de MSD con
   los cuatro servicios y su secuencia de arranque.
10. El `bff` publica un puerto HTTP accesible desde el host y nginx sirve la
    aplicación Laravel sobre php-fpm.
11. El canal WebSocket de Reverb es alcanzable desde el SPA a través del
    `frontend`.
12. El `bff` difunde los eventos por la conexión de Reverb y activa la
    orquestación de los mismos mediante Redis.

Uso: ``python3 scripts/verify-containers.py``. Devuelve 0 si todo es correcto
y 1 si hay alguna incidencia.
"""

from __future__ import annotations

import re
import shlex
import sys
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent
DOCKERFILE = ROOT / "Dockerfile"
DOCKERIGNORE = ROOT / ".dockerignore"
COMPOSE_FILE = ROOT / "docker-compose.yml"
NGINX_CONF = ROOT / "frontend" / "nginx.conf"
BFF_NGINX_CONF = ROOT / "docker" / "php" / "nginx.conf"
MYSQL_CNF = ROOT / "docker" / "mysql" / "my.cnf"
REDIS_CONF = ROOT / "docker" / "redis" / "redis.conf"
README = ROOT / "README.md"

REQUIRED_SERVICES = ("bff", "frontend", "mysql", "redis")
BFF_DEPENDENCIES = ("mysql", "redis")
EXPECTED_TARGETS = {"bff": "bff", "frontend": "frontend"}
EXPECTED_IMAGES = {"mysql": "mysql:8.0", "redis": "redis:7-alpine"}
DEPLOYMENT_TOPICS = ("On-Premise", "nube privada", "escalado horizontal", "clúster de Redis")
BFF_HTTP_PORT = "80"
BFF_FPM_PORT = "9000"
REVERB_PATH = "/app"
REVERB_PORT = "8080"


def logical_lines(text: str) -> list[str]:
    """Une las instrucciones multilínea de un Dockerfile."""
    instructions: list[str] = []
    buffer = ""

    for raw_line in text.splitlines():
        line = raw_line.strip()

        if not line or line.startswith("#"):
            continue

        if buffer:
            buffer = f"{buffer} {line}".strip()
        else:
            buffer = line

        if buffer.endswith("\\"):
            buffer = buffer[:-1].strip()
            continue

        instructions.append(buffer)
        buffer = ""

    if buffer:
        instructions.append(buffer)

    return instructions


def parse_dockerfile(
    text: str,
) -> tuple[dict[str, str], dict[str, str], list[tuple[list[str], str | None]]]:
    """Devuelve los stages declarados, sus `WORKDIR` y las instrucciones `COPY`."""
    stages: dict[str, str] = {}
    workdirs: dict[str, str] = {}
    copies: list[tuple[list[str], str | None]] = []
    current_stage: str | None = None

    for instruction in logical_lines(text):
        parts = shlex.split(instruction)
        keyword = parts[0].upper()

        if keyword == "FROM":
            if "AS" in [part.upper() for part in parts]:
                index = [part.upper() for part in parts].index("AS")
                current_stage = parts[index + 1]
                stages[current_stage] = parts[1]
            else:
                current_stage = None
            continue

        if keyword == "WORKDIR" and current_stage is not None:
            workdirs[current_stage] = parts[1]
            continue

        if keyword != "COPY":
            continue

        flags = [part for part in parts[1:] if part.startswith("--")]
        operands = [part for part in parts[1:] if not part.startswith("--")]

        source_stage = None
        for flag in flags:
            if flag.startswith("--from="):
                source_stage = flag.split("=", 1)[1]

        if len(operands) < 2:
            copies.append((operands, source_stage))
            continue

        copies.append((operands[:-1], source_stage))

    return stages, workdirs, copies


def check_copy_sources(
    stages: dict[str, str],
    copies: list[tuple[list[str], str | None]],
    errors: list[str],
) -> int:
    checked = 0

    for sources, source_stage in copies:
        if source_stage is not None:
            if ":" in source_stage:
                # Referencia a una imagen externa, no a un stage de este fichero.
                checked += 1
                continue

            if source_stage not in stages:
                errors.append(
                    f"Dockerfile: COPY --from={source_stage} referencia un stage inexistente"
                )
            checked += 1
            continue

        for source in sources:
            path = ROOT / source
            if not path.exists():
                errors.append(f"Dockerfile: el origen de COPY '{source}' no existe en el repositorio")
            else:
                checked += 1

    return checked


def parse_dockerignore(text: str) -> list[tuple[str, bool]]:
    """Devuelve las reglas de `.dockerignore` con su signo de inclusión."""
    rules: list[tuple[str, bool]] = []

    for raw_line in text.splitlines():
        line = raw_line.strip()

        if not line or line.startswith("#"):
            continue

        negated = line.startswith("!")
        rules.append((line[1:] if negated else line, negated))

    return rules


def dockerignore_pattern(pattern: str) -> re.Pattern[str]:
    """Traduce una regla de `.dockerignore` al patrón que la identifica.

    Se reproducen las reglas de Docker: `**` abarca cualquier número de
    segmentos y una regla sin barra oblicua sólo coincide con el nombre del
    fichero o del directorio.
    """
    segments = pattern.split("/")
    pieces: list[str] = []

    for index, segment in enumerate(segments):
        if segment == "**":
            pieces.append(".*" if index == len(segments) - 1 else "(?:[^/]+/)*")
            continue

        pieces.append(re.escape(segment).replace(r"\*", "[^/]*").replace(r"\?", "[^/]"))

    expression = ""

    for index, piece in enumerate(pieces):
        # El segmento `**` ya incorpora su propio separador.
        if index > 0 and segments[index - 1] != "**":
            expression += "/"

        expression += piece

    if "/" not in pattern:
        expression = f"(?:.*/)?{expression}"

    return re.compile(f"^{expression}$")


def excluded_by_dockerignore(rules: list[tuple[str, bool]], path: str) -> str | None:
    """Devuelve la regla que excluye la ruta indicada, si existe alguna."""
    candidate = path.strip("/")
    excluded: str | None = None

    for pattern, negated in rules:
        if dockerignore_pattern(pattern).match(candidate):
            excluded = None if negated else pattern

    return excluded


def check_dockerignore_rules(
    workdirs: dict[str, str],
    copies: list[tuple[list[str], str | None]],
    errors: list[str],
) -> int:
    """Comprueba que ninguna regla de `.dockerignore` excluye un origen de `COPY`.

    Un origen excluido hace fallar la construcción de la imagen, de modo que ni
    la ruta literal ni su forma relativa al `WORKDIR` del stage pueden
    coincidir con una regla de exclusión.
    """
    rules = parse_dockerignore(DOCKERIGNORE.read_text(encoding="utf-8"))
    checked = 0

    for sources, source_stage in copies:
        for source in sources:
            candidates = [source]
            workdir = workdirs.get(source_stage or "", "").rstrip("/")

            if workdir and source.startswith(f"{workdir}/"):
                candidates.append(source[len(workdir) + 1 :])

            for candidate in candidates:
                rule = excluded_by_dockerignore(rules, candidate)

                if rule is not None:
                    errors.append(
                        f"Dockerfile: el origen de COPY '{source}' está excluido por la regla "
                        f"'{rule}' de .dockerignore"
                    )
                    break

            checked += 1

    return checked


def check_compose(errors: list[str]) -> int:
    document = yaml.safe_load(COMPOSE_FILE.read_text(encoding="utf-8"))

    if not isinstance(document, dict):
        errors.append("docker-compose.yml: el documento YAML no es un mapa")
        return 0

    services = document.get("services") or {}

    for name in REQUIRED_SERVICES:
        if name not in services:
            errors.append(f"docker-compose.yml: falta el servicio '{name}'")

    bff = services.get("bff") or {}
    depends_on = bff.get("depends_on") or {}
    dependency_names = set(depends_on.keys()) if isinstance(depends_on, dict) else set(depends_on)

    for dependency in BFF_DEPENDENCIES:
        if dependency not in dependency_names:
            errors.append(f"docker-compose.yml: 'bff' no declara '{dependency}' entre sus dependencias")

    for name, expected_target in EXPECTED_TARGETS.items():
        service = services.get(name) or {}
        build = service.get("build") or {}
        target = build.get("target") if isinstance(build, dict) else None

        if target != expected_target:
            errors.append(
                f"docker-compose.yml: el servicio '{name}' debe construirse con el target '{expected_target}'"
            )

    for name, expected_image in EXPECTED_IMAGES.items():
        service = services.get(name) or {}
        image = service.get("image")

        if image != expected_image:
            errors.append(f"docker-compose.yml: el servicio '{name}' debe usar la imagen '{expected_image}'")

    for name, service in services.items():
        for volume in service.get("volumes") or []:
            if not isinstance(volume, str):
                continue

            source = volume.split(":", 1)[0]

            if source.startswith("."):
                path = (ROOT / source).resolve()

                if not path.exists():
                    errors.append(
                        f"docker-compose.yml: el montaje '{source}' del servicio '{name}' no existe en el repositorio"
                    )

    return len(services)


def check_nginx(errors: list[str]) -> int:
    """Comprueba las rutas del SPA y el upstream declarados en `nginx.conf`.

    Las rutas de nginx apuntan al contenedor, pero cada una se corresponde con
    un fichero del repositorio: el documento de entrada del SPA, el origen de
    los assets compilados y el servicio al que se reenvía la API.
    """
    if not NGINX_CONF.exists():
        errors.append("frontend/nginx.conf: falta la configuración de nginx")
        return 0

    text = NGINX_CONF.read_text(encoding="utf-8")
    document = yaml.safe_load(COMPOSE_FILE.read_text(encoding="utf-8"))
    services = document.get("services") or {}
    checked = 0

    # Documento de entrada del SPA referenciado por la directiva `try_files`.
    for fallback in re.findall(r"try_files[^;]*?(/[^\s;]+\.html)", text):
        source = ROOT / "frontend" / fallback.lstrip("/")

        if not source.exists():
            errors.append(
                f"frontend/nginx.conf: el documento de entrada '{fallback}' no existe en el repositorio"
            )
        else:
            checked += 1

    # Origen de los assets que la imagen `frontend` publica en `/assets/`.
    if "location /assets/" in text:
        if not (ROOT / "frontend" / "src").is_dir():
            errors.append("frontend/nginx.conf: no existe el origen de los assets compilados 'frontend/src'")
        else:
            checked += 1

    # Upstream del reenvío de la API: debe ser un servicio del despliegue con
    # el puerto publicado.
    for host, port in re.findall(r"set\s+\$\w+\s+([\w.-]+):(\d+)", text):
        if host not in services:
            errors.append(f"frontend/nginx.conf: el upstream '{host}' no es un servicio de docker-compose.yml")
            continue

        published = " ".join(str(value) for value in (services[host].get("ports") or []))

        if f":{port}" not in published:
            errors.append(
                f"frontend/nginx.conf: el servicio '{host}' no publica el puerto {port} del upstream"
            )

        checked += 1

    return checked


def check_service_configs(errors: list[str]) -> int:
    """Comprueba la política de persistencia de MySQL y Redis."""
    checked = 0

    for path in (MYSQL_CNF, REDIS_CONF):
        if not path.exists():
            errors.append(f"docker: falta el fichero de configuración '{path.relative_to(ROOT)}'")
        else:
            checked += 1

    if MYSQL_CNF.exists():
        text = MYSQL_CNF.read_text(encoding="utf-8")

        if not re.search(r"^innodb_flush_log_at_trx_commit\s*=\s*1", text, re.MULTILINE):
            errors.append("docker/mysql/my.cnf: no declara la persistencia durable de InnoDB")
        else:
            checked += 1

    if REDIS_CONF.exists():
        text = REDIS_CONF.read_text(encoding="utf-8")

        if not re.search(r"^appendonly\s+yes", text, re.MULTILINE):
            errors.append("docker/redis/redis.conf: no habilita la persistencia AppendOnly")
        else:
            checked += 1

    return checked


def check_readme(errors: list[str]) -> int:
    """Comprueba que el README documenta el despliegue de MSD.

    El documento debe nombrar los cuatro servicios del `docker-compose.yml` y
    recoger la secuencia de arranque con un paso por servicio.
    """
    if not README.exists():
        errors.append("falta el fichero README.md con la documentación del despliegue")
        return 0

    text = README.read_text(encoding="utf-8")
    checked = 0

    for service in REQUIRED_SERVICES:
        if not re.search(rf"`{service}`", text):
            errors.append(f"README.md: no nombra el servicio '{service}'")
        else:
            checked += 1

    sequence = re.findall(r"^\d+\.\s+\*\*`([a-z]+)`\*\*", text, re.MULTILINE)

    if sorted(sequence) != sorted(REQUIRED_SERVICES):
        errors.append(
            "README.md: la secuencia de arranque debe declarar un paso por servicio "
            f"({', '.join(REQUIRED_SERVICES)}) y se ha encontrado: {', '.join(sequence) or 'ninguno'}"
        )
    else:
        checked += 1

    for topic in DEPLOYMENT_TOPICS:
        if topic.lower() not in text.lower():
            errors.append(f"README.md: no documenta '{topic}'")
        else:
            checked += 1

    return checked


def published_ports(service: dict) -> list[tuple[str, str]]:
    """Devuelve los pares (puerto del host, puerto del contenedor) publicados."""
    pairs: list[tuple[str, str]] = []

    for value in service.get("ports") or []:
        if not isinstance(value, str):
            continue

        parts = value.split(":")
        container_port = parts[-1]
        host_port = parts[0] if len(parts) > 1 else parts[0]

        pairs.append((host_port, container_port))

    return pairs


def check_bff_http_entrypoint(errors: list[str]) -> int:
    """Comprueba que la API del BFF se publica por HTTP en el host.

    El contenedor del `bff` sirve la aplicación Laravel con nginx sobre
    php-fpm; el puerto FastCGI queda acotado al propio contenedor y la API se
    publica por HTTP.
    """
    document = yaml.safe_load(COMPOSE_FILE.read_text(encoding="utf-8"))
    services = document.get("services") or {}
    bff = services.get("bff") or {}
    checked = 0

    pairs = published_ports(bff)

    if not any(container_port == BFF_HTTP_PORT for _, container_port in pairs):
        errors.append(
            f"docker-compose.yml: el servicio 'bff' no publica el puerto HTTP {BFF_HTTP_PORT} "
            "del contenedor"
        )
    else:
        checked += 1

    if not BFF_NGINX_CONF.exists():
        errors.append(
            f"docker: falta el servidor HTTP del BFF '{BFF_NGINX_CONF.relative_to(ROOT)}'"
        )
        return checked

    text = BFF_NGINX_CONF.read_text(encoding="utf-8")

    if not re.search(r"listen\s+80;", text):
        errors.append("docker/php/nginx.conf: el servidor del BFF no escucha en el puerto 80")
    else:
        checked += 1

    if f"fastcgi_pass 127.0.0.1:{BFF_FPM_PORT}" not in text:
        errors.append(
            "docker/php/nginx.conf: el servidor del BFF no reenvía las peticiones a php-fpm "
            f"(fastcgi_pass 127.0.0.1:{BFF_FPM_PORT})"
        )
    else:
        checked += 1

    if "COPY docker/php/nginx.conf" not in DOCKERFILE.read_text(encoding="utf-8"):
        errors.append("Dockerfile: la imagen del BFF no instala docker/php/nginx.conf")
    else:
        checked += 1

    return checked


def check_realtime_route(errors: list[str]) -> int:
    """Comprueba que el canal WebSocket de Reverb es alcanzable desde el SPA."""
    checked = 0

    for path, label in ((NGINX_CONF, "frontend/nginx.conf"), (BFF_NGINX_CONF, "docker/php/nginx.conf")):
        if not path.exists():
            continue

        text = path.read_text(encoding="utf-8")

        if f"location {REVERB_PATH}" not in text:
            errors.append(f"{label}: no enruta la ruta WebSocket '{REVERB_PATH}'")
        else:
            checked += 1

        if "proxy_set_header Upgrade" not in text:
            errors.append(f"{label}: no reenvía la cabecera 'Upgrade' del protocolo WebSocket")
        else:
            checked += 1

    document = yaml.safe_load(COMPOSE_FILE.read_text(encoding="utf-8"))
    services = document.get("services") or {}
    frontend = services.get("frontend") or {}
    build = frontend.get("build") or {}
    args = build.get("args") if isinstance(build, dict) else None
    args = args or {}

    for name in ("VITE_REVERB_APP_KEY", "VITE_REVERB_HOST", "VITE_REVERB_PORT"):
        if name not in args:
            errors.append(f"docker-compose.yml: el servicio 'frontend' no recibe el argumento '{name}'")
        else:
            checked += 1

    return checked


def check_realtime_broadcasting(errors: list[str]) -> int:
    """Comprueba el cableado del canal de tiempo real en el despliegue.

    El dominio difunde los eventos con la conexión de broadcasting por
    defecto: si no es la de Reverb, ningún cliente WebSocket los recibe. La
    orquestación mediante Redis es la que permite que un evento emitido en una
    réplica del BFF llegue a los clientes de las demás.
    """
    document = yaml.safe_load(COMPOSE_FILE.read_text(encoding="utf-8"))
    services = document.get("services") or {}
    bff = services.get("bff") or {}
    environment = bff.get("environment") or {}
    checked = 0

    broadcast_connection = str(environment.get("BROADCAST_CONNECTION", ""))

    if broadcast_connection != "reverb":
        errors.append(
            "docker-compose.yml: el servicio 'bff' debe difundir por la conexión "
            f"'reverb' (BROADCAST_CONNECTION) y declara '{broadcast_connection}'"
        )
    else:
        checked += 1

    scaling = str(environment.get("REVERB_SCALING_ENABLED", ""))

    if not re.search(r"REVERB_SCALING_ENABLED:-?\s*true", scaling, re.IGNORECASE):
        errors.append(
            "docker-compose.yml: el servicio 'bff' debe activar la orquestación de Reverb "
            f"mediante Redis (REVERB_SCALING_ENABLED) y declara '{scaling}'"
        )
    else:
        checked += 1

    # El cliente de broadcasting de la aplicación habla con el servidor Reverb
    # que convive en el mismo contenedor.
    if str(environment.get("REVERB_HOST", "")) != "127.0.0.1":
        errors.append(
            "docker-compose.yml: el servicio 'bff' debe apuntar a Reverb en '127.0.0.1' "
            "(REVERB_HOST), que es donde corre dentro del contenedor"
        )
    else:
        checked += 1

    if str(environment.get("REVERB_PORT", "")) != REVERB_PORT:
        errors.append(
            f"docker-compose.yml: el servicio 'bff' debe apuntar a Reverb en el puerto "
            f"{REVERB_PORT} (REVERB_PORT)"
        )
    else:
        checked += 1

    return checked


def main() -> int:
    errors: list[str] = []

    for path in (DOCKERFILE, DOCKERIGNORE, COMPOSE_FILE):
        if not path.exists():
            print(f"[verify-containers] ERROR: falta el fichero {path.relative_to(ROOT)}", file=sys.stderr)
            return 1

    stages, workdirs, copies = parse_dockerfile(DOCKERFILE.read_text(encoding="utf-8"))
    missing_stages = [name for name in ("frontend-builder", "frontend", "bff") if name not in stages]

    for name in missing_stages:
        errors.append(f"Dockerfile: falta el stage '{name}'")

    checked_sources = check_copy_sources(stages, copies, errors)
    checked_rules = check_dockerignore_rules(workdirs, copies, errors)
    service_count = check_compose(errors)
    checked_nginx = check_nginx(errors)
    checked_configs = check_service_configs(errors)
    checked_docs = check_readme(errors)
    checked_http = check_bff_http_entrypoint(errors)
    checked_realtime = check_realtime_route(errors)
    checked_broadcast = check_realtime_broadcasting(errors)

    print("[verify-containers] stage(s) declarado(s): " + ", ".join(sorted(stages)))
    print(f"[verify-containers] servicios declarados en docker-compose.yml: {service_count}")
    print(f"[verify-containers] orígenes de COPY comprobados: {checked_sources}")
    print(f"[verify-containers] orígenes de COPY no excluidos por .dockerignore: {checked_rules}")
    print(f"[verify-containers] rutas de nginx comprobadas: {checked_nginx}")
    print(f"[verify-containers] políticas de persistencia comprobadas: {checked_configs}")
    print(f"[verify-containers] puntos de la documentación comprobados: {checked_docs}")
    print(f"[verify-containers] publicación HTTP de la API comprobada: {checked_http}")
    print(f"[verify-containers] ruta WebSocket comprobada: {checked_realtime}")
    print(f"[verify-containers] cableado del broadcasting comprobado: {checked_broadcast}")

    if errors:
        for error in errors:
            print(f"[verify-containers] ERROR: {error}", file=sys.stderr)
        print(f"[verify-containers] verificación fallida con {len(errors)} incidencia(s)", file=sys.stderr)
        return 1

    print("[verify-containers] verificación estática superada")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
