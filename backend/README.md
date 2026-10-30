# Dashboard de Monitorización del Ciclo de Vida de Vacunas — Backend

API del panel de monitorización del ciclo de vida de lotes de vacuna. El
servidor está construido con Laravel 11 sobre PHP 8.3 y aplica arquitectura
hexagonal: la capa de dominio no depende de nada externo, la capa de aplicación
es la única fuente de verdad de permisos y la infraestructura aísla Passport,
Eloquent, Redis, el gestor de secretos y los notificadores.

El despliegue es 100 % On-Premise o en nube privada de MSD. Las claves primarias
son UUID y el registro de auditoría es inmutable y de solo inserciones.

## Índice

- [Entorno](#entorno)
- [Puesta en marcha](#puesta-en-marcha)
- [Autenticación](#autenticación)
- [Roles y matriz de permisos](#roles-y-matriz-de-permisos)
- [Visibilidad acotada del Operador de Producción](#visibilidad-acotada-del-operador-de-producción)
- [Contrato de la API](#contrato-de-la-api)
- [Modelo de errores](#modelo-de-errores)
- [Auditoría de las peticiones de escritura](#auditoría-de-las-peticiones-de-escritura)
- [Gestión de secretos](#gestión-de-secretos)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Pruebas](#pruebas)
- [Verificación de aceptación](#verificación-de-aceptación)

## Entorno

| Pieza | Tecnología |
| --- | --- |
| Lenguaje | PHP 8.3 (`php:8.3-fpm-alpine`) |
| Framework | Laravel 11 |
| Base de datos | MySQL 8.0 |
| Caché y colas | Redis |
| Autenticación | Laravel Passport (tokens JWT, *password grant*) |
| Tiempo real | WebSockets (Laravel Reverb), canal privado `batch-monitoring` |
| Frontend | React 18.3, Axios, TanStack Query, Headless UI (`CustomSelect`) |

Las variables de entorno se declaran en `.env.example`, sin valores reales. En
producción las credenciales no sechedulean en ficheros: se resuelven desde el
gestor de secretos (véase [Gestión de secretos](#gestión-de-secretos)).

## Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan passport:install          # claves, migraciones de Passport y cliente inicial
php artisan db:seed                   # campus, referencias, cliente de contraseña y usuarios
php artisan serve
```

El sembrador `PassportClientSeeder` crea el cliente de contraseña que usa
`POST /api/v1/auth/token`. Es idempotente: puede ejecutarse en cada
despliegue sin duplicar clientes. La contraseña de los usuarios sembrados se
recibe por la variable `APP_USER_SEED_PASSWORD`, nunca por un fichero
versionado.

`php artisan passport:install` publica las migraciones de Passport
(`oauth_clients`, `oauth_access_tokens`, `oauth_refresh_tokens`,
`oauth_auth_codes` y `oauth_device_codes`) y genera las claves en
`storage/oauth-*.key`, que quedan fuera del control de versiones. Passport 13
no utiliza la tabla `personal_access_tokens`: los tokens personales se
almacenan en `oauth_access_tokens`, de modo que esa tabla no se crea.

Para el despliegue en contenedores, el `Dockerfile` de la raíz del proyecto y el
`docker-compose.yml` definen los servicios `backend`, `mysql`, `redis` y
`frontend`. La verificación de las imágenes es estática: no se ejecuta Docker
en el proceso de integración.

## Autenticación

Todas las rutas de datos exigen un token Bearer JWT emitido por Passport. El
panel obtiene el token en el único endpoint público de autenticación:

```http
POST /api/v1/auth/token
Content-Type: application/json

{
  "email": "operador@msd.example",
  "password": "..."
}
```

```json
{
  "token_type": "Bearer",
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "expires_in": 3600
}
```

El token se envía en la cabecera `Authorization`:

```http
Authorization: Bearer <access_token>
```

La vigencia del token se configura con `API_TOKEN_TTL_MINUTES` (60 minutos por
defecto). Las peticiones de escritura además se registran en el log de
auditoría, incluida la denegación por rol.

## Roles y matriz de permisos

El sistema contempla cinco roles. La matriz se aplica en
`App\Application\Authorization\OperationPolicy`, que es la única fuente de
verdad; el middleware `role:<operación>` de la API la aplica sobre el actor
autenticado.

| Rol | Producc. (crear lote, avanzar fase) | Logística (recepción, cierre) | Calidad (import LIMS, resultado, gating) | Lectura | Auditoría |
| --- | --- | --- | --- | --- | --- |
| Operador de Producción | Sí | Sí | No | Sí | No |
| Técnico de Control de Calidad | No | No | Sí | Sí | No |
| Coordinador de Logística | No | Sí | No | Sí | No |
| Supervisor de Planta | Sí | Sí | Sí | Sí | No |
| Auditor / Administrador IT | No | No | No | Sí | Sí |

Las lecturas no están restringidas por rol, salvo el historial de auditoría,
que queda reservado al rol de Auditor / Administrador IT. Una operación
prohibida al rol del actor responde `403` con el código
`operation_forbidden`; la denegación también queda registrada en el log de
auditoría.

## Visibilidad acotada del Operador de Producción

El Operador de Producción sólo visualiza los lotes que están en `Fase de
cultivo`. Los lotes en `Formulación Sphereon (Campus 2)` o en
`Fase logística (Campus 3)` no aparecen en `GET /api/v1/batches` y su detalle
responde `404` con el código `batch_not_found`, de modo que el panel no revela
la existencia de lotes fuera de su alcance. El acotamiento vive en
`App\Application\Authorization\BatchVisibilityPolicy`.

## Contrato de la API

Prefijo base: `/api/v1`. Todas las rutas de datos exigen `Authorization:
Bearer <token>`.

### Rutas públicas

| Método | Ruta | Descripción | Respuesta |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | Sondeo de disponibilidad de la aplicación, la base de datos y Redis. | `200` `{status, service, environment, timestamp, checks}` · `503` si una dependencia no responde. |
| POST | `/api/v1/auth/token` | Emisión del token Bearer a partir de las credenciales del usuario. | `200` `{token_type, access_token, expires_in}` · `401` credenciales incorrectas · `422` cuerpo incompleto. |

### Ciclo de vida del lote

| Método | Ruta | Rol | Descripción | Respuesta |
| --- | --- | --- | --- | --- |
| POST | `/api/v1/batches` | Operador, Supervisor | Alta de un lote de vacuna. | `201` lote creado · `403` rol no autorizado · `422` referencia inexistente o datos inválidos. |
| GET | `/api/v1/batches` | Todos | Listado de lotes acotado al alcance de visibilidad del rol. | `200` `{batches: [...]}` |
| GET | `/api/v1/batches/{batchId}` | Todos | Detalle del lote con resultados de LIMS y gating microbiológico. | `200` `{batch, lims_results, gating}` · `404` lote inexistente o fuera del alcance del rol. |
| GET | `/api/v1/batches/{batchId}/traceability` | Todos | Trazabilidad completa del lote: campus, transiciones, resultados, alertas y auditoría. | `200` `{batch_id, phase, status, current_campus_id, batch, campus_sequence, transitions, lims_results, alerts, entries}` · `404` lote inexistente. |
| POST | `/api/v1/batches/{batchId}/transitions` | Operador, Supervisor | Avance del lote a la siguiente fase del ciclo de vida. | `200` `{batch, transition}` · `403` rol no autorizado · `404` lote inexistente · `422` transición no secuencial o fase no válida. |
| POST | `/api/v1/batches/{batchId}/reception` | Operador, Coordinador, Supervisor | Registro de la recepción física del lote. | `200` lote actualizado · `403` rol no autorizado · `404` lote inexistente · `422` recepción no permitida en la fase actual. |
| POST | `/api/v1/batches/{batchId}/logistics-close` | Operador, Coordinador, Supervisor | Cierre del ciclo logístico del lote. | `200` lote actualizado · `403` rol no autorizado · `404` lote inexistente · `422` cierre no permitido en la fase actual. |

### Integración con LIMS y gating

| Método | Ruta | Rol | Descripción | Respuesta |
| --- | --- | --- | --- | --- |
| POST | `/api/v1/lims/import` | Técnico de Calidad, Supervisor | Importación de los resultados de LIMS Labware. | `200` `{summary: {imported_batches, imported_results, rejected_payloads, rejections}}` · `403` rol no autorizado · `422` carga útil rechazada. |
| POST | `/api/v1/batches/{batchId}/lims-results` | Técnico de Calidad, Supervisor | Registro de un resultado microbiológico del lote. | `201` resultado registrado · `403` rol no autorizado · `404` lote inexistente · `422` resultado incompleto. |
| GET | `/api/v1/batches/{batchId}/gating` | Todos | Estado del gating microbiológico del lote. | `200` `{gating}` · `404` lote inexistente. |
| POST | `/api/v1/batches/{batchId}/gating/release` | Técnico de Calidad, Supervisor | Liberación forzada del gating. | `200` gating liberado · `403` rol no autorizado o gating no aprobado (`gating_release_forbidden`) · `404` lote inexistente. |

### Alertas, búsqueda, indicadores y auditoría

| Método | Ruta | Rol | Descripción | Respuesta |
| --- | --- | --- | --- | --- |
| GET | `/api/v1/alerts` | Todos | Alertas operativas de prioridad máxima en curso. | `200` `{alerts: [...]}` |
| GET | `/api/v1/alerts/history` | Todos | Histórico de alertas resueltas. | `200` `{alerts: [...]}` |
| GET | `/api/v1/batches/{batchId}/alerts` | Todos | Alertas asociadas a un lote. | `200` `{alerts: [...]}` · `404` lote inexistente. |
| GET | `/api/v1/reports/traceability/{batchId}` | Todos | Reporte de trazabilidad en JSON. | `200` reporte · `404` lote inexistente. |
| GET | `/api/v1/reports/traceability/{batchId}.csv` | Todos | Reporte de trazabilidad en CSV. | `200` fichero CSV · `404` lote inexistente. |
| GET | `/api/v1/reports/traceability/{batchId}.pdf` | Todos | Reporte de trazabilidad en PDF. | `200` fichero PDF · `404` lote inexistente. |
| GET | `/api/v1/search/batches` | Todos | Búsqueda avanzada de lotes por familia, fase, alerta y fechas, con paginación. | `200` `{batches, total, page, per_page, last_page}` · `422` criterios inválidos. |
| GET | `/api/v1/dashboard/kpis` | Todos | Indicadores del panel: tiempos de ciclo por fase y desglose de estados. | `200` `{kpis}` |
| GET | `/api/v1/audit/logs` | Auditor / Administrador IT | Historial inmutable de auditoría, paginado. | `200` `{entries, pagination: {page, per_page, total}, total}` · `403` rol sin acceso al historial. |
| GET | `/api/v1/audit/logs/{uuid}` | Auditor / Administrador IT | Entrada concreta del historial de auditoría. | `200` entrada · `403` rol sin acceso al historial · `404` entrada inexistente. |

Los identificadores de lote son UUID. Las rutas de reporte acotan el
identificador a un UUID, de modo que el sufijo del formato no forme parte del
parámetro.

## Modelo de errores

Las respuestas de error son JSON con `message` y, cuando procede,
`error_code`:

```json
{
  "message": "La operación infringe una restricción de integridad de los datos.",
  "error_code": "integrity_constraint_violation"
}
```

| Código | Cuándo se produce | `error_code` de referencia |
| --- | --- | --- |
| `401` | Petición sin cabecera `Authorization`, con token caducado o con token inválido. | `Unauthenticated.` (marco de Laravel) |
| `401` | Credenciales incorrectas en `POST /api/v1/auth/token`. | `invalid_credentials` |
| `403` | El rol del actor no permite la operación solicitada. | `operation_forbidden` |
| `403` | Intento de forzar un gating no aprobado. | `gating_release_forbidden` |
| `403` | Acceso de un rol sin permisos al historial de auditoría. | `audit_trail_forbidden` |
| `404` | El recurso no existe o queda fuera del alcance de visibilidad del rol. | `batch_not_found`, `audit_entry_not_found` |
| `422` | Regla de negocio incumplida, validación de la entrada o violación de integridad. | `non_sequential_transition`, `invalid_batch_state`, `integrity_constraint_violation`, `immutable_log_entry` |
| `500` | Falta de configuración de la aplicación (por ejemplo, una credencial del gestor de secretos). | `configuration_error`, `missing_secret` |

Los errores de validación incluyen el detalle de los campos:

```json
{
  "message": "The email field is required.",
  "errors": { "email": ["El campo email es obligatorio."] }
}
```

Una violación de una restricción de la base de datos nunca produce un `500`: se
traduce a `422`.

## Auditoría de las peticiones de escritura

El middleware `audit.request` registra en `LOG_ENTRY` toda petición de
escritura, con el `user_id` del actor autenticado y el `event_type`
`api.write.requested`. La carga útil incluye `method`, `path` y `status`, de
modo que también quedan registradas las denegaciones por rol (403) y las
peticiones que terminan en error.

El registro es append-only: la base de datos rechaza cualquier `UPDATE` o
`DELETE` sobre la tabla con SQLSTATE `45000`, que la API traduce a `422` con
el código `immutable_log_entry`.

## Gestión de secretos

El puerto `App\Domain\Secrets\Port\SecretsManager` define el acceso a las
credenciales. La infraestructura ofrece el adaptador
`App\Infrastructure\Secrets\VaultSecretsManager`, que lee de un almacén KV de
HashiCorp Vault, y `App\Infrastructure\Secrets\SecretResolver`, que resuelve
las rutas declaradas en `config/secrets.php`:

| Clave | Contenido |
| --- | --- |
| `mysql.host`, `mysql.port`, `mysql.database`, `mysql.username`, `mysql.password` | Conexión de MySQL 8.0. |
| `redis.host`, `redis.port`, `redis.password` | Conexión de Redis. |
| `lims.base_url`, `lims.api_token` | Integración con LIMS Labware. |
| `notifiers.email.password`, `notifiers.sms.api_key` | Credenciales de los notificadores. |

Cuando `SECRETS_ENABLED=true`, el arranque de la aplicación valida que todas las
credenciales obligatorias estén disponibles. Si falta alguna, el arranque falla
con un error de configuración (`missing_secret`) que identifica la ruta
ausente y **nunca** incluye su valor.

## Estructura del proyecto

```
app/
├── Domain/              # Entidades, value objects, puertos y excepciones (sin dependencias)
│   ├── Audit/           # Log inmutable y tipos de evento
│   ├── Authentication/  # Puerto de emisión de tokens
│   ├── Secrets/         # Puerto del gestor de secretos
│   ├── Shared/          # Excepciones del dominio
│   ├── User/            # Usuario y roles
│   └── VaccineBatch/    # Lote de vacuna y fases del ciclo de vida
├── Application/         # Casos de uso y políticas
│   ├── Authorization/   # OperationPolicy, BatchVisibilityPolicy
│   └── ...              # Batches, Audit, LIMS, Alertas, Reportes, Búsqueda
├── Infrastructure/      # Adaptadores (Eloquent, Passport, Vault, Redis, PDF, notificadores)
└── Api/                 # Controllers, Requests, Resources, Middleware y rutas HTTP
```

Rutas HTTP: `routes/api.php` (contrato `/api/v1`) y `routes/channels.php`
(canal privado `batch-monitoring`, protegido con `auth:api`).

## Pruebas

```bash
php artisan test
```

La suite cubre el ciclo de vida del lote, la integración con LIMS, el gating,
las alertas, la trazabilidad, la auditoría, la búsqueda, los indicadores, la
autenticación, la autorización por rol y la resolución de secretos.

## Verificación de aceptación

Tres comandos de operación miden y verifican los requisitos no funcionales y
el perímetro del despliegue:

| Comando | Verifica |
| --- | --- |
| `php artisan ops:thresholds` | Los cuatro umbrales no funcionales: carga de los indicadores del panel (3 s), percentil 95 de la búsqueda avanzada (2 s), latencia de los eventos (1 s) y respuesta 401 de las rutas protegidas sin token. |
| `php artisan ops:verify-perimeter` | Que las únicas rutas públicas del despliegue son `GET /api/v1/health` y `POST /api/v1/auth/token`. |
| `php artisan security:gate` | Que el análisis de seguridad bloquea la promoción cuando la etapa falla o detecta vulnerabilidades. |

Los límites y las etapas se declaran en
[`backend/config/operations.php`](config/operations.php) y se pueden ajustar por
entorno (`OPERATIONS_DASHBOARD_THRESHOLD_SECONDS`,
`OPERATIONS_SEARCH_P95_THRESHOLD_SECONDS`,
`OPERATIONS_EVENT_LATENCY_THRESHOLD_SECONDS` y `SECURITY_SCAN_COMMAND`). Los
comandos aceptan `--json` para emitir el resultado en formato estructurado.
