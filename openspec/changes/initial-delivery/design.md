# Design

## Context

El repositorio parte de cero: no existe código de aplicación, ni specs previas en `openspec/specs/`. Todo el diseño de este documento es nuevo y no hay restricciones heredadas del código existente.

Restricciones que sí vienen fijadas y condicionan las decisiones:

- **Contrato de esquema obligatorio**: los nombres de entidades y campos (`VACCINE_REFERENCE`, `CAMPUS`, `VACCINE_BATCH`, `PHASE_TRANSITION`, `LIMS_RESULT`, `ALERT`, `LOG_ENTRY`, `APP_USER`) y sus campos deben coincidir exactamente con el ERD del contrato. No se pueden renombrar ni añadir campos.
- **Despliegue restringido**: 100 % On-Premise o nube privada de MSD. Sin dependencias de servicios públicos en tiempo de ejecución.
- **Soberanía de datos**: la información se procesa íntegramente dentro del entorno seguro de MSD.
- **Entorno de entrega sin daemon de Docker**: los ficheros de contenedores se verifican estáticamente; ninguna tarea puede invocar `docker build` ni `docker compose up`.
- **Separación de responsabilidades**: el frontend no contiene lógica de negocio ni validaciones; el Dominio no depende de frameworks.
- **Fases de negocio**: `Fase de cultivo` → `Formulación Sphereon (Campus 2)` → `Fase logística (Campus 3)`, con gating microbiológico LIMS antes del Campus 3.

Los requisitos concretos están en `specs/`; este documento explica cómo se implementan.

## Goals / Non-Goals

**Goals:**

- Estructura de monorepo con dos artefactos de código (`backend/` Laravel 11 y `frontend/` React 18.3+) y un único punto de containerización en la raíz.
- BFF con Arquitectura Hexagonal real: `Domain` sin dependencias externas, `Application` con casos de uso, `Infrastructure` con adaptadores, `Api` con controladores y rutas.
- Máquina de estados de fase explícita y testeable en el Dominio, independiente de Laravel.
- Integración LIMS de solo lectura, con cliente intercambiable y doble de pruebas.
- Trazabilidad auditable: toda operación crítica genera un `LOG_ENTRY` inmutable con UUID v4/v7 y payload JSON.
- Tiempo real sobre WebSockets con Redis, consumible por el frontend con TanStack Query.
- Entrega containerizada reproducible con un solo `Dockerfile` y un `docker-compose.yml` en la raíz.

**Non-Goals:**

- No se implementa la UI de LIMS Labware ni los sistemas de Campus 1/3: son sistemas externos y solo se consume un contrato/puerto.
- No se implementa el gestor de secretos concreto: se define el puerto `SecretsManager` y un adaptador de referencia para HashiCorp Vault.
- No se implementa el pipeline de CI/CD de MSD ni la infraestructura On-Premise: se define el fichero de pipeline y los guiones de respaldo/restauración.
- No se migra ningún dato preexistente: el proyecto nace vacío y se siembra con datos de referencia (campuses, referencias de vacuna, usuarios).
- No se modela la.notificación SMS como canal fiable: se mantiene como Best-effort con el canal Email como fuente de verdad.

## Decisions

### D1 — Monorepo con `backend/` y `frontend/` separados

**Decisión.** El repositorio contiene `/backend` (aplicación Laravel 11), `/frontend` (SPA React 18.3+ construida con Vite), un único `/Dockerfile` en la raíz con varios *targets* de construcción, y `/docker-compose.yml` en la raíz.

**Por qué.** La frontera BFF/frontend es una frontera de despliegue real: el BFF se escala horizontalmente y el frontend es estático. Separarlos permite construir y escalar cada uno de forma independiente y evita que los artefactos de `node_modules` de uno contaminen al otro.

**Alternativas consideradas.** (a) Laravel sirviendo el SPA con Inertia/Vite: descartado porque el requisito de WebSockets y la necesidad de escalar el frontend de forma independiente favorecen un cliente SPA puro. (b) Repositorios separados: descartado por la simplificación operativa en un único pipeline.

### D2 — Arquitectura Hexagonal con el Dominio aislado

**Decisión.** Cuatro capas bajo `/backend/app`: `Domain` (entidades, value objects, repositorios como interfaces, políticas de negocio, eventos de dominio), `Application` (casos de uso), `Infrastructure` (Eloquent, cliente LIMS, secretos, notificadores, broadcast, exportadores) y `Api` (controladores, requests, resources, middleware, rutas).

**Por qué.** Es la exigencia explícita de la arquitectura técnica. Aísla la máquina de estados de fase y la regla de gating, que son las reglas de negocio más sensibles y las que deben poder probarse sin base de datos ni HTTP.

**Alternativas consideradas.** (a) MVC de Laravel con modelos Eloquent grassroots: descartado por acoplamiento del Dominio a Eloquent, que impide reutilizar la lógica en otros canales. (b) CQRS con eventos de dominio persistidos: descarta porque el volumen es de miles de eventos por hora y no está justificado el coste de una proyección extra; se mantiene un log de auditoría append-only, que ya cubre la consulta.

### D3 — Claves primarias UUID v4/v7 generadas en la capa de aplicación

**Decisión.** Todas las tablas del contrato de esquema usan `id` UUID. El generador se centraliza en `/backend/app/Domain/Shared/ValueObject/Uuid.php` y las migraciones lo aplican vía el trait `/backend/app/Infrastructure/Persistence/Eloquent/Concerns/UuidPrimaryKey.php`.

**Por qué.** El contrato fija `uuid id PK` en las ocho entidades y varios criterios de aceptación exigen que los logs y la auditoría se asociesen a "claves primarias UUID (v4/v7)". El v7 se usa para los registros con orden temporal natural (batch, log) y el v4 para los registros de catálogo (campus, referencia, usuario).

**Alternativas consideradas.** (a) UUID v4 en todo: descartado porque v7 aporta orden por índice y reduce fragmentación en MySQL. (b) `BINARY(16)`: descartado por su legibilidad en logs y auditoría.

### D4 — Máquina de estados de fase en el Dominio, no en el servicio

**Decisión.** `/backend/app/Domain/VaccineBatch/Service/PhaseTransitionPolicy.php` contiene la tabla de transiciones permitidas y `/backend/app/Domain/VaccineBatch/ValueObject/BatchPhase.php` los estados. El agregado `VaccineBatch::advanceTo()` delega en la política y lanza `DomainException` ante un avance no secuencial o un retroceso.

**Por qué.** La secuencia de fases es la regla de negocio central (TC012, TC014, TC022). Centralizarla permite cubrirla con pruebas unitarias puras y garantiza que ningún canal de entrada (API, comando programado, WebSocket) la esquive.

**Alternativas consideradas.** (a) Validar la secuencia en el controlador: descartado por duplicar la regla en cada endpoint. (b) Motor de workflows configurable: descartado por complejidad y por ser un subconjunto de tres estados.

### D5 — Gating LIMS modelado como política de Dominio + estado materializado en `VACCINE_BATCH.status`

**Decisión.** `/backend/app/Domain/VaccineBatch/Service/GatingPolicy.php` decide si un lote puede entrar en `Fase logística (Campus 3)`; `/backend/app/Domain/Lims/Service/GatingEvaluator.php` determina si existe una `LIMS_RESULT` microbiológica aprobada (`result = 'Aprobado'` y `approved_by` informado). El lote bloqueado queda con `status = 'Bloqueado por Gating LIMS'`.

**Por qué.** El bloqueo debe ser observable por el frontend sin que este calcule la regla (TC024): la decisión viaja como `status` dentro de la respuesta de la API. Materializarlo en `status` (campo del contrato) evita inventar un campo nuevo en `VACCINE_BATCH`.

**Alternativas consideradas.** (a) Exponer un booleano calculado en la API: equivalente, pero `status` es un campo ya previsto por el contrato y evita duplicar información. (b) Impedir el avance en el cliente: descartado por la regla explícita de que el frontend no valida reglas de negocio.

### D6 — El "estado" de una alerta se representa con `severity`

**Decisión.** La entidad `ALERT` del contrato tiene exactamente los campos `id`, `batch_id`, `alert_type`, `severity`, `message` y `created_at`; no se añade ninguna columna de estado. En el histórico de alertas, el "estado" exigido por TC010 se representa con el valor persistido de `severity` (`'Crítica'` para prioridad máxima) y, cuando el cliente lo necesita, con el `status` vigente del lote afectado.

**Por qué.** El contrato de esquema es obligatorio y prohíbe inventar campos. `severity` es el único campo de estado disponible en `ALERT`, y la prioridad máxima se materializa en él.

**Alternativas consideradas.** (a) Añadir `status` a `ALERT`: descartado por violar el contrato de esquema. (b) Modelar el estado como evento en `LOG_ENTRY`: descartado por obligar a reconstruir el estado en lectura para un dato que ya está en la fila.

### D7 — Cliente LIMS de solo lectura detrás de un puerto, con doble de pruebas

**Decisión.** El puerto `LimsLabwareClient` (`/backend/app/Domain/Lims/Port/LimsLabwareClient.php`) expone únicamente operaciones de lectura (`fetchBatchResults`, `fetchMicrobiologyApproval`). El adaptador real es `/backend/app/Infrastructure/Lims/HttpLabwareLimsClient.php`; el doble de pruebas es `/backend/tests/Doubles/FakeLabwareLimsClient.php`. La importación se dispara por comando programado (`/backend/app/Console/Commands/SyncLimsResults.php`) y por acción de dominio, nunca desde una escritura hacia LIMS.

**Por qué.** Garantiza estructuralmente la condición de TC001 (solo lectura) y permite simular en pruebas el caso de TC002 (datos inconsistentes) sin un LIMS real.

**Alternativas consideradas.** (a) Cliente HTTP implementado en el caso de uso: descartado por acoplar el caso de uso al transporte. (b) Webhooks de LIMS hacia el BFF: descartado porque LIMS no es un sistema del que se controle el contrato de eventos.

### D8 — Datos inválidos de LIMS: rechazo previo a persistir, con log de error

**Decisión.** `/backend/app/Domain/Lims/Service/LimsPayloadValidator.php` valida la carga recibida antes de cualquier persistencia. Ante datos inconsistentes o inválidos, el caso de uso `ImportLimsResults` no persiste el lote ni sus resultados, y escribe un `LOG_ENTRY` con `event_type = 'lims.import.rejected'` y `payload` con el detalle del error.

**Por qué.** TC002 exige dos cosas simultáneas: que el lote no sea visible en el dashboard y que el intento de importación quede registrado con un error. Un `LOG_ENTRY` con `batch_id` nulo cubre ambos: es la traza del intento fallido y no expone el lote.

**Alternativas consideradas.** (a) Persistir el lote con un estado "inválido": descartado porque lo haría visible en el dashboard. (b) Registrar el error en el log de aplicación: descartado porque la auditoría exige el log estructurado inmutable con UUID.

### D9 — Passport JWT con `auth:api` y autorización por rol en la capa de aplicación

**Decisión.** Laravel Passport emite tokens JWT; todas las rutas de datos de `/backend/routes/api.php` se registran dentro del middleware `auth:api`. El rol vive en `APP_USER.role` y se resuelve en `/backend/app/Application/Authorization/BatchVisibilityPolicy.php` y `/backend/app/Application/Authorization/OperationPolicy.php`, que son la única fuente de verdad sobre qué puede hacer cada rol.

**Por qué.** TC007/NFR003 exigen 401 en token ausente o inválido; TC018, TC021 y TC027 exigen denegaciones por rol. Centralizar la autorización en la capa de aplicación evita dispersar `if` por rol en los controladores y hace las denegaciones testeables con HTTP.

**Alternativas consideradas.** (a) Policies de Laravel por modelo: equivalentes en potencia, pero las policies de Laravel son infraestructura; la decisión es mantener las reglas de rol en `Application` para que el canal WebSocket y los comandos usa la misma lógica. (b) RBAC con paquetes de terceros: descartado por añadir dependencias no necesarias para cinco roles fijos.

### D10 — Log estructurado inmutable en base de datos y en salida estructurada

**Decisión.** Cada operación crítica se registra en `LOG_ENTRY` (`id` UUID v4/v7, `batch_id`, `user_id`, `event_type`, `payload` JSON, `created_at`). La escritura se hace exclusivamente a través de `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentLogEntryRepository.php`, que solo expone `append()`. No existe endpoint ni método de aplicación que actualice o borre entradas. En paralelo, `/backend/app/Infrastructure/Logging/JsonAuditLogger.php` emite la misma entrada en JSON estructurado por stdout.

**Por qué.** TC020, TC026 y NFR005 exigen logs estructurados, inmutables y asociados a UUID, con retención farmacéutica.

**Alternativas consideradas.** (a) Servicio de logging externo (SIEM): descartado por la restricción de despliegue On-Premise. (b) `updated_at` en `LOG_ENTRY`: descartado por el contrato de esquema, que no lo contempla.

### D11 — Tiempo real con Reverb + Redis y caché de eventos

**Decisión.** Laravel Reverb es el servidor WebSocket; Redis actúa como backend de broadcasting y de caché de eventos (`/backend/app/Infrastructure/Realtime/RedisEventBroadcaster.php`). El canal privado `batch-monitoring` se autoriza en `/backend/routes/channels.php` con `auth:api`. El frontend consume el canal con `laravel-echo` + `pusher-js` (`/frontend/src/realtime/echo.ts`). En el despliegue de contenedor la conexión de broadcasting por defecto es `reverb` (`BROADCAST_CONNECTION=reverb`) y el servidor Reverb propaga los eventos por el canal `reverb` de Redis (`REVERB_SCALING_ENABLED=true`), de modo que un evento emitido en una réplica llega a los clientes conectados a las demás.

**Por qué.** Redis ya es requisito del diseño para caché y estados efímeros, y permite el escalado horizontal del BFF (una instancia publica, todas reciben) sin un broker adicional, coherente con NFR009 y con el despliegue On-Premise.

**Alternativas consideradas.** (a) Socket.IO propio: descartado por duplicar capacidades ya presentes en Laravel. (b) Solo polling desde el frontend: descartado por el requisito de latencia inferior a 1 segundo y de actualización sin recarga.

### D12 — TanStack Query como única fuente de verdad del frontend

**Decisión.** El frontend usa TanStack Query (`/frontend/src/hooks/*`) para toda lectura. La recepción de un evento WebSocket dispara `queryClient.invalidateQueries()` sobre las claves afectadas (`batches`, `batch`, `kpis`, `alerts`). No hay estado duplicado en un store propio para datos de servidor.

**Por qué.** TC025 exige sincronización con TanStack Query y TC004 exige actualización de indicadores sin recargar. Invalidar la caché hace que la única copia de la verdad sea el backend.

**Alternativas consideradas.** (a) Zustand/Redux como store de servidor: descartado por solapar responsabilidades con TanStack Query. (b) Pushing del payload completo por WebSocket: descartado por acoplar el contrato de eventos al formato de vista.

### D13 — `CustomSelect` sobre Headless UI, sin `<select>` nativos

**Decisión.** `/frontend/src/components/filters/CustomSelect.tsx` se construye sobre `Listbox` de `@headlessui/react`. Ningún filtro del dashboard usa `<select>` nativo.

**Por qué.** TC013 lo exige literalmente y prohíbe selectores nativos.

**Alternativas consideradas.** (a) `<select>` nativo estilizado: descartado por el criterio de aceptación. (b) React Select: descartado por exigir un componente propio sobre Headless UI.

### D14 — Búsqueda avanzada sobre MySQL con índices y caché Redis

**Decisión.** La búsqueda se resuelve en `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentBatchSearchRepository.php` con filtros por familia de producto (join `VACCINE_REFERENCE`), fase, estado de alerta e intervalo de fechas, paginación por `created_at` descendente y `id` como desempate estable. Las migraciones crean índices compuestos en `VACCINE_BATCH.phase`, `VACCINE_BATCH.status`, `VACCINE_BATCH.current_campus_id`, `VACCINE_BATCH.created_at` y en `ALERT.batch_id`, `ALERT.severity`. Las páginas de resultados se cachean en Redis con clave por hash de filtros.

**Por qué.** El objetivo es P95 inferior a 2 segundos (TC006, NFR008) y el volumen esperado es de miles de lotes, no millones; un índice relacional bien cubierto es más simple de operar y auditar que un motor de búsqueda externo, y encaja con el despliegue On-Premise.

**Alternativas consideradas.** (a) Elasticsearch/Meilisearch: descartado por añadir un servicio no solicitado y por el requisito de despliegue restringido. (b) Búsqueda en el frontend: descartado por no cumplir el P95 ni el volumen.

### D15 — Reportes de trazabilidad en CSV y PDF

**Decisión.** `/backend/app/Domain/Report/Service/TraceabilityReportBuilder.php` compone el modelo del reporte y `/backend/app/Domain/Report/Port/TraceabilityReportExporter.php` lo serializa; los adaptadores son `/backend/app/Infrastructure/Reports/CsvTraceabilityExporter.php` (CSV UTF-8 con BOM) y `/backend/app/Infrastructure/Reports/PdfTraceabilityExporter.php` (PDF con `dompdf`).

**Por qué.** TC011 exige un formato adecuado para auditorías regulatorias. CSV sirve para el procesamiento automatizado y PDF para la auditoría humana.

**Alternativas consideradas.** (a) Solo HTML imprimible: descartado por no ser un formato de entrega auditable. (b) XLSX: descartado por no aportar sobre CSV y por añadir una dependencia.

### D16 — Gestor de secretos como puerto con adaptador Vault

**Decisión.** `/backend/app/Domain/Secrets/Port/SecretsManager.php` (interfaz) y `/backend/app/Infrastructure/Secrets/VaultSecretsManager.php` (adaptador HTTP contra HashiCorp Vault). Los valores sensibles (MySQL, Redis, LIMS, credenciales de notificación) se resuelven en tiempo de ejecución mediante `SecretResolver`. El despliegue de producción no monta ningún `.env`; `/backend/.env.example` es sólo referencia de desarrollo.

**Por qué.** TC028 y NFR007 exigen credenciales inyectadas desde un gestor de secretos en tiempo de ejecución, sin archivos `.env` en producción ni secretos en variables de entorno.

**Alternativas consideradas.** (a) Variables de entorno inyectadas por el orquestador: descartado porque NFR007 excluye explícitamente las variables de entorno como portador de credenciales de producción. (b) Vault como sidecar con agente que escribe fichero: descartado por reintroducir el fichero de credenciales en disco.

### D17 — Un único Dockerfile en la raíz con dos *targets*

**Decisión.** `/Dockerfile` declara los *stages* `frontend-builder` (node:20-alpine), `frontend` (nginx:1.27-alpine) y `bff` (php:8.3-fpm-alpine), con `bff` como *target* por defecto. `/docker-compose.yml` construye `bff` con `target: bff` y `frontend` con `target: frontend`, y define además `mysql` (mysql:8.0) y `redis` (redis:7-alpine) con volúmenes y red propias. `/docker/php/supervisor.conf` y `/docker/php/entrypoint.sh` orquestan nginx, php-fpm, `queue:work` y Reverb dentro de la imagen del BFF. La configuración `/docker/php/nginx.conf` atiende el puerto `80` del contenedor y reenvía la aplicación a php-fpm por FastCGI en `127.0.0.1:9000`, de modo que la API se publica por HTTP en el host y el canal WebSocket (`/app/`) llega a Reverb. El mismo `docker-compose.yml` cablea el canal de tiempo real con `BROADCAST_CONNECTION=reverb` y `REVERB_SCALING_ENABLED=true`, para que los eventos que difunde el dominio lleguen a los clientes WebSocket y se propaguen por Redis entre réplicas del BFF.

**Por qué.** El criterio de aceptación pide un Dockerfile y un docker-compose.yml en la raíz. Un solo fichero evita duplicar la definición de la imagen y cumple la imagen base php:8.3-fpm-alpine exigida por la arquitectura técnica para el BFF. Además, php-fpm habla FastCGI y no HTTP: sin un servidor HTTP en la imagen del BFF, la API no quedaría accesible desde el exterior del host.

**Alternativas consideradas.** (a) Un Dockerfile por artefacto (`/backend/Dockerfile`, `/frontend/Dockerfile`): descartado por no cumplir el criterio de "un Dockerfile en la raíz". (b) Imagen única que sirva API y SPA: descartada porque el frontend necesita nginx y una base distinta a PHP.

### D18 — Verificación estática de los ficheros de contenedores

**Decisión.** Como el entorno de entrega no tiene daemon de Docker, la verificación de `/Dockerfile` y `/docker-compose.yml` es estática: el YAML se parsea y se comprueba que cada origen de `COPY` existe en el repositorio, que ninguna regla de `.dockerignore` lo excluye, que el `bff` publica un puerto HTTP accesible desde el host y que la ruta WebSocket de Reverb es alcanzable desde el SPA. Se ejecuta como paso explícito de las tareas de contenedor, nunca como aserción de contenido dentro de las pruebas automatizadas.

**Por qué.** Las pruebas deben ejercitar comportamiento (funciones, endpoints HTTP) y no el contenido de ficheros de infraestructura. Además el criterio de aceptación sobre Docker se valida por inspección del entregable.

**Alternativas consideradas.** (a) Test PHPUnit que lea el compose: descartado por la regla de que las pruebas no deben verificar el contenido de `Dockerfile`, `docker-compose.yml`, README o scripts. (b) `docker build` en el pipeline: descartado porque el entorno de entrega no dispone de daemon.

### D19 — Backup y restore de MySQL y Redis como scripts declarativos

**Decisión.** `/scripts/backup.sh` y `/scripts/restore.sh` ejecutan `mysqldump` y `redis-cli --rdb`/RESTORE, y `/backend/app/Console/Commands/BackupCriticalData.php` y `/backend/app/Console/Commands/RestoreCriticalData.php` exponen la misma operación al plano de aplicación. El pipeline los invoca en el ambiente correspondiente.

**Por qué.** NFR010 exige respaldo y restauración exitosos de MySQL y Redis con integridad y consistencia, dentro del despliegue On-Premise.

**Alternativas consideradas.** (a) Solo scripts de shell: descartado por dejar la operación fuera del modelo de la aplicación y sin registro auditable. (b) Replicación como única medida: descartado porque no cubre la restauración tras corrupción.

### D20 — Etapa de análisis de seguridad en CI/CD

**Decisión.** `/`.github/workflows/ci.yml` ejecuta en cada push y pull request: instalación de dependencias PHP y Node, suite de pruebas, análisis estático de PHP, y una etapa de seguridad (`composer audit`, `npm audit`, y escaneo de código con una herramienta de análisis de dependencias) que falla el pipeline ante vulnerabilidades. La promoción a `staging` y `prod` queda condicionada al éxito de esa etapa.

**Por qué.** NFR011 exige una etapa de análisis de seguridad que detecte vulnerabilidades y bloquee el despliegue de código inseguro.

**Alternativas consideradas.** (a) Escaneo solo en `main`: descartado por detectar tarde. (b) Revisión manual: descartado por no ser automatizable ni bloqueante.

### D21 — Observabilidad de KPI y latencias en el propio backend

**Decisión.** `/backend/app/Api/Http/Controllers/V1/DashboardController.php` expone `GET /api/v1/dashboard/kpis` con los tiempos de ciclo por fase y el desglose de estados, calculados en `/backend/app/Domain/Dashboard/Service/CycleTimeCalculator.php`. Las búsquedas y la importación de LIMS registran su duración mediante `/backend/app/Infrastructure/Logging/JsonAuditLogger.php`.

**Por qué.** NFR001 exige que los KPIs se muestren en menos de 3 segundos, y NFR002/NFR006/NFR008 exigen umbrales de latencia medibles.

**Alternativas consideradas.** (a) KPIs calculados en el frontend: descartado por distribuir la lógica de negocio y por no cumplir el P95 objetivo. (b) Métricas en un sistema externo de observabilidad: descartado por no estar solicitado.

### D22 — Toolchain de desarrollo con PHP 8.3 independiente del daemon de Docker

**Decisión.** `/scripts/setup-php83.sh` instala en el perfil del usuario un PHP 8.3 (compilación estática enlazada, con `pdo_sqlite`, `pdo_mysql`, `mbstring`, `openssl`, `curl`, `bcmath` y `zip`) y Composer 2, y deja envoltorios en `~/bin` y `~/.local/bin`. El script es idempotente y termina verificando `php -v`, `php -m` y `composer --version`.

**Por qué.** El entorno de verificación no dispone de daemon de Docker, de modo que la imagen `php:8.3-fpm-alpine` del despliegue no puede usarse para ejecutar `composer install`, `php artisan test` ni la verificación de contenedores. Instalar el interprete en el perfil del usuario mantiene la suite ejecutable con el mismo código de aplicación que despliega la imagen.

**Alternativas consideradas.** (a) `docker compose run` para las verificaciones: descartado porque no hay daemon disponible y porque D18 exige que la verificación de contenedores sea estática. (b) Compilar PHP desde el código fuente: descartado por exigir toolchain de compilación y por multiplicar el tiempo de aprovisionamiento. (c) Usar el PHP del sistema: descartado porque la versión no es la fijada por el enunciado y porque no incluye las extensiones exigidas.

## Estructura de ficheros a crear

Rutas exactas, relativas a la raíz del repositorio.

### Raíz

- `/docker-compose.yml`
- `/Dockerfile`
- `/.dockerignore`
- `/.env.example`
- `/README.md`
- `/.github/workflows/ci.yml`
- `/docker/php/php.ini`
- `/docker/php/supervisor.conf`
- `/docker/php/entrypoint.sh`
- `/docker/mysql/my.cnf`
- `/docker/redis/redis.conf`
- `/scripts/backup.sh`
- `/scripts/restore.sh`
- `/scripts/security-scan.sh`
- `/scripts/setup-php83.sh`

### Backend — Laravel 11

- `/backend/composer.json`
- `/backend/composer.lock`
- `/backend/artisan`
- `/backend/phpunit.xml`
- `/backend/.env.example`
- `/backend/bootstrap/app.php`
- `/backend/bootstrap/providers.php`
- `/backend/config/app.php`
- `/backend/config/auth.php`
- `/backend/config/broadcasting.php`
- `/backend/config/cache.php`
- `/backend/config/database.php`
- `/backend/config/lims.php`
- `/backend/config/monitoring.php`
- `/backend/config/queue.php`
- `/backend/config/reports.php`
- `/backend/config/secrets.php`
- `/backend/config/services.php`
- `/backend/routes/api.php`
- `/backend/routes/channels.php`
- `/backend/routes/console.php`

Dominio:

- `/backend/app/Domain/Shared/ValueObject/Uuid.php`
- `/backend/app/Domain/Shared/Event/DomainEvent.php`
- `/backend/app/Domain/Shared/Event/DomainEventRecorder.php`
- `/backend/app/Domain/Shared/Exception/DomainException.php`
- `/backend/app/Domain/Shared/Exception/UnauthorizedOperationException.php`
- `/backend/app/Domain/VaccineBatch/Entity/VaccineBatch.php`
- `/backend/app/Domain/VaccineBatch/Entity/PhaseTransition.php`
- `/backend/app/Domain/VaccineBatch/Entity/VaccineReference.php`
- `/backend/app/Domain/VaccineBatch/Entity/Campus.php`
- `/backend/app/Domain/VaccineBatch/ValueObject/BatchPhase.php`
- `/backend/app/Domain/VaccineBatch/ValueObject/BatchStatus.php`
- `/backend/app/Domain/VaccineBatch/Repository/VaccineBatchRepository.php`
- `/backend/app/Domain/VaccineBatch/Repository/PhaseTransitionRepository.php`
- `/backend/app/Domain/VaccineBatch/Repository/CampusRepository.php`
- `/backend/app/Domain/VaccineBatch/Repository/VaccineReferenceRepository.php`
- `/backend/app/Domain/VaccineBatch/Service/PhaseTransitionPolicy.php`
- `/backend/app/Domain/VaccineBatch/Service/GatingPolicy.php`
- `/backend/app/Domain/VaccineBatch/Event/BatchCreated.php`
- `/backend/app/Domain/VaccineBatch/Event/BatchPhaseAdvanced.php`
- `/backend/app/Domain/VaccineBatch/Event/PhysicalReceptionRegistered.php`
- `/backend/app/Domain/VaccineBatch/Event/LogisticsCycleClosed.php`
- `/backend/app/Domain/Lims/Entity/LimsResult.php`
- `/backend/app/Domain/Lims/Repository/LimsResultRepository.php`
- `/backend/app/Domain/Lims/Service/LimsPayloadValidator.php`
- `/backend/app/Domain/Lims/Service/GatingEvaluator.php`
- `/backend/app/Domain/Lims/Port/LimsLabwareClient.php`
- `/backend/app/Domain/Lims/Event/LimsResultsImported.php`
- `/backend/app/Domain/Lims/Event/LimsImportRejected.php`
- `/backend/app/Domain/Alert/Entity/Alert.php`
- `/backend/app/Domain/Alert/Repository/AlertRepository.php`
- `/backend/app/Domain/Alert/Service/AlertFactory.php`
- `/backend/app/Domain/Alert/Service/ThermalDeviationDetector.php`
- `/backend/app/Domain/Alert/Service/SphereonAnomalyDetector.php`
- `/backend/app/Domain/Alert/Event/AlertRaised.php`
- `/backend/app/Domain/Audit/Entity/LogEntry.php`
- `/backend/app/Domain/Audit/Repository/LogEntryRepository.php`
- `/backend/app/Domain/Audit/Service/StructuredAuditLogger.php`
- `/backend/app/Domain/User/Entity/AppUser.php`
- `/backend/app/Domain/User/Repository/AppUserRepository.php`
- `/backend/app/Domain/User/ValueObject/UserRole.php`
- `/backend/app/Domain/Report/Service/TraceabilityReportBuilder.php`
- `/backend/app/Domain/Report/Port/TraceabilityReportExporter.php`
- `/backend/app/Domain/Search/Service/BatchSearchService.php`
- `/backend/app/Domain/Search/Port/BatchSearchRepository.php`
- `/backend/app/Domain/Dashboard/Service/CycleTimeCalculator.php`
- `/backend/app/Domain/Secrets/Port/SecretsManager.php`

Aplicación:

- `/backend/app/Application/Batches/CreateBatch.php`
- `/backend/app/Application/Batches/AdvanceBatchPhase.php`
- `/backend/app/Application/Batches/RegisterPhysicalReception.php`
- `/backend/app/Application/Batches/CloseLogisticsCycle.php`
- `/backend/app/Application/Batches/GetBatchDetail.php`
- `/backend/app/Application/Batches/GetBatchTraceability.php`
- `/backend/app/Application/Batches/ListBatches.php`
- `/backend/app/Application/Lims/ImportLimsResults.php`
- `/backend/app/Application/Lims/RegisterLimsResult.php`
- `/backend/app/Application/Lims/EvaluateGatingRelease.php`
- `/backend/app/Application/Lims/ForceGatingRelease.php`
- `/backend/app/Application/Alerts/ListAlerts.php`
- `/backend/app/Application/Alerts/GetAlertHistory.php`
- `/backend/app/Application/Audit/GetAuditTrail.php`
- `/backend/app/Application/Reports/ExportTraceabilityReport.php`
- `/backend/app/Application/Dashboard/GetDashboardKpis.php`
- `/backend/app/Application/Authorization/BatchVisibilityPolicy.php`
- `/backend/app/Application/Authorization/OperationPolicy.php`

Infraestructura:

- `/backend/app/Infrastructure/Persistence/Eloquent/Concerns/UuidPrimaryKey.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Model/VaccineBatchModel.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Model/PhaseTransitionModel.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Model/LimsResultModel.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Model/AlertModel.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Model/LogEntryModel.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Model/AppUserModel.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Model/CampusModel.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Model/VaccineReferenceModel.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentVaccineBatchRepository.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentPhaseTransitionRepository.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentLimsResultRepository.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentAlertRepository.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentLogEntryRepository.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentAppUserRepository.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentCampusRepository.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentVaccineReferenceRepository.php`
- `/backend/app/Infrastructure/Persistence/Eloquent/Repository/EloquentBatchSearchRepository.php`
- `/backend/app/Infrastructure/Lims/HttpLabwareLimsClient.php`
- `/backend/app/Infrastructure/Secrets/VaultSecretsManager.php`
- `/backend/app/Infrastructure/Secrets/SecretResolver.php`
- `/backend/app/Infrastructure/Notifier/AlertNotifier.php`
- `/backend/app/Infrastructure/Notifier/EmailAlertNotifier.php`
- `/backend/app/Infrastructure/Notifier/SmsAlertNotifier.php`
- `/backend/app/Infrastructure/Notifier/CompositeAlertNotifier.php`
- `/backend/app/Infrastructure/Logging/JsonAuditLogger.php`
- `/backend/app/Infrastructure/Realtime/RedisEventBroadcaster.php`
- `/backend/app/Infrastructure/Reports/CsvTraceabilityExporter.php`
- `/backend/app/Infrastructure/Reports/PdfTraceabilityExporter.php`

API:

- `/backend/app/Api/Http/Controllers/V1/BatchController.php`
- `/backend/app/Api/Http/Controllers/V1/PhaseTransitionController.php`
- `/backend/app/Api/Http/Controllers/V1/LimsController.php`
- `/backend/app/Api/Http/Controllers/V1/GatingController.php`
- `/backend/app/Api/Http/Controllers/V1/AlertController.php`
- `/backend/app/Api/Http/Controllers/V1/TraceabilityController.php`
- `/backend/app/Api/Http/Controllers/V1/ReportController.php`
- `/backend/app/Api/Http/Controllers/V1/SearchController.php`
- `/backend/app/Api/Http/Controllers/V1/AuditController.php`
- `/backend/app/Api/Http/Controllers/V1/DashboardController.php`
- `/backend/app/Api/Http/Controllers/V1/HealthController.php`
- `/backend/app/Api/Http/Requests/CreateBatchRequest.php`
- `/backend/app/Api/Http/Requests/AdvancePhaseRequest.php`
- `/backend/app/Api/Http/Requests/RegisterPhysicalReceptionRequest.php`
- `/backend/app/Api/Http/Requests/CloseLogisticsCycleRequest.php`
- `/backend/app/Api/Http/Requests/RegisterLimsResultRequest.php`
- `/backend/app/Api/Http/Requests/BatchSearchRequest.php`
- `/backend/app/Api/Http/Resources/BatchResource.php`
- `/backend/app/Api/Http/Resources/BatchDetailResource.php`
- `/backend/app/Api/Http/Resources/PhaseTransitionResource.php`
- `/backend/app/Api/Http/Resources/LimsResultResource.php`
- `/backend/app/Api/Http/Resources/AlertResource.php`
- `/backend/app/Api/Http/Resources/LogEntryResource.php`
- `/backend/app/Api/Http/Resources/CycleTimeResource.php`
- `/backend/app/Api/Http/Middleware/EnsureUserRole.php`
- `/backend/app/Api/Http/Middleware/AuditRequest.php`
- `/backend/app/Api/Providers/AppServiceProvider.php`
- `/backend/app/Api/Providers/BroadcastServiceProvider.php`
- `/backend/app/Api/Providers/RouteServiceProvider.php`

Consola:

- `/backend/app/Console/Commands/SyncLimsResults.php`
- `/backend/app/Console/Commands/BackupCriticalData.php`
- `/backend/app/Console/Commands/RestoreCriticalData.php`

Persistencia y datos:

- `/backend/database/migrations/2026_01_01_000100_create_vaccine_reference_table.php`
- `/backend/database/migrations/2026_01_01_000200_create_campus_table.php`
- `/backend/database/migrations/2026_01_01_000300_create_app_user_table.php`
- `/backend/database/migrations/2026_01_01_000400_create_vaccine_batch_table.php`
- `/backend/database/migrations/2026_01_01_000500_create_phase_transition_table.php`
- `/backend/database/migrations/2026_01_01_000600_create_lims_result_table.php`
- `/backend/database/migrations/2026_01_01_000700_create_alert_table.php`
- `/backend/database/migrations/2026_01_01_000800_create_log_entry_table.php`
- `/backend/database/migrations/2026_01_01_000900_create_personal_access_tokens_table.php`
- `/backend/database/migrations/2026_01_01_001000_create_jobs_table.php`
- `/backend/database/seeders/DatabaseSeeder.php`
- `/backend/database/seeders/CampusSeeder.php`
- `/backend/database/seeders/VaccineReferenceSeeder.php`
- `/backend/database/seeders/AppUserSeeder.php`

Pruebas backend:

- `/backend/tests/Support/CreatesApplication.php`
- `/backend/tests/Support/InteractsWithDatabase.php`
- `/backend/tests/Support/MakesAuthenticatedRequests.php`
- `/backend/tests/Doubles/FakeLabwareLimsClient.php`
- `/backend/tests/Unit/Domain/VaccineBatch/PhaseTransitionPolicyTest.php`
- `/backend/tests/Unit/Domain/VaccineBatch/GatingPolicyTest.php`
- `/backend/tests/Unit/Domain/Lims/LimsPayloadValidatorTest.php`
- `/backend/tests/Unit/Domain/Alert/AlertFactoryTest.php`
- `/backend/tests/Unit/Domain/User/UserRoleTest.php`
- `/backend/tests/Feature/Api/BatchCreationTest.php`
- `/backend/tests/Feature/Api/BatchPhaseAdvanceTest.php`
- `/backend/tests/Feature/Api/LogisticsCycleTest.php`
- `/backend/tests/Feature/Api/LimsImportTest.php`
- `/backend/tests/Feature/Api/GatingTest.php`
- `/backend/tests/Feature/Api/AuthenticationTest.php`
- `/backend/tests/Feature/Api/AuthorizationTest.php`
- `/backend/tests/Feature/Api/BatchSearchTest.php`
- `/backend/tests/Feature/Api/AlertHistoryTest.php`
- `/backend/tests/Feature/Api/TraceabilityTest.php`
- `/backend/tests/Feature/Api/ReportExportTest.php`
- `/backend/tests/Feature/Api/DashboardKpisTest.php`
- `/backend/tests/Feature/Api/HealthTest.php`
- `/backend/tests/Feature/Realtime/BatchEventBroadcastTest.php`
- `/backend/tests/Feature/Audit/ImmutableLogEntryTest.php`
- `/backend/tests/Feature/Secrets/SecretsManagerTest.php`

### Frontend — React 18.3+

- `/frontend/package.json`
- `/frontend/package-lock.json`
- `/frontend/vite.config.ts`
- `/frontend/vitest.config.ts`
- `/frontend/tsconfig.json`
- `/frontend/tsconfig.node.json`
- `/frontend/index.html`
- `/frontend/nginx.conf`
- `/frontend/.env.example`
- `/frontend/src/main.tsx`
- `/frontend/src/App.tsx`
- `/frontend/src/index.css`
- `/frontend/src/types/batch.ts`
- `/frontend/src/types/alert.ts`
- `/frontend/src/api/httpClient.ts`
- `/frontend/src/api/batches.ts`
- `/frontend/src/api/alerts.ts`
- `/frontend/src/api/lims.ts`
- `/frontend/src/api/reports.ts`
- `/frontend/src/auth/authStore.ts`
- `/frontend/src/auth/ProtectedRoute.tsx`
- `/frontend/src/realtime/echo.ts`
- `/frontend/src/hooks/useBatches.ts`
- `/frontend/src/hooks/useBatchDetail.ts`
- `/frontend/src/hooks/useBatchTraceability.ts`
- `/frontend/src/hooks/useAlerts.ts`
- `/frontend/src/hooks/useDashboardKpis.ts`
- `/frontend/src/hooks/useRealtimeEvents.ts`
- `/frontend/src/components/filters/CustomSelect.tsx`
- `/frontend/src/components/filters/BatchFilters.tsx`
- `/frontend/src/components/dashboard/KpiPanel.tsx`
- `/frontend/src/components/dashboard/BatchList.tsx`
- `/frontend/src/components/dashboard/BatchListItem.tsx`
- `/frontend/src/components/batches/BatchDetailPanel.tsx`
- `/frontend/src/components/batches/AdvancePhaseButton.tsx`
- `/frontend/src/components/batches/GatingBlockNotice.tsx`
- `/frontend/src/components/batches/ReceptionActions.tsx`
- `/frontend/src/components/alerts/AlertNotifier.tsx`
- `/frontend/src/components/alerts/AlertHistoryTable.tsx`
- `/frontend/src/components/reports/ExportTraceabilityReportButton.tsx`
- `/frontend/src/pages/DashboardPage.tsx`
- `/frontend/src/pages/BatchDetailPage.tsx`
- `/frontend/src/pages/AlertHistoryPage.tsx`
- `/frontend/src/test/setup.ts`
- `/frontend/src/components/filters/CustomSelect.test.tsx`
- `/frontend/src/components/dashboard/KpiPanel.test.tsx`
- `/frontend/src/components/batches/AdvancePhaseButton.test.tsx`
- `/frontend/src/components/alerts/AlertNotifier.test.tsx`
- `/frontend/src/hooks/useBatches.test.tsx`

## Contrato de API

Todos los endpoints de datos requieren `Authorization: Bearer <JWT>` y están bajo `auth:api`.

| Método | Ruta | Rol | Comportamiento |
|---|---|---|---|
| `POST` | `/api/v1/auth/token` | público | Emite el Bearer Token JWT |
| `GET` | `/api/v1/health` | público | Estado del servicio y de sus dependencias |
| `POST` | `/api/v1/batches` | Operador de Producción | Crea el lote en `Fase de cultivo` |
| `GET` | `/api/v1/batches` | todos | Listado con filtros y visibilidad por rol |
| `GET` | `/api/v1/batches/{batchId}` | todos | Detalle del lote con `status` de bloqueo |
| `POST` | `/api/v1/batches/{batchId}/transitions` | Operador de Producción | Avance secuencial de fase |
| `POST` | `/api/v1/batches/{batchId}/reception` | Coordinador de Logística | Recepción física |
| `POST` | `/api/v1/batches/{batchId}/logistics-close` | Coordinador de Logística | Cierre del ciclo logístico |
| `GET` | `/api/v1/batches/{batchId}/traceability` | todos | Trazabilidad cronológica del lote |
| `GET` | `/api/v1/batches/{batchId}/alerts` | todos | Alertas del lote |
| `GET` | `/api/v1/batches/{batchId}/gating` | todos | Estado del gating LIMS |
| `POST` | `/api/v1/batches/{batchId}/gating/release` | denegado a todos | Intento de forzar liberación, siempre rechazado |
| `POST` | `/api/v1/batches/{batchId}/lims-results` | Técnico de Control de Calidad | Registro de resultado microbiológico |
| `POST` | `/api/v1/lims/import` | Supervisor de Planta | Dispara la importación desde LIMS |
| `GET` | `/api/v1/alerts` | todos | Listado de alertas |
| `GET` | `/api/v1/alerts/history` | todos | Histórico de alertas |
| `GET` | `/api/v1/search/batches` | todos | Búsqueda avanzada paginada |
| `GET` | `/api/v1/dashboard/kpis` | todos | KPIs de tiempos de ciclo y estados |
| `GET` | `/api/v1/reports/traceability/{batchId}` | todos | Reporte de trazabilidad |
| `GET` | `/api/v1/reports/traceability/{batchId}.csv` | todos | Exportación CSV |
| `GET` | `/api/v1/reports/traceability/{batchId}.pdf` | todos | Exportación PDF |
| `GET` | `/api/v1/audit/logs` | Auditor / Administrador IT | Historial inmutable paginado |
| `GET` | `/api/v1/audit/logs/{uuid}` | Auditor / Administrador IT | Entrada de auditoría por UUID |

Canal WebSocket privado: `batch-monitoring`, autorizado con `auth:api`. Eventos: `batch.phase.advanced`, `batch.status.changed`, `alert.raised`.

## Riesgos / Trade-offs

- **[Riesgo] El contrato de esquema no contempla estado en `ALERT`, pero TC010 pide mostrar "estado" de la alerta** → Mitigación: se representa con `severity` (D6) y se documenta en la API; si en el futuro se necesita un ciclo de vida de alertas (acuse de recibo, cierre), se Planteará una revisión del contrato de esquema.
- **[Riesgo] La integración real con LIMS Labware no está disponible durante el desarrollo** → Mitigación: puerto `LimsLabwareClient` con doble de pruebas que reproduce respuestas reales (válidas, inconsistentes, con aprobación microbiológica), y contrato de payload documentado en `config/lims.php`.
- **[Riesgo] La búsqueda sobre `VACCINE_BATCH` con joins a `VACCINE_REFERENCE`, `ALERT` y `PHASE_TRANSITION` puede degradarse con volúmenes altos** → Mitigación: índices compuestos, caché Redis por hash de filtros y paginación obligatoria; la medición de P95 se realiza sobre el conjunto de datos de prueba representativo.
- **[Riesgo] Un único Dockerfile con varios *targets* es menos trivial de mantener que un Dockerfile por artefacto** → Mitigación: los *stages* están separados y documentados, y `docker-compose.yml` fija explícitamente el `target` de cada servicio.
- **[Riesgo] Reverb + Redis exige que la instancia que publica y la que recibe compartan Redis** → Mitigación: `docker-compose.yml` define un único Redis con red compartida y el despliegue documenta el clúster Redis para producción.
- **[Riesgo] Latencia de importación de LIMS si el LIMS responde lentamente** → Mitigación: importación por cola de trabajos con reintentos y sin bloqueo del canal de requests.
- **[Trade-off] El historial de auditoría se almacena en MySQL y crece sin poda** → Mitigación: particionado por `created_at` y retención acordada con calidad farmacéutica; el backup cubre también ese crecimiento.
- **[Trade-off] Los KPIs se calculan en el backend en cada consulta** → Mitigación: caché Redis con TTL corto; si el volumen lo justifica, el cálculo se moverá a una proyección asíncrona sin cambiar el contrato de la API.
- **[Trade-off] El `payload` JSON de `LOG_ENTRY` es de tipo libre** → Mitigación: el constructor de la entidad valida el esquema del payload por `event_type` antes de persistir.

## Migration Plan

No hay migración de datos: el proyecto nace vacío. El despliegue en el entorno de MSD sigue estos pasos:

1. `composer install --no-dev` y `npm ci && npm run build` sobre las imágenes construidas desde `/Dockerfile`.
2. `/backend/php artisan migrate --force` para crear el esquema del contrato y las tablas de infraestructura de Passport y de la cola.
3. `/backend/php artisan db:seed --class=DatabaseSeeder` para cargar los tres campus, las referencias de vacuna y los usuarios de los cinco roles, con contraseñas generadas y depositadas en el gestor de secretos.
4. Configuración de los secretos de producción (MySQL, Redis, LIMS Labware, notificadores, Vault) en el gestor de secretos antes de arrancar los contenedores; el despliegue de producción no monta ningún `.env`.
5. Arranque de `bff` (php-fpm, `queue:work`, Reverb) y `frontend` (nginx) con `/docker-compose.yml` en el ambiente correspondiente, escalando `bff` horizontalmente.
6. Ejecución de `/scripts/backup.sh` y validación de `/scripts/restore.sh` sobre un entorno limpio antes de abrir el servicio a los usuarios.

**Rollback.** Cada cambio de esquema se entrega como migración versionada; el rollback es `php artisan migrate:rollback --step=1` y el redeploy de la imagen anterior. Al no existir datos previos, no hay estrategia de backfill. El rollback de contenido (código) es volver a desplegar la etiqueta anterior del artefacto.

## Open Questions

- El nombre y el formato exacto del contrato de payload de LIMS Labware (endpoint, autenticación y esquema de respuesta) se confirmará con el equipo de integración de MSD; el puerto `LimsLabwareClient` y `config/lims.php` ya dejan el punto de adaptable acotado a un único fichero.
- El proveedor concreto de secretos (HashiCorp Vault u otro ya disponible en el entorno On-Premise de MSD) y sus rutas de secretos se configurarán en `config/secrets.php` una vez confirmado.
- El umbral numérico exacto de desviación térmica que dispara alerta se concertará con calidad; hasta entonces el detector trabaja con el umbral configurado en `config/monitoring.php` y es parametrizable por entorno.
- El canal SMS concreto (proveedor On-Premise) está pendiente de confirmación; la interfaz `AlertNotifier` y el notificador compuesto permiten incorporarlo sin cambios de dominio.
