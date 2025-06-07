FROM php:8.4-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    nano \
    curl \
    libzip-dev \
    zip \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-mysql-client \
    netcat-openbsd \
    cron \
    python3 \
    python3-pip \
    && docker-php-ext-install pdo pdo_mysql intl zip ftp gd

# Install PyMuPDF
RUN pip3 install --break-system-packages PyMuPDF

# Copy your cron job
COPY docker/cron/iproto-cron /etc/cron.d/iproto-cron
RUN chmod 0644 /etc/cron.d/iproto-cron && crontab /etc/cron.d/iproto-cron

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Set working directory
WORKDIR /var/www/html

EXPOSE 8000
