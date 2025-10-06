FROM php:8.2-apache

# Build-time argument to set application working directory. You can pass this
# from docker-compose with build.args (APP_WORKDIR). This is build-time only;
# container_name is a runtime property and cannot be read at build time.
ARG APP_WORKDIR=/var/www/html/dgu-services

# Non-interactive
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies for PHP extensions and tools
RUN apt-get update && apt-get install -y \
    git curl tzdata \
    libicu-dev libzip-dev \
    libjpeg-dev libpng-dev libfreetype6-dev \
    libxml2-dev \
    libldap2-dev \
    libonig-dev \
    zip unzip \
    && rm -rf /var/lib/apt/lists/*

# Timezone
ENV TZ=Europe/Athens
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) gd intl zip pdo_mysql exif xml opcache bcmath mbstring pcntl

# LDAP (optional; ignore failure if platform lacks some headers)
RUN docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
 && docker-php-ext-install -j$(nproc) ldap || true

# Enable Apache rewrite
RUN a2enmod rewrite

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN mkdir -p ${APP_WORKDIR}
WORKDIR ${APP_WORKDIR}
RUN git clone https://github.com/ktsouvalis/e-services-uop.git .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# Provide entrypoint scripts
COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
# RUN /usr/local/bin/entrypoint.sh 

# Copy Apache vhost if present
COPY ./vhost.conf /etc/apache2/sites-available/000-default.conf

# Expose ports used in compose
EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]