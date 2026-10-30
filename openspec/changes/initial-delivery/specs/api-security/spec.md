# Spec Delta

## Purpose

Define la autenticación de la API por Bearer Tokens JWT emitidos por Laravel Passport, la autorización por rol que impedeiera operaciones fuera del perfil de cada usuario, el acotamiento de la visibilidad del Operador de Producción y el consumo de las credenciales de producción desde un gestor de secretos.

## ADDED Requirements

### Requirement: Autenticación por Bearer Token JWT en rutas protegidas
Todas las rutas de la API que exponen datos de lotes SHALL exigir un Bearer Token JWT válido emitido por el proveedor de autenticación de la aplicación. Una petición sin token o con token inválido o expirado SHALL ser rechazada con 401 Unauthorized.

Criterios de aceptación: TC007, NFR003.

#### Scenario: Petición sin cabecera de autorización
- **WHEN** una petición a una ruta protegida de la API se realiza sin cabecera Authorization
- **THEN** la API responde con el código de estado 401 Unauthorized e indica que la comunicación con el dashboard se restringe a Bearer Tokens (JWT)

#### Scenario: Petición con token inválido o expirado
- **WHEN** una petición a una ruta protegida de la API se realiza con un Bearer Token JWT inválido o expirado
- **THEN** la API responde con el código de estado 401 Unauthorized

#### Scenario: Petición con token válido
- **WHEN** una petición a una ruta protegida de la API se realiza con un Bearer Token JWT válido
- **THEN** la petición se procesa exitosamente y la API devuelve los datos esperados

### Requirement: Autorización por rol con denegación de operaciones fuera de perfil
La API SHALL denegar toda operación que exceda el perfil del usuario que la ejecuta. El Coordinador de Logística SHALL tener bloqueada cualquier modificación sobre datos de producción o calidad, y el Auditor / Administrador IT SHALL carecer por completo de permisos para alterar registros farmacéuticos o datos operativos.

Criterios de aceptación: TC018, TC021.

#### Scenario: Coordinador de Logística modifica datos de producción
- **WHEN** el Coordinador de Logística intenta modificar datos de producción o de calidad
- **THEN** el sistema deniega el acceso a la funcionalidad de modificación y rechaza la petición API, sin alterar ningún dato

#### Scenario: Auditor / Administrador IT altera registros farmacéuticos
- **WHEN** el Auditor / Administrador IT intenta alterar registros farmacéuticos o datos operativos
- **THEN** el sistema deniega el acceso a las funcionalidades de modificación y rechaza la petición API, sin alterar ningún registro

#### Scenario: Operaciones permitidas dentro del perfil
- **WHEN** el Técnico de Control de Calidad registra un resultado microbiológico de un lote dentro de su perfil
- **THEN** la API procesa la operación y el resultado queda registrado como LIMS_RESULT asociada al lote

### Requirement: Visibilidad acotada del Operador de Producción a los lotes en fase de cultivo
El Operador de Producción SHALL ver el estado y la ubicación de los lotes únicamente mientras se encuentran en 'Fase de cultivo', y SHALL quedar sin visibilidad sobre los lotes en fases o campus ajenos a su alcance.

Criterios de aceptación: TC027.

#### Scenario: Listado acotado a la fase de cultivo
- **WHEN** el Operador de Producción lista los lotes
- **THEN** la API devuelve el estado y la ubicación de los lotes en 'Fase de cultivo'

#### Scenario: Ocultación de lotes fuera de alcance
- **WHEN** existen lotes en 'Formulación Sphereon (Campus 2)' o en 'Fase logística (Campus 3)'
- **THEN** el Operador de Producción no los ve en el listado, y la API no los devuelve en la respuesta

#### Scenario: Detalle de un lote fuera de alcance
- **WHEN** el Operador de Producción solicita por identificador un lote que no está en 'Fase de cultivo'
- **THEN** la API responde con 404 y no expone ningún dato del lote

### Requirement: Credenciales de producción desde gestor de secretos
Las credenciales de producción, incluidas las de MySQL, Redis y LIMS Labware, SHALL consumirse desde un gestor de secretos e inyectarse de forma segura en tiempo de ejecución. La aplicación SHALL NOT exponer archivos .env con credenciales en producción ni secretos en el código, en la configuración o en los logs.

Criterios de aceptación: TC028, NFR007.

#### Scenario: Arranque en producción con secretos inyectados
- **WHEN** el servicio arranca en el entorno de producción
- **THEN** obtiene las credenciales de MySQL, Redis y LIMS Labware desde el gestor de secretos, inyectadas en tiempo de ejecución, sin leerlas de un archivo .env

#### Scenario: Ausencia de credenciales en el repositorio
- **WHEN** se inspecciona el repositorio y la imagen de producción
- **THEN** no existe ningún archivo .env con credenciales de producción ni ninguna credencial escrita en el código, en la configuración o en los logs

#### Scenario: Credenciales ausentes en el gestor de secretos
- **WHEN** el gestor de secretos no devuelve una credencial requerida en el arranque
- **THEN** el arranque falla de forma explícita con un error de configuración, sin exponer el valor de la credencial en el mensaje
