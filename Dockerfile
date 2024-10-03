# Use the official PHP image as the base image
FROM php:8.3-apache

# Set working directory
WORKDIR /var/www

# Update packages and install the necessary software
RUN apt-get update && \
    apt-get install -y software-properties-common ca-certificates lsb-release apt-transport-https git wget \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev zlib1g-dev libzip-dev libcurl4-openssl-dev libonig-dev \
    libxml2-dev libssl-dev libc-client-dev libkrb5-dev libjpeg-dev curl && \ 
    # Install Node.js and npm
    curl -fsSL https://deb.nodesource.com/setup_16.x | bash - && \
    apt-get install -y nodejs && \
    # Install Composer
    wget -O composer-setup.php https://getcomposer.org/installer && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm composer-setup.php && \
    # Clear cache
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-configure imap --with-kerberos --with-imap-ssl && \
    docker-php-ext-install -j$(nproc) gd zip curl fileinfo imap mbstring mysqli pdo_mysql exif

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Create a non-root user named 'sail'
RUN useradd -G www-data,root -u 1000 -d /home/sail sail && \
    mkdir -p /home/sail/.composer && \
    chown -R sail:sail /home/sail

# Copy existing application directory contents
COPY . /var/www

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www

# Install npm dependencies and build assets
RUN cd /var/www && npm install && npm run build

# Copy Apache vhost file
COPY ./docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Set the user to www-data
USER www-data

# Start Apache in the foreground
CMD ["apache2-foreground"]

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 CMD curl -f http://localhost/ || exit 1