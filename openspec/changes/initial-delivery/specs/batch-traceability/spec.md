# Spec Delta

## Purpose

Define la trazabilidad completa de un lote entre campus, el registro estructurado e inmutable de cada operación crítica de dominio con identificador UUID, la consulta del historial por el Auditor / Administrador IT y la generación y exportación de reportes de trazabilidad para auditorías regulatorias.

## ADDED Requirements

### Requirement: Detalle de trazabilidad completa por lote
El detalle de un lote SHALL mostrar la trazabilidad completa del lote entre campus, incluyendo todas sus transiciones de fase con from_phase, to_phase y transition_date, ordenadas cronológicamente.

Criterios de aceptación: TC003.

#### Scenario: Visualización de eventos en orden cronológico
- **WHEN** un usuario solicita el detalle de trazabilidad de un lote concreto
- **THEN** la respuesta incluye todos los eventos y cambios de estado del lote con su fecha y hora, ordenados cronológicamente

#### Scenario: Secuencia completa de campus recorrida
- **WHEN** el lote ha transitado entre Campus 1, formulación Sphereon y Campus 3
- **THEN** el detalle muestra la secuencia completa de campus recorrida, desde 'Fase de cultivo' en el Campus 1 hasta la fase alcanzada en el Campus 3

#### Scenario: Detalle de un lote sin historial
- **WHEN** se solicita el detalle de un lote recién creado sin transiciones de fase
- **THEN** la respuesta devuelve el lote con su phase, su status, su current_campus_id y una lista de transiciones vacía, sin error

### Requirement: Log estructurado inmutable de operaciones críticas
Cada operación crítica de dominio SHALL registrarse en LOG_ENTRY con id UUID v4/v7, batch_id, user_id, event_type, payload en JSON y created_at. Los registros SHALL ser estructurados e inmutables y no admitirán modificaciones posteriores.

Criterios de aceptación: TC026, NFR005.

#### Scenario: Registro de una operación crítica
- **WHEN** se ejecuta una operación crítica sobre un lote, como un alta, un avance de fase o una importación de LIMS
- **THEN** se persiste un LOG_ENTRY con id UUID v4/v7, batch_id, user_id, event_type de la operación, payload con los datos de la operación y created_at con la fecha y hora

#### Scenario: Rechazo de la modificación de un log
- **WHEN** se intenta actualizar o eliminar un LOG_ENTRY existente
- **THEN** la operación es rechazada, el registro permanece inmutable y el sistema no altera ni la carga útil ni la fecha de creación

### Requirement: Acceso completo al historial inmutable para Auditor / Administrador IT
El Auditor / Administrador IT SHALL tener acceso completo al historial inmutable de trazabilidad y a la auditoría de eventos asociados a las claves primarias UUID.

Criterios de aceptación: TC020.

#### Scenario: Consulta del historial completo
- **WHEN** el Auditor / Administrador IT consulta el historial de trazabilidad de un lote
- **THEN** la respuesta incluye la totalidad de los eventos registrados, con sus claves primarias UUID y su payload, sin ocultar ni omitir entradas

#### Scenario: Consulta de auditoría por UUID de evento
- **WHEN** el Auditor / Administrador IT solicita un evento de auditoría por su UUID
- **THEN** la API devuelve el evento completo con su event_type, su payload, su user_id y su created_at

### Requirement: Reporte de trazabilidad exportable
El sistema SHALL generar un reporte de trazabilidad por lote que incluya la trazabilidad completa del lote entre campus y SHALL permitir su exportación en un formato suitable para auditorías regulatorias.

Criterios de aceptación: TC011.

#### Scenario: Generación del reporte
- **WHEN** un usuario solicita el reporte de trazabilidad de un lote
- **THEN** el reporte incluye la trazabilidad completa del lote entre campus, con sus transiciones de fase, sus resultados de LIMS y sus alertas

#### Scenario: Exportación del reporte para auditoría
- **WHEN** el usuario exporta el reporte de trazabilidad de un lote
- **THEN** se descarga un archivo en un formato apta para auditorías regulatorias que contiene la trazabilidad completa del lote
