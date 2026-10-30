# Spec Delta

## Purpose

Define que el proyecto se entrega containerizado, con una imagen Docker construida desde el repositorio y un fichero docker-compose.yml en la raíz que construye y ejecuta el conjunto de servicios del sistema.

## ADDED Requirements

### Requirement: Dockerfile y docker-compose.yml en la raíz del proyecto
El repositorio SHALL contener un único Dockerfile en su raíz que construya las imágenes de la aplicación a partir del código fuente del repositorio, y un docker-compose.yml en la raíz que construya y ejecute el proyecto. El docker-compose.yml SHALL definir los servicios del sistema, incluidos el BFF, el frontend, MySQL y Redis, con sus volúmenes y su red.

#### Scenario: Existencia de los ficheros de contenedores en la raíz
- **WHEN** se inspecciona la raíz del repositorio
- **THEN** existen un Dockerfile y un docker-compose.yml

#### Scenario: Coherencia de las rutas copiadas en la imagen
- **WHEN** se inspecciona el Dockerfile de la raíz
- **THEN** toda ruta declarada en una instrucción COPY corresponde a un archivo o directorio existente en el repositorio, y la imagen del servicio BFF se construye sobre la imagen base php:8.3-fpm-alpine

#### Scenario: Validez del fichero de composición
- **WHEN** se analiza el docker-compose.yml
- **THEN** el fichero es YAML válido y declara servicios para el BFF, el frontend, MySQL y Redis, con MySQL y Redis como dependencias del BFF, y con el BFF y el frontend construidos a partir del Dockerfile de la raíz

#### Scenario: Ejecución del proyecto en contenedores
- **WHEN** el entorno se levanta con el docker-compose.yml de la raíz
- **THEN** los servicios del proyecto quedan en ejecución y la API del BFF y el frontend quedan accesibles desde el exterior del host, sin que el sistema dependa de servicios externos al entorno
