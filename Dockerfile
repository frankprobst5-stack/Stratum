# Stratum CMS — container image.
#
# An alternative to the shared-hosting install path (composer install +
# php bin/install.php + real Apache), not a replacement for it — most
# clubs migrating to Stratum are on ordinary cPanel-style hosting, which
# is still the primary target (see docs/architecture.md). This is for
# admins who'd rather run it on a VPS via Docker instead.
#
# Same runtime shape real Apache hosting already gets: PHP 8.2 (the
# floor composer.json states and CI tests against), mod_rewrite +
# AllowOverride All so public/.htaccess's front-controller rewrite works
# unmodified — the same posture already verified against a real
# php:8.2-apache container earlier in this project (see
# docs/roadmap.md's Apache verification notes).
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.2-apache

# gd needs libjpeg/libwebp at build time for ImageThumbnailer's jpeg/webp
# support (core/services/ImageThumbnailer.php); zip is for real theme/addon
# package installs and the self-update mechanism (SafeZipExtractor) — CI's
# extension list omits it since no test exercises that path, but a real
# deployment does.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libjpeg-dev libwebp-dev libpng-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd exif fileinfo zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Document root is public/, not the project root — same reason
# public/.htaccess exists at all: index.php and the rewrite rule live
# there, everything above it (core/, storage/, .env) must never be
# web-reachable.
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public/!g' /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

# storage/ (uploads, cache, logs, backups) is the only directory Stratum
# writes to at runtime — see docs/database-conventions.md. Mounted as a
# volume in docker-compose.yml so it survives a container rebuild; the
# ownership fix here covers a fresh image with no volume mounted yet.
RUN chown -R www-data:www-data storage

EXPOSE 80
