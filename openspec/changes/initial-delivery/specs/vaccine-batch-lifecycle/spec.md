# Spec Delta

## Purpose

Define el ciclo de vida de un lote de vacuna biológico a través de los tres campus de MSD: alta en 'Fase de cultivo' en el Campus 1, avance secuencial a 'Formulación Sphereon (Campus 2)' y a 'Fase logística (Campus 3)', con registro inmutable de cada transición y sincronización de la ubicación vigente.

## ADDED Requirements

### Requirement: Alta de lote por el Operador de Producción
El Operador de Producción SHALL poder crear la entidad inicial VaccineBatch. El lote se persiste con clave primaria UUID v4/v7, phase 'Fase de cultivo', status 'En curso' y current_campus_id referencing el Campus 1.

Criterios de aceptación: TC015.

#### Scenario: Creación de lote en fase de cultivo
- **WHEN** el Operador de Producción solicita la creación de un lote para una VACCINE_REFERENCE existente
- **THEN** la API responde 201 con el lote persistido, uuid de clave primaria UUID v4/v7, phase 'Fase de cultivo', status 'En curso' y current_campus_id igual al UUID del Campus 1

#### Scenario: Creación con referencia de vacuna inexistente
- **WHEN** la creación de lote se solicita con un reference_id que no corresponde a ninguna VACCINE_REFERENCE
- **THEN** la API responde 422, no persiste ningún lote y no registra ninguna transición de fase

### Requirement: Transiciones de fase secuenciales y obligatorias
El sistema SHALL admitir únicamente las transiciones de fase secuenciales 'Fase de cultivo' → 'Formulación Sphereon (Campus 2)' → 'Fase logística (Campus 3)'. Cualquier avance a una fase no consecutiva o a un campus no visitable SHALL ser rechazado.

Criterios de aceptación: TC012, TC022.

#### Scenario: Avance secuencial válido a formulación Sphereon
- **WHEN** el Operador de Producción solicita avanzar un lote en phase 'Fase de cultivo' a la fase 'Formulación Sphereon (Campus 2)'
- **THEN** la API responde 200, el phase del lote pasa a 'Formulación Sphereon (Campus 2)' y el status permanece 'En curso'

#### Scenario: Avance no secuencial rechazado
- **WHEN** se solicita avanzar un lote en phase 'Fase de cultivo' directamente a la fase 'Fase logística (Campus 3)'
- **THEN** la API responde 422, el phase, el status y el current_campus_id del lote quedan sin cambios y la respuesta indica que la transición no es secuencial

#### Scenario: Retroceso de fase rechazado
- **WHEN** se solicita retroceder un lote en phase 'Formulación Sphereon (Campus 2)' a 'Fase de cultivo'
- **THEN** la API responde 422 y el lote conserva su phase, su status y su current_campus_id

### Requirement: Sincronización de ubicación y avance entre campus
Cada transición de fase SHALL actualizar el current_campus_id del lote para que refleje el campus que lo aloja en cada momento, de modo que el dashboard muestre la ubicación vigente del lote entre Campus 1, formulación Sphereon y Campus 3.

Criterios de aceptación: TC014.

#### Scenario: Actualización de ubicación al entrar en Campus 2
- **WHEN** un lote transita de 'Fase de cultivo' a 'Formulación Sphereon (Campus 2)'
- **THEN** el current_campus_id del lote pasa a ser el UUID del Campus 2 y el detalle del lote muestra la ubicación 'Formulación Sphereon (Campus 2)'

#### Scenario: Actualización de ubicación al entrar en Campus 3
- **WHEN** un lote transita de 'Formulación Sphereon (Campus 2)' a 'Fase logística (Campus 3)'
- **THEN** el current_campus_id del lote pasa a ser el UUID del Campus 3 y el detalle del lote muestra la ubicación 'Fase logística (Campus 3)'

### Requirement: Registro inmutable de las transiciones de fase
Cada transición de fase aplicada SHALL registrarse como una PHASE_TRANSITION con id UUID v4/v7, batch_id, from_phase, to_phase y transition_date. Los registros de transición SHALL ser inmutables.

Criterios de aceptación: TC003, NFR005.

#### Scenario: Registro de la transición ejecutada
- **WHEN** se aplica una transición de fase sobre un lote
- **THEN** se persiste una PHASE_TRANSITION con from_phase igual a la fase previa, to_phase igual a la fase alcanzada y transition_date con la fecha y hora de la transición

#### Scenario: Intento de modificación de una transición
- **WHEN** se intenta actualizar o eliminar una PHASE_TRANSITION existente
- **THEN** la operación es rechazada y el registro de transición permanece inmutable y consultable

### Requirement: Recepción física y cierre del ciclo logístico por el Coordinador de Logística
El Coordinador de Logística SHALL poder registrar la recepción física de un lote en el Campus 3 y, de forma independiente, el cierre del ciclo logístico, actualizando el status del lote de forma correcta en cada paso.

Criterios de aceptación: TC017.

#### Scenario: Registro de la recepción física
- **WHEN** el Coordinador de Logística registra la recepción física de un lote en phase 'Fase logística (Campus 3)'
- **THEN** el status del lote pasa a 'Recibido' y la acción queda registrada en el log estructurado de trazabilidad del lote

#### Scenario: Cierre del ciclo logístico
- **WHEN** el Coordinador de Logística cierra el ciclo logístico de un lote con status 'Recibido'
- **THEN** el status del lote pasa a 'Cerrado' y el current_campus_id permanece en el UUID del Campus 3

#### Scenario: Cierre logístico de un lote no recibido
- **WHEN** el Coordinador de Logística solicita el cierre del ciclo logístico de un lote cuyo status no es 'Recibido'
- **THEN** la API responde 422 y el status del lote permanece sin cambios
