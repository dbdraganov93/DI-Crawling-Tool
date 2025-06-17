FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip git nano curl libzip-dev zip libonig-dev \
    libxml2-dev libpq-dev libcurl4-openssl-dev \
    libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client netcat-openbsd cron python3 python3-pip \
    && docker-php-ext-install pdo pdo_mysql intl zip ftp gd opcache

RUN pip3 install --break-system-packages PyMuPDF

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN echo "Listen 8000" >> /etc/apache2/ports.conf

WORKDIR /var/www/html
RUN mkdir -p /var/www/html/public/csv \
 && chown -R www-data:www-data /var/www/html/public/csv \
 && chmod -R 775 /var/www/html/public/csv

EXPOSE 8000
