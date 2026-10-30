# Spec Delta

## Purpose

Define los requisitos no funcionales de operación del sistema: capacidad de atender miles de transacciones por hora con baja latencia, respaldo y recuperación de los datos críticos de MySQL y Redis, etapa de análisis de seguridad en el pipeline CI/CD y disponibilidad total en el entorno de despliegue On-Premise o nube privada de MSD.

## ADDED Requirements

### Requirement: Capacidad de miles de transacciones por hora
El sistema SHALL soportar miles de transacciones por hora con tiempos de respuesta promedio inferiores a 2 segundos en el percentil 95, sin degradación significativa del rendimiento ni errores.

Criterios de aceptación: NFR002.

#### Scenario: Comportamiento bajo carga sostenida
- **WHEN** el sistema atiende una carga de miles de transacciones por hora
- **THEN** el percentil 95 de los tiempos de respuesta se mantiene por debajo de 2 segundos, sin errores y sin degradación significativa del rendimiento

#### Scenario: Estabilidad de las operaciones críticas
- **WHEN** la carga se concentra en operaciones críticas como el avance de fase y la importación de LIMS
- **THEN** el sistema mantiene los tiempos de respuesta dentro del umbral sin procesar operaciones de forma incorrecta ni duplicada

### Requirement: Respaldo y recuperación de datos críticos
MySQL y Redis SHALL contar con procesos de respaldo y de restauración que preserven la integridad y la consistencia de los datos críticos, garantizando la continuidad operativa.

Criterios de aceptación: NFR010.

#### Scenario: Respaldo de los datos críticos
- **WHEN** se ejecuta el proceso de respaldo de MySQL y de Redis
- **THEN** se generan copias de respaldo de ambos almacenes con los datos críticos íntegros y consistentes

#### Scenario: Restauración sobre un entorno limpio
- **WHEN** las copias de respaldo se restauran sobre un entorno vacío
- **THEN** los datos críticos quedan restaurados con su integridad y consistencia, y el sistema vuelve a operar con normalidad

### Requirement: Análisis de seguridad en el pipeline CI/CD
El pipeline CI/CD SHALL incluir una etapa de análisis de seguridad del código que detecte vulnerabilidades y bloquee el despliegue de código inseguro, con recorrido por los ambientes dev, staging y prod.

Criterios de aceptación: NFR011.

#### Scenario: Bloqueo del despliegue ante vulnerabilidades
- **WHEN** la etapa de análisis de seguridad detecta una vulnerabilidad en el código
- **THEN** el pipeline falla y el despliegue queda bloqueado, sin promoción del código a los ambientes posteriores

#### Scenario: Continuación cuando no hay vulnerabilidades
- **WHEN** el análisis de seguridad no detecta vulnerabilidades
- **THEN** el pipeline continúa con la promoción del código a los ambientes dev, staging y prod

### Requirement: Disponibilidad en el entorno On-Premise o nube privada de MSD
El sistema SHALL permanecer 100% disponible y accesible en el entorno de despliegue On-Premise o nube privada de MSD, sin interrupciones inesperadas y sin depender de servicios públicos de terceros.

Criterios de aceptación: NFR012.

#### Scenario: Operación en el entorno restringido de MSD
- **WHEN** el sistema se despliega y opera en el entorno On-Premise o nube privada de MSD
- **THEN** permanece accesible sin interrupciones inesperadas y todas sus dependencias críticas, incluidas MySQL, Redis, LIMS Labware y el gestor de secretos, se resuelven dentro de ese entorno

#### Scenario: Recuperación de un componente caído
- **WHEN** una instancia del BFF deja de estar disponible durante la operación
- **THEN** el sistema sigue atendiendo las peticiones y las actualizaciones en tiempo real gracias al escalado horizontal y a la orquestación mediante Redis
