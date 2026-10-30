# Spec Delta

## Purpose

Define el filtrado de lotes por familia de producto, fase y estado de alerta, y la búsqueda avanzada de lotes con resultados relevantes y latencia P95 inferior a 2 segundos.

## ADDED Requirements

### Requirement: Filtrado de lotes por familia de producto, fase y estado de alerta
El usuario SHALL poder filtrar los lotes por familia de producto, por fase y por estado de alerta, y SHALL poder combinar esos criterios de forma simultánea.

Criterios de aceptación: TC006.

#### Scenario: Filtro por familia de producto
- **WHEN** el usuario aplica el filtro de familia de producto sobre una familia concreta
- **THEN** el listado devuelve únicamente los lotes pertenecientes a esa familia de producto

#### Scenario: Filtro por fase
- **WHEN** el usuario aplica el filtro de fase 'Formulación Sphereon (Campus 2)'
- **THEN** el listado devuelve únicamente los lotes que se encuentran en esa fase

#### Scenario: Filtro por estado de alerta
- **WHEN** el usuario aplica el filtro de estado de alerta de alerta crítica activa
- **THEN** el listado devuelve únicamente los lotes con al menos una alerta crítica vigente

#### Scenario: Combinación de filtros
- **WHEN** el usuario aplica simultáneamente el filtro de familia de producto, el de fase y el de estado de alerta
- **THEN** el listado devuelve únicamente los lotes que satisfacen los tres criterios a la vez

#### Scenario: Filtro sin coincidencias
- **WHEN** la combinación de filtros aplicada no admite ningún lote
- **THEN** el listado devuelve un conjunto vacío y la API responde 200, sin error

### Requirement: Búsqueda avanzada de lotes con P95 inferior a 2 segundos
La búsqueda avanzada de lotes SHALL devolver resultados relevantes para los criterios introducidos y SHALL responder en menos de 2 segundos en el percentil 95.

Criterios de aceptación: TC006, NFR008.

#### Scenario: Resultados relevantes de la búsqueda avanzada
- **WHEN** el usuario introduce criterios de búsqueda avanzada sobre lotes
- **THEN** la API devuelve los lotes que coinciden con los criterios, con la información necesaria para distinguirlos entre sí

#### Scenario: Latencia P95 de la búsqueda avanzada
- **WHEN** se mide la latencia de la búsqueda avanzada sobre el conjunto de datos de prueba representativo
- **THEN** el percentil 95 de los tiempos de respuesta es inferior a 2 segundos

#### Scenario: Búsqueda avanzada paginada
- **WHEN** el usuario solicita una página concreta de resultados de la búsqueda avanzada
- **THEN** la API devuelve esa página de resultados junto con el total de coincidencias, manteniendo el orden por fecha de creación del lote
