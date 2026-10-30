# Spec Delta

## Purpose

Define la generación automática de alertas operativas de prioridad máxima por desviación térmica y por anomalía de formulación Sphereon, su notificación en tiempo real al Supervisor de Planta y la consulta del histórico de alertas por el responsable de calidad.

## ADDED Requirements

### Requirement: Generación automática de alertas de prioridad máxima
El sistema SHALL generar automáticamente, sin intervención manual, una ALERT por cada desviación térmica y por cada anomalía de formulación Sphereon detectada en un lote. Cada alerta SHALL registrar id UUID v4/v7, batch_id, alert_type, severity, message y created_at.

Criterios de aceptación: TC005, TC023.

#### Scenario: Alerta por desviación térmica
- **WHEN** el sistema detecta una desviación térmica sobre un lote biológico
- **THEN** se crea una ALERT con alert_type de desviación térmica, severity 'Crítica' (prioridad máxima), message que describe la desviación, batch_id del lote afectado y created_at con la fecha y hora de la detección

#### Scenario: Alerta por anomalía de formulación Sphereon
- **WHEN** el sistema detecta una anomalía de formulación Sphereon sobre un lote
- **THEN** se crea una ALERT con alert_type de anomalía Sphereon, severity 'Crítica' (prioridad máxima), message que describe la anomalía, batch_id del lote afectado y created_at con la fecha y hora de la detección

#### Scenario: Generación sin intervención manual
- **WHEN** ocurre una desviación térmica o una anomalía Sphereon y ningún usuario ha solicitado ninguna acción
- **THEN** la alerta queda persistida de forma automática, sin necesidad de que ninguna persona la cree ni la confirme

#### Scenario: Alertas de prioridad no máxima
- **WHEN** el sistema detecta una incidencia que no constituye desviación térmica ni anomalía Sphereon
- **THEN** la alerta generada, si la hay, no se registra con severity 'Crítica'

### Requirement: Notificación en tiempo real de alertas críticas
Las alertas de prioridad máxima SHALL notificarse en tiempo real al Supervisor de Planta, sin intervención manual y sin necesidad de recargar la página, y SHALL notificarse además por los canales de notificación de la organización.

Criterios de aceptación: TC019.

#### Scenario: Notificación visual en tiempo real al Supervisor de Planta
- **WHEN** se genera una alerta por desviación térmica o por anomalía Sphereon
- **THEN** el Supervisor de Planta conectado recibe la notificación en tiempo real y la alerta se muestra visualmente en el dashboard sin que deba recargar la página

#### Scenario: Notificación por los canales de la organización
- **WHEN** se genera una alerta de prioridad máxima
- **THEN** el sistema emite la notificación de la alerta por los canales de notificación configurados para el Supervisor de Planta

### Requirement: Histórico de alertas consultable
El histórico de alertas SHALL ser consultable y SHALL mostrar todas las alertas generadas con sus detalles: alert_type, batch_id del lote afectado, severity y estado de la alerta, message y created_at.

Criterios de aceptación: TC010.

#### Scenario: Consulta del histórico completo
- **WHEN** el responsable de calidad solicita el histórico de alertas
- **THEN** la respuesta incluye todas las alertas generadas con su tipo, el lote afectado, la severidad y estado, el mensaje y la fecha y hora de creación

#### Scenario: Histórico acotado a un lote
- **WHEN** el responsable de calidad solicita el histórico de alertas de un lote concreto
- **THEN** la respuesta incluye todas las alertas de ese lote con sus detalles, sin incluir alertas de otros lotes

#### Scenario: Histórico ordenado cronológicamente
- **WHEN** un lote acumula varias alertas en instantes distintos
- **THEN** el histórico las devuelve ordenadas cronológicamente por created_at
