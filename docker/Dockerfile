## Rooted Dockerfile relocated to docker/Dockerfile
## Build with: docker compose -f docker/docker-compose.yml up -d --build

# ---------- Stage: Node build (frontend assets) ----------
FROM node:20-alpine AS node_build
WORKDIR /build
COPY ../package*.json ./
RUN npm ci --no-audit --no-fund
COPY .. .
RUN npm run build || (echo "Frontend build failed" && exit 1)

# ---------- Stage: Composer (PHP dependencies) ----------
FROM php:8.2-cli AS composer_deps
WORKDIR /var/www/html/dgu-services
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libxml2-dev libldap2-dev libonig-dev \
    libjpeg-dev libpng-dev libfreetype6-dev zlib1g-dev libssl-dev \
 && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd intl zip pdo_mysql exif xml bcmath mbstring pcntl \
 && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
 && docker-php-ext-install -j"$(nproc)" ldap || true
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 1. Copy only composer manifests for dependency caching
COPY ../composer.json ../composer.lock ./

# 2. Install PHP dependencies WITHOUT scripts (artisan not present yet)
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts \
 && rm -rf /root/.composer/cache

# 3. Copy full application source (provides artisan & app code)
COPY .. .

# 4. Now run autoload optimization and (optionally) framework discovery scripts
#    We invoke dump-autoload first then manually run package:discover instead of letting the original
#    post-autoload-dump hook fail earlier. If package:discover fails (e.g. missing .env) we don't want
#    to break the build, so it's allowed to continue.
RUN composer dump-autoload --optimize \
 && (php artisan package:discover --ansi || echo "[warn] artisan package:discover skipped during build")

# ---------- Stage: Final runtime (Apache + PHP) ----------
FROM php:8.2-apache AS app
ARG APP_WORKDIR=/var/www/html/dgu-services
RUN apt-get update && apt-get install -y \
    git curl tzdata unzip \
    libicu-dev libzip-dev libxml2-dev libldap2-dev libonig-dev \
    libjpeg-dev libpng-dev libfreetype6-dev zlib1g-dev \
 && rm -rf /var/lib/apt/lists/*
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd intl zip pdo_mysql exif xml opcache bcmath mbstring pcntl \
 && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
 && docker-php-ext-install -j"$(nproc)" ldap || true
COPY docker/apache/vhost.conf /tmp/vhost.conf
RUN set -e; a2enmod rewrite; \
    echo "[build] Using provided docker/apache/vhost.conf"; cp /tmp/vhost.conf /etc/apache2/sites-available/000-default.conf;

# Copy application code
COPY .. ${APP_WORKDIR}/
# Copy vendor & composer manifests from the correct path inside composer_deps stage
COPY --from=composer_deps ${APP_WORKDIR}/vendor ${APP_WORKDIR}/vendor
COPY --from=composer_deps ${APP_WORKDIR}/composer.* ${APP_WORKDIR}/

# Provide entrypoint scripts
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
RUN /usr/local/bin/entrypoint.sh

WORKDIR ${APP_WORKDIR}
COPY --from=node_build /build/public/build public/build
EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

# ---------- Stage: Assets builder runtime (separate container) ----------
FROM node:20-alpine AS assets-builder
ARG APP_WORKDIR=/var/www/html/dgu-services
WORKDIR ${APP_WORKDIR}
COPY ../package*.json ./
RUN npm ci --no-audit --no-fund
COPY .. .
CMD ["sh", "-c", "npm run build"]