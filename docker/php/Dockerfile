FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev zip libonig-dev \
    libxml2-dev libpq-dev libcurl4-openssl-dev \
    libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client cron python3 python3-pip \
 && docker-php-ext-install pdo pdo_mysql intl zip ftp gd opcache \
 && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

RUN pip3 install --break-system-packages PyMuPDF

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers \
 && echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-available/security.conf \
 && a2enconf security

# Apache config
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copy app code
COPY . /var/www/html

# Copy dummy .env file (for build/runtime fallback)
COPY .env.prod /var/www/html/.env

# Run composer install 
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Permissions for runtime directory
RUN mkdir -p /var/www/html/public/csv \
 && chown -R www-data:www-data /var/www/html/public/csv \
 && chmod -R 775 /var/www/html/public/csv

EXPOSE 80
CMD ["apache2-foreground"]
