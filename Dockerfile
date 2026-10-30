# syntax=docker/dockerfile:1

# Imagen única del monorepo con tres *targets* de construcción (D1):
#   frontend-builder -> compila el SPA React con Vite
#   frontend        -> sirve el SPA estático con nginx
#   bff             -> aplicación Laravel 11 con php-fpm, cola y Reverb

# ---------------------------------------------------------------------------
# Stage 1: construcción del SPA
# ---------------------------------------------------------------------------
FROM node:20-alpine AS frontend-builder

WORKDIR /app

# Configuración del SPA en tiempo de compilación.
ARG VITE_API_BASE_URL=/api/v1
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST=127.0.0.1
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http
# El SPA se compila en `site` dentro de la imagen y no en `dist`: así, ninguna
# regla de `.dockerignore` puede excluir la ruta que el stage `frontend` copia
# desde este constructor, y la regla que excluye el artefacto local
# `frontend/dist` del contexto de construcción sigue siendo aplicable.
ENV BUILD_OUT_DIR=site \
    VITE_API_BASE_URL=$VITE_API_BASE_URL \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: imagen estática del frontend
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS frontend

COPY frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend-builder /app/site /usr/share/nginx/html

EXPOSE 80

# ---------------------------------------------------------------------------
# Stage 3: BFF Laravel 11
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS bff

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    APP_ENV=production

# El trabajador de nginx pertenece al grupo `www-data` para poder leer los
# ficheros públicos del volumen `bff-storage` (propiedad de `www-data`).
RUN apk add --no-cache \
        bash \
        git \
        icu-libs \
        libzip \
        nginx \
        oniguruma \
        supervisor \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apk del --virtual .build-deps \
    && addgroup nginx www-data

COPY --from=composer:2.7-bin /composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/composer.json backend/composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/supervisor.conf /etc/supervisor/supervisord.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/php/nginx.conf /etc/nginx/http.d/default.conf
RUN chmod +x /usr/local/bin/entrypoint \
    && rm -f /etc/nginx/conf.d/default.conf \
    && nginx -t

EXPOSE 80 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
