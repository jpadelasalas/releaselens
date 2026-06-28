# syntax=docker/dockerfile:1

FROM node:20-alpine AS frontend

WORKDIR /app/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ARG VITE_API_URL=
ENV VITE_API_URL=${VITE_API_URL}
RUN npm run build

FROM composer:2 AS backend

WORKDIR /app/backend
COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts
COPY backend/ ./
RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-interaction

FROM php:8.3-apache-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        gosu \
        libcurl4-openssl-dev \
        libonig-dev \
        libpq-dev \
    && docker-php-ext-install -j"$(nproc)" curl mbstring opcache pcntl pdo_pgsql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
WORKDIR /var/www/html

COPY --from=backend /app/backend/ ./
COPY --from=frontend /app/frontend/dist/ ./public/
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/releaselens.ini
COPY docker/entrypoint.sh /usr/local/bin/releaselens-entrypoint

RUN chmod +x /usr/local/bin/releaselens-entrypoint \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["releaselens-entrypoint"]
CMD ["apache2-foreground"]
