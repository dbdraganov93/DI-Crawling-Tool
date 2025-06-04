FROM php:8.4-apache

# Install required PHP extensions and system packages
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    default-mysql-client \
#    python3-pip \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo pdo_mysql intl zip opcache ftp \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy Symfony project files (or mount with volume in docker-compose)
COPY . /var/www/html

RUN git config --global --add safe.directory /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --optimize-autoloader

# Set proper Apache document root for Symfony
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# Expose port 80
EXPOSE 80

# Adjust Apache config to Symfony's public dir
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# RUN pip install PyMuPDF

RUN rm -f /var/www/html/migrations/*.php

COPY post_migration.sql /sql/post_migration.sql
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]