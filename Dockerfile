# StudyQuest teacher-side Laravel web service for Render.
# Requires package-lock.json and a Laravel/Vite frontend that outputs public/build.
# Match the PHP version to your composer.json / composer.lock requirements.
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-fpm-bookworm
ENV DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor git unzip ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Installs any required Debian build libraries and supports gRPC used by
# google/cloud-firestore. Remove grpc/protobuf if your project uses REST only.
COPY --from=ghcr.io/mlocati/php-extension-installer:latest \
    /usr/bin/install-php-extensions /usr/local/bin/install-php-extensions
RUN install-php-extensions bcmath gd intl mbstring opcache pcntl \
    pdo_mysql pdo_pgsql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
COPY --from=frontend /app/public/build /var/www/html/public/build

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/studyquest.conf
COPY docker/start.sh /usr/local/bin/studyquest-start

RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/studyquest-start

EXPOSE 10000
CMD ["/usr/local/bin/studyquest-start"]
