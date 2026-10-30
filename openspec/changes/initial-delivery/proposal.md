# Proposal

## Why

La operación de vacunas está fragmentada entre tres campus (Campus 1, formulación Sphereon en Campus 2 y Campus 3) y entre sistemas heterogéneos (LIMS Labware, manufactura, logística), lo que genera puntos ciegos operativos, conciliaciones manuales y un elevado backlog técnico de IT sin trazabilidad transversal de lotes. Se necesita una plataforma unificada que dé trazabilidad de extremo a extremo, gating de calidad obligatorio antes del embalaje y alertas tempranas de prioridad máxima, ejecutada íntegramente dentro del entorno seguro On-Premise o nube privada de MSD.

## What Changes

- Se crea el proyecto desde cero: backend BFF en Laravel 11 con Arquitectura Hexagonal (Domain, Application, Infrastructure, API) y frontend en React 18.3+, sobre MySQL 8.0 (claves primarias UUID v4/v7) y Redis (caché, estados efímeros, WebSockets).
- Se modela el agregado `VaccineBatch` y las transiciones de fase secuenciales Campus 1 → Formulación Sphereon (Campus 2) → Campus 3 (Logística), con registro inmutable de cada transición.
- Se implementa la integración de solo lectura con LIMS Labware: importación automática y sin intervención manual, descarte de datos inconsistentes o inválidos, y registro del error de importación.
- Se implementa el Gating LIMS: un lote no puede avanzar a la fase logística sin aprobación microbiológica; el Técnico de Control de Calidad no puede forzar la liberación.
- Se generan automáticamente alertas de prioridad máxima por desviación térmica y por anomalía de formulación Sphereon, con historial consultable y notificación visual en tiempo real por WebSockets.
- Se expone una API REST protegida por `auth:api` con Bearer Tokens (JWT) emitidos por Laravel Passport, con autorización por rol (Operador de Producción, Técnico de Control de Calidad, Coordinador de Logística, Supervisor de Planta, Auditor / Administrador IT).
- Se entrega el dashboard React con TanStack Query, componente `CustomSelect` sobre Headless UI, detalle de trazabilidad cronológica, KPIs, filtrado por familia de producto / fase / estado de alerta y búsqueda avanzada.
- Se genera y exporta el reporte de trazabilidad por lote para auditorías regulatorias.
- Se registra un log estructurado inmutable por evento de dominio con UUID v4/v7, con origen e fecha de actualización de cada dato importado.
- Se consumen las credenciales de producción desde un gestor de secretos, sin archivos `.env` en producción.
- Se añaden Dockerfile y `docker-compose.yml` en la raíz para construir y ejecutar el proyecto.
- Se define el pipeline CI/CD con ambientes dev/staging/prod y etapa de análisis de seguridad que bloquea el despliegue de código inseguro.

## Capabilities

### New Capabilities
- `vaccine-batch-lifecycle`: creación del lote, máquina de fases secuenciales Campus 1 / Sphereon Campus 2 / Campus 3, transiciones registradas, sincronización de ubicación y cierre del ciclo logístico.
- `lims-gating`: importación automática de solo lectura desde LIMS Labware, validación y descarte de datos inválidos, trazabilidad de origen y fecha de actualización, gating microbiológico previo a la fase logística y prohibición de forzar la liberación.
- `batch-alerts`: generación automática de alertas de prioridad máxima por desviación térmica y anomalía Sphereon, severidad, notificación en tiempo real e historial de alertas consultable.
- `batch-traceability`: detalle de trazabilidad completa del lote entre campus con eventos en orden cronológico, log estructurado inmutable con UUID v4/v7 y generación/exportación del reporte de trazabilidad.
- `batch-search-filter`: filtrado por familia de producto, fase y estado de alerta, y búsqueda avanzada de lotes con P95 inferior a 2 segundos.
- `api-security`: autenticación JWT Bearer, middleware `auth:api`, respuesta 401 en token ausente o inválido, autorización por rol con denegación de operaciones fuera de perfil y consumo de credenciales desde gestor de secretos.
- `realtime-monitoring`: difusión de alertas críticas y cambios de estado por WebSockets orquestados con Redis, sincronización del frontend con TanStack Query, latencia inferior a 1 segundo y recuperación tras fallos de red.
- `dashboard-ui`: KPI del dashboard, `CustomSelect` de Headless UI para filtros, ausencia de lógica de negocio en el frontend, bloqueo visual del avance por gating y visibilidad de lotes según rol.
- `containerization`: imagen Docker del proyecto y `docker-compose.yml` de raíz que construyen y ejecutan el BFF, el frontend, MySQL y Redis.
- `operations-nonfunctional`: capacidad de miles de transacciones por hora, respaldo y recuperación de MySQL y Redis, etapa de análisis de seguridad en CI/CD y disponibilidad en despliegue On-Premise o nube privada de MSD.

### Modified Capabilities
<!-- Ninguna: el repositorio parte de cero y no hay specs existentes. -->

## Impact

- **Código nuevo**: proyecto completo desde cero; BFF Laravel 11 (Dominio, Aplicación, Infraestructura, API), frontend React 18.3+, migraciones y seeders MySQL 8.0.
- **APIs nuevas**: endpoints REST de lotes, transiciones de fase, resultados LIMS, gating, alertas, trazabilidad, reportes, búsqueda y autenticación; canal WebSocket de eventos de dominio.
- **Dependencias**: Laravel 11, Laravel Passport, Predis/Redis, React 18.3+, TanStack Query, Headless UI, Tailwind, Recharts, Docker.
- **Sistemas externos**: LIMS Labware (solo lectura), sistemas de manufactura y logística de Campus 1 y Campus 3, notificadores Email/SMS, gestor de secretos.
- **Infraestructura**: contenedores Docker, clúster Redis, escalado horizontal del BFF, pipeline CI/CD con ambientes dev, staging y prod.
- **Datos**: esquema con las entidades `VACCINE_REFERENCE`, `CAMPUS`, `VACCINE_BATCH`, `PHASE_TRANSITION`, `LIMS_RESULT`, `ALERT`, `LOG_ENTRY` y `APP_USER`.

## ACCEPTANCE CRITERIA

Los siguientes criterios provienen del plan de pruebas de QA y deben cumplirse íntegramente.

1. **TC001**: Verificar la importación automática y solo-lectura de datos de lotes desde LIMS. → Los resultados de LIMS se importan automáticamente sin intervención manual. La integración es de solo-lectura y no modifica datos en LIMS.
2. **TC002**: Confirmar que datos inconsistentes o inválidos de LIMS no se exponen en el sistema. → Los datos inconsistentes o inválidos se detectan y no se exponen. El lote no es visible en el dashboard y el sistema registra el intento de importación con un error.
3. **TC003**: Verificar la visualización de la trazabilidad completa y eventos de un lote específico. → El detalle muestra la trazabilidad completa del lote entre campus. Se visualizan los eventos y cambios de estado del lote en orden cronológico.
4. **TC004**: Validar que los indicadores clave del dashboard se actualizan en tiempo real. → Los indicadores se actualizan en tiempo real, reflejando el nuevo estado o evento del lote sin necesidad de recargar la página.
5. **TC005**: Comprobar la generación automática de alerta por desviación térmica. → Las desviaciones térmicas y anomalías Sphereon generan alertas de prioridad máxima. Las alertas se generan automáticamente sin intervención manual y se notifican visualmente en tiempo real.
6. **TC006**: Verificar la funcionalidad de filtrado y búsqueda avanzada de lotes. → El usuario puede filtrar por familia de producto, fase y estado de alerta. La búsqueda avanzada devuelve resultados relevantes en menos de 2 segundos (P95).
7. **TC007**: Validar que el acceso a las rutas protegidas de la API es denegado sin un token JWT válido. → La API devuelve un error de autenticación (ej. 401 Unauthorized) en ambos casos, indicando que la comunicación con el dashboard se restringe a Bearer Tokens (JWT).
8. **TC008**: Verificar que un lote no puede avanzar a la fase logística sin la aprobación del Gating LIMS. → La validación de que un lote biológico no pueda avanzar a su fase logística en el Campus 3 sin la aprobación del control microbiológico proveniente del sistema LIMS (Labware) debe verificarse.
9. **TC009**: Validar que los datos importados de LIMS conservan trazabilidad de origen y fecha de actualización. → Cada dato importado registra su origen (LIMS Labware) y fecha de actualización. El historial de cambios del lote es consultable y auditable, mostrando estos detalles.
10. **TC010**: Verificar la consulta del histórico de alertas por el responsable de calidad. → El histórico de alertas es consultable, mostrando todas las alertas generadas con sus detalles (tipo, lote afectado, fecha/hora, estado) para hacer seguimiento.
11. **TC011**: Validar la generación y exportación de reportes de trazabilidad por lote. → El reporte incluye la trazabilidad completa del lote entre campus. El reporte es exportable para auditorías regulatorias en un formato adecuado.
12. **TC012**: Verificar el cumplimiento de transiciones de fase secuenciales y obligatorias entre campus. → El sistema debe impedir el avance no secuencial, forzando el cumplimiento de las reglas de negocio clave para la monitorización del ciclo de vida de las vacunas, que incluyen transiciones de fase secuenciales.
13. **TC013**: Asegurar que los filtros de la interfaz utilizan el componente CustomSelect de Headless UI. → Los filtros de la interfaz utilizan estrictamente el componente CustomSelect de Headless UI, prohibiendo selectores nativos, según el criterio de aceptación.
14. **TC014**: Validar la sincronización de ubicación y avance de lotes entre Campus 1, Sphereon y Campus 3. → La ubicación y el avance secuencial de los lotes biológicos se sincronizan correctamente y se reflejan en el dashboard a medida que transitan entre Campus 1, formulación Sphereon y Campus 3.
15. **TC015**: Verificar que el Operador de Producción puede crear la entidad inicial de un lote (VaccineBatch). → El Operador de Producción tiene permisos para crear la entidad inicial del lote (VaccineBatch) y el lote aparece en el estado 'Fase de cultivo'.
16. **TC016**: Verificar que el Técnico de Control de Calidad no puede forzar la liberación de un lote (Gating LIMS). → El sistema deniega la acción y muestra un mensaje indicando que el Técnico de Control de Calidad tiene prohibido forzar la liberación de un lote (Gating) si el sistema no ha recibido la aprobación correspondiente.
17. **TC017**: Verificar que el Coordinador de Logística puede registrar la recepción física y el cierre del ciclo logístico. → El Coordinador de Logística puede registrar la recepción física y el cierre del ciclo logístico, actualizando el estado del lote correctamente.
18. **TC018**: Confirmar que el Coordinador de Logística no puede modificar datos de producción o calidad. → El sistema deniega el acceso a la funcionalidad de modificación y/o la petición API es rechazada, confirmando que el Coordinador de Logística tiene bloqueada cualquier modificación sobre los datos de producción o calidad.
19. **TC019**: Verificar que el Supervisor de Planta recibe alertas críticas en tiempo real mediante WebSockets. → El Supervisor de Planta recibe alertas críticas (desviaciones térmicas) en tiempo real mediante WebSockets, y la alerta se notifica visualmente en tiempo real.
20. **TC020**: Verificar que el Auditor / Administrador IT tiene acceso completo al historial inmutable de trazabilidad. → El Auditor / Administrador IT tiene acceso completo al historial inmutable de trazabilidad (logs estructurados) y auditoría de eventos asociados a las claves primarias UUID, mostrando el historial completo.
21. **TC021**: Confirmar que el Auditor / Administrador IT no puede alterar registros farmacéuticos o datos operativos. → El sistema deniega el acceso a las funcionalidades de modificación y/o la petición API es rechazada, confirmando que el Auditor / Administrador IT carece por completo de permisos para alterar registros.
22. **TC022**: Verificar el avance de lote a formulación Sphereon por Operador de Producción. → El Operador de Producción puede ejecutar el avance secuencial hacia la fase de formulación Sphereon, y el lote actualiza su estado a 'Formulación Sphereon (Campus 2)'.
23. **TC023**: Comprobar la generación automática de alerta por anomalía Sphereon. → Las anomalías Sphereon generan alertas de prioridad máxima. Las alertas se generan automáticamente sin intervención manual y se notifican visualmente en tiempo real al Supervisor de Planta.
24. **TC024**: Validar que el frontend muestra correctamente el estado de bloqueo de un lote por Gating LIMS. → El frontend en React reacciona a este estado de bloqueo sin calcular ni validar la regla de negocio autónomamente, mostrando visualmente que el lote no puede avanzar (ej. botón de avance deshabilitado).
25. **TC025**: Verificar la correcta sincronización de datos en el frontend utilizando TanStack Query. → El frontend, utilizando TanStack Query, asegura la correcta sincronización de datos, reflejando automáticamente los cambios de estado del lote provenientes del backend.
26. **TC026**: Validar que las operaciones críticas generan logs estructurados inmutables con UUIDs. → Se encuentran logs estructurados inmutables asociados a claves primarias UUID (v4/v7) para la operación realizada, garantizando la trazabilidad y auditoría.
27. **TC027**: Verificar que el Operador de Producción solo puede ver el estado y ubicación de lotes en fase de cultivo. → El Operador de Producción puede ver el estado y ubicación temporal de los lotes biológicos durante la fase de cultivo, pero no tiene visibilidad de lotes en fases o campus fuera de su alcance.
28. **TC028**: Validar que las credenciales de producción son consumidas desde un gestor de secretos. → Las credenciales de producción se consumen desde un gestor de secretos, sin exponer archivos .env en producción, mitigando el riesgo de fuga de credenciales.
29. **NFR001**: Verificar el tiempo de carga de los indicadores clave en el dashboard principal. → El dashboard principal carga y muestra todos los KPIs de tiempos de ciclo y estados de lotes en menos de 3 segundos.
30. **NFR002**: Evaluar la capacidad del sistema para manejar miles de transacciones concurrentes. → El sistema soporta miles de transacciones por hora con tiempos de respuesta promedio inferiores a 2 segundos (P95) y sin degradación significativa del rendimiento o errores.
31. **NFR003**: Validar la autenticación y autorización de la API mediante JWT y middleware. → 1. La solicitud es rechazada con un código de estado 401 Unauthorized. 2. La solicitud es rechazada con un código de estado 401 Unauthorized. 3. La solicitud es procesada exitosamente, devolviendo los datos esperados.
32. **NFR004**: Verificar la sincronización de estados de lotes entre campus ante fallos de red. → La sincronización de estados entre Campus 1, formulación Sphereon y Campus 3 se recupera automáticamente, y el estado del lote es consistente y correcto en el dashboard después de la reconexión, sin pérdida de datos.
33. **NFR005**: Asegurar la inmutabilidad y el formato de los logs estructurados para auditoría. → El log se registra con un UUID (v4/v7), es estructurado, inmutable y no permite modificaciones posteriores, cumpliendo con los requisitos de retención farmacéutica.
34. **NFR006**: Validar la latencia de las actualizaciones en tiempo real a través de WebSockets. → Las alertas críticas y cambios de estado se notifican visualmente en el dashboard en tiempo real, con una latencia inferior a 1 segundo desde que el evento ocurre en el origen.
35. **NFR007**: Verificar la correcta integración del gestor de secretos para credenciales de producción. → Las credenciales de producción (ej. para MySQL, Redis, LIMS) no están expuestas en archivos de configuración o variables de entorno, sino que son inyectadas de forma segura en tiempo de ejecución desde el gestor de secretos.
36. **NFR008**: Validar que la búsqueda avanzada de lotes devuelve resultados en menos de 2 segundos (P95). → La búsqueda avanzada devuelve resultados en menos de 2 segundos (P95).
37. **NFR009**: Evaluar la capacidad del sistema para manejar miles de conexiones WebSocket concurrentes. → El sistema mantiene las conexiones WebSocket activas y entrega las actualizaciones en tiempo real sin latencia perceptible (<1 segundo) y sin degradación del servicio para miles de usuarios concurrentes.
38. **NFR010**: Verificar la capacidad de respaldo y recuperación de datos críticos (MySQL, Redis). → Todos los datos críticos (MySQL, Redis) son respaldados y restaurados exitosamente, manteniendo su integridad y consistencia, asegurando la continuidad operativa.
39. **NFR011**: Confirmar la ejecución de análisis de seguridad del código en el pipeline CI/CD. → El pipeline CI/CD incluye una etapa de análisis de seguridad que detecta vulnerabilidades en el código y bloquea el despliegue de código inseguro, cumpliendo con la revisión de seguridad y calidad del código.
40. **NFR012**: Verificar la disponibilidad del sistema en el entorno de despliegue On-Premise o nube privada de MSD. → El sistema permanece 100% disponible y accesible en el entorno On-Premise o nube privada de MSD, sin interrupciones inesperadas, cumpliendo con la restricción de despliegue.
