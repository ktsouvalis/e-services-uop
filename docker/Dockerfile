# Dockerfile for Laravel App with Reverb WebSocket Server
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    apache2 \
    libapache2-mod-php8.2 \
    php8.2 \
    php8.2-cli \
    php8.2-mysql \
    php8.2-zip \
    php8.2-xml \
    php8.2-gd \
    php8.2-intl \
    php8.2-fileinfo \
    php8.2-ldap \
    php8.2-imap \
    php8.2-sqlite3 \
    php8.2-exif \
    php8.2-curl \
    curl git zip unzip supervisor bash \
    && apt-get clean

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the application code (excluding the docker directory)
COPY . /var/www
RUN rm -rf /var/www/docker

# Install PHP dependencies
RUN composer install --no-scripts --no-autoloader && composer dump-autoload --optimize

# Install frontend dependencies and build assets
RUN npm install && npm run build

# Copy Supervisor config for Reverb
COPY ./docker/supervisor /etc/supervisor

# Expose ports for NGINX and Reverb
EXPOSE 80 6001

# Command to start Supervisor to run PHP-FPM and Reverb
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
