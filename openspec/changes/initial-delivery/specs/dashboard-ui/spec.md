# Spec Delta

## Purpose

Define el comportamiento visible del dashboard React: filtros construidos exclusivamente con el componente CustomSelect de Headless UI, indicadores clave que se actualizan en tiempo real, carga de KPIs por debajo de 3 segundos, bloqueo visual del avance por Gating LIMS y notificación visual de alertas críticas.

## ADDED Requirements

### Requirement: Filtros con el componente CustomSelect de Headless UI
Los filtros de la interfaz SHALL construirse estrictamente con el componente CustomSelect de Headless UI. Queda prohibido el uso de selectores nativos del navegador.

Criterios de aceptación: TC013.

#### Scenario: Desplegables de filtro basados en CustomSelect
- **WHEN** se renderiza el panel de filtros de lotes
- **THEN** cada control desplegable del panel es una instancia del componente CustomSelect de Headless UI

#### Scenario: Ausencia de selectores nativos
- **WHEN** se inspecciona el DOM del panel de filtros
- **THEN** no existe ningún elemento select nativo en el árbol de nodos

#### Scenario: Selección de una opción de filtro
- **WHEN** el usuario selecciona una opción en uno de los filtros
- **THEN** el valor seleccionado se aplica al filtrado de lotes y el listado se actualiza con el criterio elegido

### Requirement: Indicadores clave actualizados en tiempo real
Los indicadores clave del dashboard SHALL reflejar el nuevo estado o evento del lote en tiempo real, sin necesidad de recargar la página.

Criterios de aceptación: TC004.

#### Scenario: Actualización del KPI tras un evento de lote
- **WHEN** el backend emite un evento de cambio de estado o de generación de alerta de un lote
- **THEN** los indicadores clave del dashboard se actualizan con ese nuevo estado o evento sin que el usuario recargue la página

#### Scenario: Consistencia de los indicadores con el detalle del lote
- **WHEN** un indicador refleja un cambio de fase de un lote
- **THEN** el detalle de ese mismo lote muestra la fase y la ubicación coincidentes con el indicador actualizado

### Requirement: Carga de los KPIs del dashboard en menos de 3 segundos
El dashboard principal SHALL cargar y mostrar todos los KPIs de tiempos de ciclo y estados de lotes en menos de 3 segundos.

Criterios de aceptación: NFR001.

#### Scenario: Carga inicial del dashboard
- **WHEN** el usuario abre el dashboard principal
- **THEN** todos los KPIs de tiempos de ciclo y estados de lotes se muestran en menos de 3 segundos

#### Scenario: KPIs de tiempos de ciclo presentes
- **WHEN** el dashboard principal termina de cargar sus KPIs
- **THEN** el usuario visualiza los tiempos de ciclo por fase y el desglose de estados de lotes

### Requirement: Bloqueo visual del avance por Gating LIMS
El frontend SHALL reaccionar al estado de bloqueo por Gating LIMS publicado por la API, mostrando visualmente que el lote no puede avanzar, y SHALL NOT calcular ni validar por su cuenta la regla de negocio del gating.

Criterios de aceptación: TC024.

#### Scenario: Botón de avance deshabilitado
- **WHEN** la API devuelve un lote con el estado de bloqueo por Gating LIMS
- **THEN** el frontend muestra visualmente que el lote no puede avanzar, con el botón de avance deshabilitado y la indicación del bloqueo

#### Scenario: Ausencia de validación propia del gating
- **WHEN** el frontend procesa la respuesta de la API para un lote
- **THEN** no ejecuta ningún cálculo ni validación propia de la regla de gating y se limita a representar el estado devuelto por la API

#### Scenario: Habilitación del avance cuando no hay bloqueo
- **WHEN** la API devuelve un lote sin estado de bloqueo por Gating LIMS
- **THEN** el botón de avance del lote aparece habilitado

### Requirement: Notificación visual de alertas críticas en el dashboard
Las alertas críticas generadas por el sistema SHALL notificarse visualmente en el dashboard en tiempo real, sin recargar la página.

Criterios de aceptación: TC005, TC023.

#### Scenario: Aparición de la notificación visual
- **WHEN** el dashboard recibe por el canal de tiempo real una alerta crítica por desviación térmica o por anomalía Sphereon
- **THEN** la alerta se muestra visualmente en el dashboard de inmediato y sin que el usuario recargue la página

#### Scenario: Indicador de alertas no leídas
- **WHEN** existen alertas críticas pendientes de atender en el dashboard
- **THEN** el dashboard muestra un indicador visible con su número
