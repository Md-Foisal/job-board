# syntax=docker/dockerfile:1

# One image runs the whole app: nginx and PHP-FPM serve the site, and two
# extra s6 services keep the queue worker and the scheduler running beside
# them, so a host that gives a single container still runs everything.

FROM serversideup/php:8.4-fpm-nginx-v5.0.0 AS base

USER root
RUN install-php-extensions intl gd bcmath
USER www-data

# PHP refuses a body over post_max_size before Laravel sees it; Laravel then
# shows its own 413 page. nginx's limit (100M by default) stays above it, so
# oversized uploads reach that page instead of nginx's.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_UPLOAD_MAX_FILE_SIZE=16M \
    PHP_POST_MAX_SIZE=20M


FROM base AS vendor

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress


# The stylesheet pulls Flux's CSS and Blade stubs out of vendor, so the
# assets are built after the PHP packages are in place.
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build


FROM base AS app

# The image's start-up scripts (migrations, storage link, caches) finish
# before s6 starts any service, so these two depend only on "base".
USER root
COPY --chmod=755 docker/s6/ /etc/s6-overlay/s6-rc.d/
RUN touch /etc/s6-overlay/user-bundles.d/user/contents.d/laravel-queue \
          /etc/s6-overlay/user-bundles.d/user/contents.d/laravel-scheduler
USER www-data

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# Runs package discovery and publishes Filament's assets into public/.
RUN composer dump-autoload --no-dev --optimize --no-interaction
