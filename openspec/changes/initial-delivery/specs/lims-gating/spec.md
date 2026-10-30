# Spec Delta

## Purpose

Define la integración con el LIMS Labware: importación automática y de solo lectura de resultados de lotes, descarte de datos inconsistentes o inválidos, trazabilidad de origen y fecha de actualización de cada dato importado, y gating microbiológico obligatorio antes de habilitar la fase logística del Campus 3.

## ADDED Requirements

### Requirement: Importación automática y de solo lectura desde LIMS Labware
El sistema SHALL importar los resultados de lotes desde LIMS Labware automáticamente, sin intervención manual, registrándolos como LIMS_RESULT con id UUID v4/v7, batch_id, test_type, result, approved_by y result_date. La integración SHALL ser de solo lectura y no deberá modificar datos en LIMS Labware.

Criterios de aceptación: TC001.

#### Scenario: Importación automática de resultados
- **WHEN** el proceso de sincronización con LIMS Labware se ejecuta
- **THEN** los resultados de LIMS se importan automáticamente sin intervención manual y quedan registrados en LIMS_RESULT con batch_id, test_type, result, approved_by y result_date

#### Scenario: Integración sin escritura hacia LIMS Labware
- **WHEN** el sistema importa resultados desde LIMS Labware
- **THEN** ninguna escritura, actualización ni borrado es emitido contra LIMS Labware y los datos de origen permanecen sin modificar

### Requirement: Descarte de datos inconsistentes o inválidos de LIMS
El sistema SHALL validar los datos recibidos de LIMS Labware y SHALL exponer en el sistema únicamente los lotes con datos consistentes y válidos. Los lotes con datos inconsistentes o inválidos SHALL quedar no visibles en el dashboard y el intento de importación SHALL registrarse con un error.

Criterios de aceptación: TC002.

#### Scenario: Lote con datos inválidos no expuesto
- **WHEN** LIMS Labware devuelve un lote con datos inconsistentes o inválidos
- **THEN** el lote no queda visible en el dashboard ni en el listado de lotes y no se persiste como lote consultable

#### Scenario: Registro del error de importación
- **WHEN** la importación de un lote desde LIMS Labware falla por datos inconsistentes o inválidos
- **THEN** el sistema registra el intento de importación con un error en LOG_ENTRY, con event_type de importación fallida y el payload con el detalle del error, y la fecha y hora del intento

#### Scenario: Lote con datos válidos visible
- **WHEN** LIMS Labware devuelve un lote con datos consistentes y válidos
- **THEN** el lote queda importado y visible en el dashboard con su estado y su ubicación

### Requirement: Trazabilidad de origen y fecha de actualización de datos importados
Todo dato importado desde LIMS Labware SHALL registrar su origen y su fecha de actualización, y el historial de cambios del lote SHALL ser consultable y auditable mostrando ambos detalles.

Criterios de aceptación: TC009.

#### Scenario: Consulta del historial con origen y fecha
- **WHEN** un usuario consulta el historial de cambios de un lote con datos importados de LIMS Labware
- **THEN** el historial muestra, para cada dato importado, su origen 'LIMS Labware' y su fecha de actualización, junto con el evento y la fecha del cambio

#### Scenario: Auditoría del dato importado
- **WHEN** el Auditor / Administrador IT audita un registro con datos importados de LIMS Labware
- **THEN** el registro conserva el origen 'LIMS Labware' y la fecha de actualización del dato tal como fue recibido, sin modificaciones posteriores

### Requirement: Gating LIMS obligatorio antes de la fase logística
Un lote SHALL poder avanzar a la fase 'Fase logística (Campus 3)' únicamente cuando exista una LIMS_RESULT microbiológica con result 'Aprobado' y approved_by informado, procedente de LIMS Labware. En caso contrario, el avance SHALL ser rechazado y el status del lote SHALL quedar 'Bloqueado por Gating LIMS'.

Criterios de aceptación: TC008.

#### Scenario: Avance logístico bloqueado sin aprobación microbiológica
- **WHEN** se solicita avanzar a la fase 'Fase logística (Campus 3)' un lote cuya LIMS_RESULT microbiológica no está aprobada
- **THEN** la API responde 422, el lote no cambia de phase ni de current_campus_id y su status pasa a 'Bloqueado por Gating LIMS'

#### Scenario: Avance logístico permitido con aprobación microbiológica
- **WHEN** el lote dispone de una LIMS_RESULT microbiológica con result 'Aprobado' y approved_by informado
- **THEN** la API responde 200 al solicitar la fase 'Fase logística (Campus 3)', el phase del lote pasa a 'Fase logística (Campus 3)' y el current_campus_id pasa al UUID del Campus 3

#### Scenario: Bloqueo visible en la representación del lote
- **WHEN** la API devuelve el detalle de un lote con status 'Bloqueado por Gating LIMS'
- **THEN** la respuesta incluye el estado de bloqueo y la fase actual del lote, sin exigir al cliente ningún cálculo adicional

### Requirement: Prohibición de forzar la liberación de un lote
El Técnico de Control de Calidad SHALL tener prohibido forzar la liberación (Gating) de un lote cuando el sistema no ha recibido la aprobación microbiológica correspondiente de LIMS Labware. El sistema SHALL denegar la acción y SHALL informar del motivo.

Criterios de aceptación: TC016.

#### Scenario: Intento de forzar la liberación sin aprobación
- **WHEN** el Técnico de Control de Calidad intenta forzar la liberación de un lote sin aprobación microbiológica recibida de LIMS Labware
- **THEN** el sistema deniega la acción, no modifica el status ni el phase del lote y muestra un mensaje indicando que el Técnico de Control de Calidad tiene prohibido forzar la liberación de un lote (Gating) si el sistema no ha recibido la aprobación correspondiente
