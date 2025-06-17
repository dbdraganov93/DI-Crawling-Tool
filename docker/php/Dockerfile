FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip git nano curl libzip-dev zip libonig-dev \
    libxml2-dev libpq-dev libcurl4-openssl-dev \
    libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client netcat-openbsd cron python3 python3-pip \
    && docker-php-ext-install pdo pdo_mysql intl zip ftp gd opcache

RUN pip3 install --break-system-packages PyMuPDF

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules and secure Apache signature
RUN a2enmod rewrite headers \
 && echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-available/security.conf \
 && a2enconf security

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
RUN mkdir -p /var/www/html/public/csv \
 && chown -R www-data:www-data /var/www/html/public/csv \
 && chmod -R 775 /var/www/html/public/csv

COPY . /var/www/html

RUN git config --global --add safe.directory /var/www/html && \
    composer install && \
    mkdir -p /var/www/html/public/csv && \
    chown -R www-data:www-data /var/www/html/public/csv && \
    chmod -R 775 /var/www/html/public/csv && \
    php bin/console doctrine:database:drop --if-exists --force && \
    php bin/console doctrine:database:create && \
    rm -f src/Migrations/*.php && \
    php bin/console make:migration --no-interaction && \
    php bin/console doctrine:migrations:migrate --no-interaction && \
    php bin/console doctrine:fixtures:load --no-interaction

CMD service cron start && apache2-foreground

EXPOSE 80
