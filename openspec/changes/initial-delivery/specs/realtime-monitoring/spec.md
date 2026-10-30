# Spec Delta

## Purpose

Define la difusión en tiempo real de alertas críticas y cambios de estado de lote mediante WebSockets orquestados con Redis, la sincronización de esos cambios en el frontend mediante TanStack Query, la recuperación automática tras fallos de red y la capacidad de atender miles de conexiones concurrentes.

## ADDED Requirements

### Requirement: Emisión de eventos de dominio por WebSockets
El sistema SHALL emitir por WebSockets, con soporte de orquestación mediante Redis, los eventos de alerta crítica y de cambio de estado de lote, con una latencia inferior a 1 segundo desde que el evento ocurre en el origen.

Criterios de aceptación: TC019, NFR006.

#### Scenario: Evento de alerta crítica en tiempo real
- **WHEN** se genera una alerta crítica por desviación térmica
- **THEN** el Supervisor de Planta conectado recibe el evento por WebSocket con una latencia inferior a 1 segundo respecto al momento de la detección

#### Scenario: Evento de cambio de estado en tiempo real
- **WHEN** un lote cambia de fase o de status
- **THEN** el evento de cambio de estado se emite por WebSocket y llega a los clientes suscritos en menos de 1 segundo

#### Scenario: Despliegue horizontal sin pérdida de eventos
- **WHEN** el sistema opera con más de una instancia del BFF
- **THEN** un evento emitido en una instancia llega a los clientes conectados a las demás instancias gracias a la orquestación mediante Redis

### Requirement: Sincronización del frontend mediante TanStack Query
El frontend SHALL sincronizar los datos del backend mediante TanStack Query, de modo que los cambios de estado de lote provenientes del backend se reflejen automáticamente en la caché y en la vista sin recargar la página.

Criterios de aceptación: TC025, TC004.

#### Scenario: Reflejo automático del cambio de estado
- **WHEN** llega por WebSocket un evento de cambio de estado de un lote
- **THEN** TanStack Query actualiza la caché de lotes y la vista muestra el nuevo estado del lote sin que el usuario recargue la página

#### Scenario: Sincronización de los indicadores
- **WHEN** llega por WebSocket un evento que altera los indicadores del dashboard
- **THEN** TanStack Query invalida y vuelve a obtener los datos de los indicadores, que quedan reflejando el nuevo estado

### Requirement: Recuperación automática tras fallos de red
Tras una interrupción de red, la sincronización de estados entre Campus 1, formulación Sphereon y Campus 3 SHALL recuperarse automáticamente, y el estado de cada lote SHALL ser consistente y correcto en el dashboard tras la reconexión, sin pérdida de datos.

Criterios de aceptación: NFR004.

#### Scenario: Reconexión automática del canal
- **WHEN** se pierde la conexión de red con el servidor de WebSockets
- **THEN** el cliente intenta reconectar automáticamente y restablece el canal en cuanto la red vuelve, sin intervención del usuario

#### Scenario: Consistencia del estado tras la reconexión
- **WHEN** el cliente se reconecta tras un corte de red
- **THEN** el dashboard muestra el estado del lote correcto y coincidente con el backend, sin pérdida de eventos ni de datos

#### Scenario: Eventos ocurridos durante el corte
- **WHEN** se produce un cambio de estado de un lote mientras el cliente está desconectado
- **THEN** tras la reconexión el cliente recibe ese cambio y el dashboard refleja el estado actualizado del lote

### Requirement: Capacidad de miles de conexiones WebSocket concurrentes
El sistema SHALL mantener miles de conexiones WebSocket concurrentes activas y SHALL entregar las actualizaciones en tiempo real con una latencia inferior a 1 segundo y sin degradación del servicio.

Criterios de aceptación: NFR009.

#### Scenario: Carga de miles de conexiones
- **WHEN** miles de clientes mantienen simultáneamente conexiones WebSocket activas y se producen cambios de estado y alertas
- **THEN** el sistema mantiene las conexiones activas y entrega las actualizaciones en menos de 1 segundo, sin degradación perceptible del servicio
