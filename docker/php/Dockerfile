FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev zip libonig-dev \
    libxml2-dev libpq-dev libcurl4-openssl-dev \
    libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client cron python3 python3-pip \
 && docker-php-ext-install pdo pdo_mysql intl zip ftp gd opcache \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

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

# Temporary .env to avoid Symfony crashing at build time
RUN echo "APP_ENV=prod" > .env \
 && echo "DATABASE_URL=mysql://user:pass@localhost:3306/test" >> .env \
 && composer install --no-dev --optimize-autoloader \
 && rm .env

# Permissions for runtime directory
RUN mkdir -p /var/www/html/public/csv \
 && chown -R www-data:www-data /var/www/html/public/csv \
 && chmod -R 775 /var/www/html/public/csv

# Expose port and start apache
EXPOSE 80
CMD ["apache2-foreground"]
