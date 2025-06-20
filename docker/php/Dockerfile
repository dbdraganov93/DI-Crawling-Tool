FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev zip libonig-dev \
    libxml2-dev libpq-dev libcurl4-openssl-dev \
    libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client cron python3 python3-pip \
 && docker-php-ext-install pdo pdo_mysql intl zip ftp gd opcache \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# ✅ Ensure a base php.ini exists
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# ✅ Add your custom overrides as an additional config file
COPY docker/php-overrides.ini "$PHP_INI_DIR/conf.d/custom.ini"

# Python deps
RUN pip3 install --break-system-packages PyMuPDF

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache hardening
RUN a2enmod rewrite headers \
 && echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-available/security.conf \
 && a2enconf security

# Apache config
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# App root
WORKDIR /var/www/html

# Code + env
COPY . /var/www/html
COPY .env.prod /var/www/html/.env

# Composer install
RUN composer install --no-dev --optimize-autoloader --no-scripts

# CSV perms
RUN mkdir -p /var/www/html/public/csv \
 && chown -R www-data:www-data /var/www/html/public/csv \
 && chmod -R 775 /var/www/html/public/csv

EXPOSE 80
CMD ["apache2-foreground"]
