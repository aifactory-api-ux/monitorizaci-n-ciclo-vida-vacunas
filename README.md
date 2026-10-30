# Dashboard de Monitorización del Ciclo de Vida de Vacunas

Panel de monitorización del ciclo de vida de los lotes de vacuna-tracking de
MSD. El sistema ingiere los resultados microbiológicos de LIMS Labware,
controla el gating de calidad, genera las alertas operativas de prioridad
máxima y ofrece la trazabilidad completa de cada lote.

El despliegue es 100 % On-Premise o en nube privada de MSD.

## Componentes

| Componente | Ruta | Descripción |
| --- | --- | --- |
| API | [`backend/`](backend/) | Laravel 11 sobre PHP 8.3, arquitectura hexagonal, tokens JWT con Passport, MySQL 8.0, Redis y WebSockets. |
| Panel | [`frontend/`](frontend/) | React 18.3 con Axios, TanStack Query y Headless UI (`CustomSelect`). |
| Contenedores | [`Dockerfile`](Dockerfile), [`docker-compose.yml`](docker-compose.yml), [`docker/`](docker/) | Imágenes de `bff`, `frontend`, `mysql` y `redis`. |

## Contrato UI/UX y tokens de diseño

El panel aplica el contrato UI/UX aprobado a través de los tokens de diseño, que
son la única fuente de verdad de la interfaz:

| Pieza | Ruta | Función |
| --- | --- | --- |
| Tokens | [`frontend/src/styles/tokens.ts`](frontend/src/styles/tokens.ts) | Objeto `tokens` con la paleta, la tipografía, la escala de espaciado, los radios, las sombras, la iconografía y el movimiento aprobados. |
| Tema | [`frontend/src/styles/theme.ts`](frontend/src/styles/theme.ts) | Traduce los tokens a un bloque `@theme` de Tailwind. |
| Inyección | [`frontend/vite.config.ts`](frontend/vite.config.ts) | Añade el tema a `src/index.css` antes de que Tailwind lo compile. |

Las utilidades resultantes son las que usa la interfaz: `bg-primary`,
`bg-surface`, `border-line`, `text-text-high`, `text-text-medium`,
`text-text-low`, `text-danger`, `text-warning`, `text-success`, `text-body-14`,
`text-label-12`, `text-metric-24`, `rounded-lg`, `shadow-card`, `shadow-overlay`,
`shadow-focus`, `gap-7` y la utilidad `numeric` para las cifras tabulares. Los
valores exactos están verificados por
[`frontend/src/styles/tokens.test.ts`](frontend/src/styles/tokens.test.ts).

## Documentación

- **[Contrato de la API](backend/README.md)**: endpoints, roles admitidos,
  códigos de estado y casos de error (401, 403, 404 y 422).
- [Especificación funcional](SPEC.md) y [plan de desarrollo](DEVELOPMENT_PLAN.md).
- [Cambio OpenSpec en curso](openspec/changes/initial-delivery/): propuesta,
  diseño, especificaciones y tareas.

## Despliegue On-Premise o en nube privada de MSD

El sistema se despliega íntegramente dentro del perímetro de MSD: no hay
llamadas a servicios públicos en tiempo de ejecución. Las imágenes se construyen
desde el [`Dockerfile`](Dockerfile) de la raíz y se orquestan con el
[`docker-compose.yml`](docker-compose.yml) de la raíz, que declara los cuatro
servicios del sistema con su red (`vaccine-net`) y sus volúmenes persistentes.

### Servicios del despliegue

| Servicio | Imagen | Origen | Puertos publicados | Función |
| --- | --- | --- | --- | --- |
| `bff` | `msd/vaccine-batch-tracking-bff:latest` | `Dockerfile`, target `bff` | `8081` → `80` (API HTTP) | API Laravel 11 servida por nginx sobre php-fpm, trabajador de cola y servidor WebSocket Reverb. |
| `frontend` | `msd/vaccine-batch-tracking-frontend:latest` | `Dockerfile`, target `frontend` | `8080` → `80` | SPA React servido por nginx, que reenvía `/api`, `/broadcasting` y `/app` al `bff`. |
| `mysql` | `mysql:8.0` | Imagen oficial | `3306` | Base de datos del dominio; persiste en el volumen `mysql-data`. |
| `redis` | `redis:7-alpine` | Imagen oficial | `6379` | Caché, colas y sesión; propaga los eventos de Reverb entre réplicas; persiste en el volumen `redis-data`. |

El `bff` declara `mysql` y `redis` como dependencias con espera al *healthcheck*,
de modo que la aplicación sólo arranca cuando ambas dependencias responden. El
volumen `bff-storage` conserva los registros del log de auditoría y las claves
de Passport entre despliegues.

Dentro del contenedor del `bff` cohabitan tres procesos supervisados: nginx
(puerto `80`, punto de entrada HTTP), php-fpm (puerto `9000`, protocolo FastCGI
acotado a `127.0.0.1`) y Reverb (puerto `8080`). El puerto `9000` no se publica
en el host: la API se expone por HTTP a través del puerto `80`.

### Secuencia de arranque

El arranque sigue el orden en que cada dependencia deja de bloquear al
servicio que la consume:

1. **`mysql`** — levanta el motor con [`docker/mysql/my.cnf`](docker/mysql/my.cnf),
   inicializa el esquema y la cuenta de aplicación, y publica su estado en el
   *healthcheck*.
2. **`redis`** — levanta `redis:7-alpine` con
   [`docker/redis/redis.conf`](docker/redis/redis.conf) (persistencia AppendOnly
   habilitada) y responde `PING` en el *healthcheck*.
3. **`bff`** — arranca nginx, php-fpm, `queue:work` y Reverb bajo supervisor;
   espera a MySQL, aplica las migraciones y genera las claves de Passport si no
   existen.
4. **`frontend`** — sirve el SPA compilado con nginx y reenvía `/api`,
   `/broadcasting` y `/app` al `bff`.

```bash
cp .env.example .env   # credenciales reales: las aporta el gestor de secretos
docker compose up -d mysql
docker compose up -d redis
docker compose up -d bff
docker compose up -d frontend
```

Con el despliegue en marcha, la API del BFF y el frontend quedan accesibles
desde el exterior del host:

| Recurso | URL |
| --- | --- |
| API del BFF | `http://localhost:8081/api/v1` |
| Frontend (SPA) | `http://localhost:8080` |
| API a través del frontend | `http://localhost:8080/api/v1` |

El API expone el contrato bajo el prefijo `/api/v1`. El sondeo de
disponibilidad (`GET /api/v1/health`) y la emisión del token
(`POST /api/v1/auth/token`) son públicos; el resto de rutas exigen un token
Bearer y las de escritura aplican además la autorización por rol.

### Escalado horizontal del BFF

El `bff` no guarda estado en el contenedor: los tokens son JWT firmados por
Passport, la sesión, la caché y las colas viven en Redis, y el almacenamiento
persistente está en el volumen `bff-storage`. Por eso se puede ejecutar en
varias réplicas detrás de un balanceador:

```bash
docker compose up -d --scale bff=3
```

Al publicar un puerto del host, Docker no puede reutilizarlo entre réplicas:
en la topología escalada el `BFF_HTTP_PORT` se retira del mapeo y el tráfico
entra por el `frontend`, cuyo nginx resuelve el servicio `bff` en tiempo de
ejecución por el DNS de Docker y reparte las peticiones entre las réplicas.

El canal WebSocket `batch-monitoring` se apoya en Reverb con broadcasting por
Redis: todas las réplicas reciben los eventos, de modo que el cliente mantiene
una única conexión WebSocket con la réplica que le asignó el balanceador, sin
necesidad de afinidad de sesión para el resto del tráfico. El despliegue fija
`BROADCAST_CONNECTION=reverb` (el servidor Reverb entrega los eventos a los
clientes) y `REVERB_SCALING_ENABLED=true` (Reverb los propaga por el canal
`reverb` de Redis), y ambos valores pueden ajustarse desde el `.env` del
despliegue. El balanceador frontal debe admitir la actualización de protocolo
(`Upgrade`/`Connection`) en la ruta `/broadcasting/` y un tiempo de espera
superior al de la ruta `/api`.

### Clúster de Redis

El perfil que entrega el `docker-compose.yml` es de un solo nodo: `redis`
(`redis:7-alpine`) con el fichero [`docker/redis/redis.conf`](docker/redis/redis.conf),
que habilita la persistencia AppendOnly (`appendonly yes` con
`appendfsync everysec`), conserva instantáneas RDB como red de seguridad ante
corrupción del AOF y aplica `maxmemory-policy noeviction` para que las colas
nunca pierdan entradas.

En MSD, el servicio Redis del despliegue de producción se despliega como un
clúster de nodos Redis 7 dentro de la nube privada, con el modo cluster
habilitado, tres maestros y una réplica por maestro, y el mismo fichero de
configuración por nodo. El BFF se conecta al clúster con el cliente `predis`
mediante `REDIS_HOST`, `REDIS_PORT` y `REDIS_PASSWORD`; para ello MSD publica
el punto de acceso del clúster con el nombre de servicio `redis` (o el
registro DNS equivalente) dentro de la red de despliegue, de modo que la
configuración del BFF no cambia al pasar del perfil de un nodo al clúster.

La operación del clúster se apoya en las mismas rutas de respaldo y
restauración del despliegue: `redis-cli --rdb` para la copia y `RESTORE` para
la recuperación. La conmutación por fallo de un maestro la resuelve la
promoción de su réplica, y `appendfsync everysec` limita la pérdida de datos a
la ventana de un segundo.

### Credenciales y secretos

El despliegue no monta ningún fichero `.env` dentro de los contenedores: las
credenciales de MySQL, Redis, LIMS y notificación se resuelven en tiempo de
ejecución desde el gestor de secretos ([`backend/config/secrets.php`](backend/config/secrets.php)).
Si falta una credencial obligatoria, la aplicación no arranca y lo comunica con
un error de configuración que no incluye ningún valor. Para el despliegue
contenedorizado, `docker-compose.yml` exige `APP_KEY`, `DB_USERNAME`,
`DB_PASSWORD`, `DB_ROOT_PASSWORD` y las credenciales de Reverb, y falla de
forma explícita si no están definidas.

### Política de rotación de credenciales

Las credenciales de producción se rotan en el gestor de secretos (HashiCorp
Vault sobre el motor KV v2, con las rutas canónicas declaradas en
[`backend/config/secrets.php`](backend/config/secrets.php)) y nunca en el
repositorio. La rotación sigue estas reglas:

| Credencial | Periodicidad | Procedimiento |
| --- | --- | --- |
| MySQL y Redis (usuarios de la aplicación) | Cada 90 días | El gestor emite una versión nueva de la credencial, el despliegue la entrega en el arranque y la versión anterior se revoca una vez comprobada la conexión. |
| Credenciales de LIMS Labware y de los notificadores | Cada 90 días | Igual que las anteriores, dentro de la misma ventana de mantenimiento. |
| Token de servicio de Vault | Cada 90 días, y de inmediato ante cualquier sospecha de compromiso | El token se renueva desde el fichero `VAULT_TOKEN_FILE`; la aplicación lo lee en el arranque y lo conserva sólo en memoria. |
| Claves de Passport | Cada 12 meses | La rotación genera un par de claves nuevo y conserva el anterior durante 30 días, de modo que los tokens ya emitidos siguen siendo válidos durante ese periodo. |
| `APP_KEY` | Cada 12 meses | Se regenera en el gestor de secretos; exige reiniciar las réplicas del BFF de forma escalonada. |

Ante la sospecha de un compromiso la rotación es inmediata y no espera a la
ventana programada. Ninguna versión de una credencial se escribe en el
repositorio ni en un fichero `.env` versionado: el despliegue recibe el valor
en tiempo de ejecución, los guiones de respaldo lo obtienen del gestor a
través del fichero de opciones de MySQL (`MYSQL_DEFAULTS_FILE`) o de la
variable de entorno que lo entrega, y lo eliminan al terminar.

## Estrategia de respaldo y restauración

Los datos críticos del sistema son los de **MySQL** (el dominio: lotes, fases,
resultados de LIMS, alertas y log de auditoría) y los de **Redis** (caché de
búsqueda, claves de sesión, colas de trabajo y estado de Reverb). Ambos
almacenes se respaldan y se restauran con procesos declarativos del propio
repositorio, que se ejecutan dentro del perímetro de MSD.

### Piezas del respaldo

| Pieza | Origen | Contenido |
| --- | --- | --- |
| `mysql/<base>.sql` | `mysqldump --single-transaction` | Volcado consistente de MySQL 8.0 con rutinas, disparadores y eventos. |
| `redis/dump.rdb` | `redis-cli --rdb` | Instantánea RDB de Redis 7, que sirve de entrada a la recuperación en frío. |
| `artifact/` | `php artisan backup:critical-data` | Manifiesto con los resúmenes SHA-256, las filas de las tablas críticas y la instantánea de las claves de Redis con su contenido y su tiempo de vida. |
| `manifest.sha256` | `sha256sum` | Resumen de integridad de cada fichero del respaldo. |
| `metadata.env` | Guión de respaldo | Parámetros de conexión del respaldo, sin credenciales. |

### Frecuencia

| Periodicidad | Alcance | Ejecución |
| --- | --- | --- |
| Diaria, a las 02:00 UTC | Copia completa de MySQL y Redis | [`scripts/backup.sh`](scripts/backup.sh) desde el planificador del despliegue |
| Antes de cada ventana de mantenimiento | Copia completa | El mismo guion |
| Antes de cada promoción a `staging` y a `prod` | Copia completa | El mismo guion, sobre el ambiente de destino |
| Tras cada aplicación de migraciones | Copia completa | El mismo guion, una vez aplicado el esquema |

La copia diaria fija el punto de recuperación objetivo en 24 horas; las copias
previas al mantenimiento y a las promociones acortan esa ventana en los
momentos en los que el dominio cambia. Cada ejecución purga los respaldos que
superan el periodo de conservación.

### Retención

| Elemento | Periodo de retención | Notas |
| --- | --- | --- |
| Respaldo en el nodo de despliegue | 30 días (`BACKUP_RETENTION_DAYS`) | La purga la ejecuta el propio guion de respaldo. |
| Réplica del respaldo en la nube privada de MSD | 12 meses | Copia fuera del nodo, para recuperar ante la pérdida del despliegue. |
| Log estructurado `LOG_ENTRY` (auditoría) | 7 años | Retención de los registros de calidad farmacéutica acordada con calidad. Las entradas son inmutables: no se actualizan ni se borran, y el respaldo diario cubre su crecimiento. |
| Filas del dominio (`VACCINE_BATCH`, `PHASE_TRANSITION`, `LIMS_RESULT`, `ALERT`) | 7 años, el mismo horizonte que el log | Las claves foráneas del log son restrictivas: la traza de auditoría no puede separarse de su lote. |
| Claves de Redis | No se retienen | Son estado derivado: se reconstruyen desde MySQL durante la restauración. |

### Restauración

[`scripts/restore.sh`](scripts/restore.sh) restituye un respaldo en tres
pasos:

1. **Verificación de la integridad**: compara los resúmenes SHA-256 de
   `manifest.sha256` con los de los ficheros y rechaza el respaldo si alguno
   falta o no coincide, sin tocar ningún destino.
2. **Reposición de MySQL**: aplica el volcado SQL con el cliente `mysql`.
3. **Reposición del dominio y de las claves**: restaura las filas de las
   tablas críticas y las claves de Redis desde el artefacto de la aplicación
   (`php artisan restore:critical-data`), que verifica sus propios resúmenes
   antes de aplicar nada.

La incorporación de filas es idempotente por identificador: las filas que ya
existen en el destino se conservan y no se borra ninguna, de modo que la
restauración es compatible con el log de auditoría inmutable y puede aplicarse
sobre un almacén limpio. El fichero `redis/dump.rdb` queda disponible para la
recuperación en frío, en la que se sustituye con él el volumen de datos de
Redis antes de iniciar el servicio.

La restauración se verifica mensualmente sobre un almacén limpio: se genera un
respaldo, se restaura y se comprueba que los datos críticos quedan íntegros y
que la API vuelve a operar con normalidad.

## Puesta en marcha local

### Toolchain

El entorno de desarrollo necesita PHP 8.3 con las extensiones `pdo_sqlite`,
`pdo_mysql`, `mbstring`, `openssl`, `curl`, `bcmath` y `zip`, además de Composer
2. El script [`scripts/setup-php83.sh`](scripts/setup-php83.sh) instala ambas
herramientas en el perfil del usuario sin necesitar un daemon de Docker, que no
está disponible en el entorno de verificación, y termina comprobando `php -v`,
`php -m` y `composer --version`:

```bash
./scripts/setup-php83.sh
```

El script es idempotente: si el interprete ya está instalado, sólo lo verifica.
La imagen `php:8.3-fpm-alpine` del despliegue (`Dockerfile`, target `bff`) es la
que usa el servicio en producción.

### Backend

Para el desarrollo local del backend sin contenedores:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan passport:install
php artisan db:seed
php artisan test
```

### Panel

```bash
cd frontend
npm ci
npm test
npm run build
```

## Seguridad

- Las credenciales de producción no se versionan: `.env` está ignorado por
  Git y los ficheros `.env.example` sólo contienen nombres de variables. El
  pipeline comprueba que ningún `.env` versionado declare credenciales
  ([`scripts/check-env-secrets.sh`](scripts/check-env-secrets.sh)).
- El pipeline analiza las vulnerabilidades de las dependencias PHP y Node
  ([`scripts/security-scan.sh`](scripts/security-scan.sh)). La etapa de
  seguridad se ejecuta antes de la promoción y su fallo impide promover a
  `staging` y a `prod`.
- Las claves primarias son UUID y el log de auditoría es inmutable
  (append-only).
- La autorización por rol se define en una única fuente de verdad y las
  peticiones de escritura quedan registradas en el historial de auditoría.

## Verificación de aceptación

### Matriz de trazabilidad

Cada criterio de aceptación de la [propuesta](openspec/changes/initial-delivery/proposal.md)
queda asociado al endpoint o a la función que lo implementa y a la prueba que lo
verifica. Las referencias de la columna de pruebas apuntan a ficheros de la
suite (`backend/tests/` y `frontend/src/`), de modo que cada criterio tiene una
cobertura ejecutable y ninguna referencia queda sin fichero o sin caso de
prueba.

| Criterio | Requisito verificable | Endpoint o función | Prueba que lo cubre |
| --- | --- | --- | --- |
| TC001 | Importación automática y de solo lectura desde LIMS | `POST /api/v1/lims/import` (`LimsController::import`), `App\Infrastructure\Lims\HttpLabwareLimsClient` | `backend/tests/Feature/Api/LimsImportTest.php::test_it_imports_the_lims_payloads_without_manual_intervention` |
| TC002 | Los datos inconsistentes no se exponen ni se persisten | `POST /api/v1/lims/import`, `App\Domain\Lims\Service\LimsPayloadValidator::validate` | `backend/tests/Feature/Api/LimsImportTest.php::test_it_rejects_an_inconsistent_payload_without_persisting_or_exposing_the_batch` |
| TC003 | Trazabilidad completa y eventos en orden cronológico | `GET /api/v1/batches/{batchId}/traceability` (`TraceabilityController::show`) | `backend/tests/Feature/Api/TraceabilityTest.php::test_it_exposes_the_complete_traceability_of_the_batch_between_campuses`<br>`frontend/src/components/batches/AdvancePhaseButton.test.tsx::ordena cronológicamente los eventos de trazabilidad del lote` |
| TC004 | Los indicadores se actualizan en tiempo real | `GET /api/v1/dashboard/kpis` (`DashboardController::index`), `frontend/src/hooks/useDashboardKpis.ts` | `frontend/src/components/dashboard/KpiPanel.test.tsx::vuelve a pintar los indicadores cuando llega un evento de tiempo real`<br>`backend/tests/Feature/Realtime/BatchEventBroadcastTest.php::test_el_avance_de_fase_difunde_el_evento_con_el_lote_y_el_campus` |
| TC005 | Alerta automática por desviación térmica | `App\Domain\Alert\Service\ThermalDeviationDetector::detect`, `GET /api/v1/alerts` | `backend/tests/Feature/Api/AlertHistoryTest.php::test_it_generates_the_critical_alert_automatically_without_any_user_request`<br>`backend/tests/Unit/Domain/Alert/ThermalDeviationDetectorTest.php::test_it_raises_an_alert_when_the_deviation_exceeds_the_threshold` |
| TC006 | Filtros por familia, fase y estado de alerta, con P95 inferior a 2 s | `GET /api/v1/search/batches` (`SearchController::index`) | `backend/tests/Feature/Api/BatchSearchTest.php::test_it_filters_the_batches_by_product_family`<br>`backend/tests/Feature/Api/BatchSearchTest.php::test_it_keeps_the_p95_of_the_advanced_search_below_two_seconds_over_a_representative_dataset` |
| TC007 | 401 en las rutas protegidas sin un token JWT válido | Middleware `auth:api` del grupo `api` | `backend/tests/Feature/Api/AuthenticationTest.php::test_data_routes_reject_requests_without_an_authorization_header`<br>`backend/tests/Feature/Api/ApiRouteProtectionTest.php::test_data_routes_reject_requests_with_an_invalid_bearer_token` |
| TC008 | Sin avance a la fase logística sin aprobación microbiológica | `POST /api/v1/batches/{batchId}/transitions`, `GET /api/v1/batches/{batchId}/gating` | `backend/tests/Feature/Api/GatingTest.php::test_it_blocks_the_advance_to_the_logistics_phase_without_microbiology_approval` |
| TC009 | Los datos importados conservan origen y fecha de actualización | `GET /api/v1/batches/{batchId}` (`BatchController::show`) | `backend/tests/Feature/Api/LimsImportTest.php::test_it_exposes_the_origin_and_the_update_date_of_the_imported_data` |
| TC010 | Histórico de alertas consultable y filtrable | `GET /api/v1/alerts/history` (`AlertController::history`) | `backend/tests/Feature/Api/AlertHistoryTest.php::test_it_returns_the_whole_history_and_filters_it_by_batch` |
| TC011 | Reporte de trazabilidad exportable | `GET /api/v1/reports/traceability/{batchId}.csv` y `GET /api/v1/reports/traceability/{batchId}.pdf` | `backend/tests/Feature/Api/ReportExportTest.php::test_it_exports_the_traceability_report_in_csv`<br>`backend/tests/Feature/Api/ReportExportTest.php::test_it_exports_the_traceability_report_in_pdf` |
| TC012 | Transiciones de fase secuenciales y obligatorias | `POST /api/v1/batches/{batchId}/transitions` (`PhaseTransitionController::store`) | `backend/tests/Feature/Api/BatchPhaseAdvanceTest.php::test_it_rejects_a_non_sequential_advance_without_mutating_the_batch` |
| TC013 | Filtros con el componente CustomSelect de Headless UI | `frontend/src/components/filters/CustomSelect.tsx` | `frontend/src/components/filters/CustomSelect.test.tsx::no renderiza ningún elemento select nativo` |
| TC014 | Sincronización de ubicación y avance entre Campus 1, Sphereon y Campus 3 | `POST /api/v1/batches/{batchId}/transitions`, `GET /api/v1/batches/{batchId}` | `backend/tests/Feature/Api/BatchPhaseAdvanceTest.php::test_it_advances_the_batch_to_the_sphereon_phase_and_relocates_it` |
| TC015 | El Operador de Producción crea el lote en fase de cultivo | `POST /api/v1/batches` (`BatchController::store`) | `backend/tests/Feature/Api/BatchCreationTest.php::test_it_creates_the_batch_in_culture_phase_at_campus_one` |
| TC016 | El Técnico de Control de Calidad no fuerza la liberación del lote | `POST /api/v1/batches/{batchId}/gating/release` (`GatingController::release`) | `backend/tests/Feature/Api/GatingTest.php::test_it_denies_the_forced_release_of_the_batch` |
| TC017 | Recepción física y cierre del ciclo logístico | `POST /api/v1/batches/{batchId}/reception`, `POST /api/v1/batches/{batchId}/logistics-close` | `backend/tests/Feature/Api/LogisticsCycleTest.php::test_it_registers_the_physical_reception_and_moves_the_batch_to_received`<br>`backend/tests/Feature/Api/LogisticsCycleTest.php::test_it_closes_the_logistics_cycle_and_keeps_the_campus` |
| TC018 | El Coordinador de Logística no modifica datos de producción ni de calidad | Middleware `role` de las rutas de escritura | `backend/tests/Feature/Api/AuthorizationTest.php::test_it_denies_the_logistics_coordinator_to_modify_the_production_data` |
| TC019 | Alertas críticas en tiempo real por WebSockets | Canal `batch-monitoring`, `POST /broadcasting/auth` | `backend/tests/Feature/Realtime/BatchEventBroadcastTest.php::test_la_alerta_difunde_el_evento_con_el_lote_afectado`<br>`frontend/src/components/alerts/AlertNotifier.test.tsx::notifica visualmente la alerta crítica recibida por el canal de tiempo real` |
| TC020 | El Auditor accede al historial inmutable de trazabilidad | `GET /api/v1/audit/logs` (`AuditController::index`) | `backend/tests/Feature/Api/TraceabilityTest.php::test_the_auditor_accesses_the_whole_audit_trail` |
| TC021 | El Auditor no altera registros farmacéuticos ni operativos | Middleware `role` de las rutas de escritura | `backend/tests/Feature/Api/AuthorizationTest.php::test_it_denies_the_auditor_to_alter_the_pharmaceutical_records` |
| TC022 | El Operador de Producción avanza a la formulación Sphereon | `POST /api/v1/batches/{batchId}/transitions` | `backend/tests/Feature/Api/BatchPhaseAdvanceTest.php::test_it_advances_the_batch_to_the_sphereon_phase_and_relocates_it` |
| TC023 | Alerta automática por anomalía Sphereon | `App\Domain\Alert\Service\SphereonAnomalyDetector::detect` | `backend/tests/Unit/Domain/Alert/SphereonAnomalyDetectorTest.php::test_it_raises_an_alert_when_the_anomaly_exceeds_the_threshold`<br>`backend/tests/Unit/Domain/Alert/AlertFactoryTest.php::test_it_creates_a_critical_sphereon_anomaly_alert` |
| TC024 | El frontend muestra el bloqueo por gating sin calcular la regla | `frontend/src/components/batches/AdvancePhaseButton.tsx` | `frontend/src/components/batches/AdvancePhaseButton.test.tsx::deshabilita el avance y muestra el aviso cuando la API devuelve el lote bloqueado por gating` |
| TC025 | Sincronización de datos con TanStack Query | `frontend/src/hooks/useBatches.ts` | `frontend/src/hooks/useBatches.test.tsx::invalida las consultas afectadas cuando llega un cambio de fase` |
| TC026 | Logs estructurados inmutables asociados a UUID | `App\Domain\Audit\Entity\LogEntry`, `App\Infrastructure\Persistence\Eloquent\Model\LogEntryModel` | `backend/tests/Feature/Audit/ImmutableLogEntryTest.php::test_it_records_the_critical_operation_with_its_identifier_and_payload` |
| TC027 | El Operador de Producción sólo ve lotes en fase de cultivo | `GET /api/v1/batches` (`BatchController::index`) | `backend/tests/Feature/Api/AuthorizationTest.php::test_it_limits_the_production_operator_to_the_cultivation_phase` |
| TC028 | Las credenciales de producción se consumen del gestor de secretos | `App\Infrastructure\Secrets\VaultSecretsManager`, `backend/config/secrets.php` | `backend/tests/Feature/Secrets/SecretsManagerTest.php::test_it_resolves_the_credentials_from_the_secrets_manager` |
| NFR001 | Los KPIs del panel se cargan en menos de 3 s | `GET /api/v1/dashboard/kpis`, `App\Application\Dashboard\GetDashboardKpis::execute` | `backend/tests/Feature/Operations/NonFunctionalThresholdsTest.php::test_it_measures_the_dashboard_load_below_three_seconds` |
| NFR002 | Miles de transacciones por hora sin errores ni duplicados | `App\Application\Search\BatchSearchService::execute` | `backend/tests/Feature/Api/BatchSearchTest.php::test_it_handles_thousands_of_transactions_per_hour_without_errors_or_duplicates` |
| NFR003 | 401 sin token, con token inválido o caducado; 200 con token válido | Middleware `auth:api` del grupo `api` | `backend/tests/Feature/Api/AuthenticationTest.php::test_data_routes_reject_requests_without_an_authorization_header`<br>`backend/tests/Feature/Api/AuthenticationTest.php::test_data_routes_accept_requests_with_a_valid_token`<br>`backend/tests/Feature/Operations/NonFunctionalThresholdsTest.php::test_it_measures_the_unauthorized_response_of_the_protected_routes_without_a_token` |
| NFR004 | El estado del lote se recupera tras un corte de red | `frontend/src/hooks/useBatches.ts` | `frontend/src/hooks/useBatches.test.tsx::recupera el estado vigente del lote tras un corte de red y una reconexión` |
| NFR005 | Log estructurado inmutable con UUID v4/v7 | `App\Domain\Audit\Entity\LogEntry` | `backend/tests/Feature/Audit/ImmutableLogEntryTest.php::test_it_rejects_the_modification_of_a_log_entry_and_keeps_it_intact`<br>`backend/tests/Feature/Audit/ImmutableLogEntryTest.php::test_the_database_rejects_the_update_of_a_log_entry` |
| NFR006 | Latencia de los eventos inferior a 1 s desde el origen | `App\Infrastructure\Realtime\RedisEventBroadcaster`, `App\Application\Operations\MeasureNonFunctionalThresholds::execute` | `backend/tests/Feature/Realtime/BatchEventBroadcastTest.php::test_la_latencia_entre_el_origen_del_evento_y_su_entrega_no_supera_un_segundo`<br>`backend/tests/Feature/Operations/NonFunctionalThresholdsTest.php::test_it_measures_the_latency_of_the_events_below_one_second` |
| NFR007 | Credenciales inyectadas en tiempo de ejecución desde el gestor de secretos | `App\Infrastructure\Secrets\SecretResolver` | `backend/tests/Feature/Secrets/SecretsManagerTest.php::test_it_resolves_the_credentials_from_the_secrets_manager`<br>`backend/tests/Feature/Secrets/SecretsManagerTest.php::test_it_fails_at_startup_when_a_required_credential_is_missing` |
| NFR008 | La búsqueda avanzada devuelve resultados en menos de 2 s (P95) | `GET /api/v1/search/batches` | `backend/tests/Feature/Api/BatchSearchTest.php::test_it_keeps_the_p95_of_the_advanced_search_below_two_seconds_over_a_representative_dataset`<br>`backend/tests/Feature/Operations/NonFunctionalThresholdsTest.php::test_it_measures_the_p95_of_the_advanced_search_below_two_seconds` |
| NFR009 | Miles de conexiones WebSocket con latencia inferior a 1 s | `App\Application\Realtime\MeasureRealtimeCapacity`, `App\Infrastructure\Realtime\ArraySubscriberRegistry` | `backend/tests/Feature/Realtime/RealtimeCapacityTest.php::test_it_delivers_the_update_to_thousands_of_concurrent_connections`<br>`backend/tests/Feature/Realtime/RealtimeCapacityTest.php::test_it_delivers_the_update_without_exceeding_the_latency_limit` |
| NFR010 | Respaldo y restauración de los datos críticos de MySQL y Redis | `php artisan backup:critical-data`, `php artisan restore:critical-data` | `backend/tests/Feature/Backup/CriticalDataBackupRestoreTest.php::test_it_restores_the_critical_data_on_a_clean_store_and_the_system_operates_again` |
| NFR011 | Una etapa de análisis de seguridad bloquea la promoción insegura | `App\Domain\Security\Service\SecurityGate::evaluate`, `php artisan security:gate` | `backend/tests/Feature/Security/SecurityGateTest.php::test_it_blocks_the_promotion_when_a_stage_detects_vulnerabilities`<br>`backend/tests/Feature/Security/SecurityGateTest.php::test_the_command_blocks_the_promotion_when_the_analysis_detects_vulnerabilities` |
| NFR012 | El despliegue permanece dentro del perímetro privado de MSD | `App\Domain\Operations\Service\PrivatePerimeterPolicy::evaluate`, `php artisan ops:verify-perimeter` | `backend/tests/Feature/Operations/PrivatePerimeterTest.php::test_it_publishes_every_data_route_with_the_token_requirement`<br>`backend/tests/Feature/Operations/PrivatePerimeterTest.php::test_it_exposes_only_the_health_probe_and_the_token_issuance` |

### Umbrales no funcionales medidos

Los cuatro umbrales exigidos se miden con `php artisan ops:thresholds`, que
ejecuta los casos de uso reales del API (indicadores del panel, búsqueda
avanzada, publicación en el canal de tiempo real y respuesta de una ruta
protegida sin token) y compara el resultado con los límites declarados en
[`backend/config/operations.php`](backend/config/operations.php).

| Criterio | Indicador | Medido | Umbral | Cumplimiento |
| --- | --- | --- | --- | --- |
| NFR001 | Carga de los indicadores del panel principal | 0,124 s | 3 s | Cumple |
| NFR008 | Percentil 95 de la búsqueda avanzada | 0,004 s | 2 s | Cumple |
| NFR006 | Latencia de publicación de los eventos en el canal | 0,003 s | 1 s | Cumple |
| NFR003 | Respuesta de las rutas protegidas sin token | 401 | 401 | Cumple |

La medición se ejecutó sobre el conjunto de datos representativo de 1.200
lotes, en el entorno de verificación local (PHP 8.3.32, SQLite y caché en
memoria, por no haber Redis ni MySQL disponibles en él); el despliegue de MSD
opera sobre MySQL 8.0 y Redis. La latencia de los eventos se contrasta además
de forma aislada en la suite, que mide el intervalo entre el origen del evento
y su entrega en el canal.

Las otras dos verificaciones de aceptación son ejecutables con el mismo
binario de Laravel:

```bash
cd backend
php artisan ops:thresholds        # umbrales no funcionales
php artisan ops:verify-perimeter  # rutas públicas del perímetro de MSD
php artisan security:gate         # bloqueo de la promoción ante hallazgos
```

`ops:verify-perimeter` declara como públicas únicamente `GET /api/v1/health` y
`POST /api/v1/auth/token`: el resto de las rutas del API exige token Bearer.
`security:gate` ejecuta el comando de análisis configurado en
`SECURITY_SCAN_COMMAND` y bloquea la promoción cuando la etapa falla o
detecta vulnerabilidades; sin comando configurado la etapa falla y la
promoción queda bloqueada.

## Pruebas

```bash
cd backend && php artisan test      # Suite del API
cd frontend && npm test             # Suite del panel
```
