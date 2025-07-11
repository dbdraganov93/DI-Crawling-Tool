#!/bin/bash

set -e

echo "⏳ Waiting for MySQL..."
until mysqladmin ping -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --silent; do
  sleep 2
done

echo "✅ MySQL is up"

echo "📦 Running Composer install..."
composer install

echo "📁 Ensuring CSV directory exists..."
mkdir -p /var/www/html/public/csv
chown -R www-data:www-data /var/www/html/public/csv
chmod -R 775 /var/www/html/public/csv

echo "📁 Ensuring PDF directory exists..."
mkdir -p /var/www/html/public/pdf
chown -R www-data:www-data /var/www/html/public/pdf
chmod -R 775 /var/www/html/public/pdf

echo "🧨 Dropping database (if exists)..."
php bin/console doctrine:database:drop --if-exists --force

echo "📚 Creating database..."
php bin/console doctrine:database:create

echo "🧹 Removing old migrations..."
rm -f src/Migrations/*.php

echo "🔧 Making new migration..."
php bin/console make:migration --no-interaction

echo "🚀 Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "🌱 Loading fixtures..."
php bin/console doctrine:fixtures:load --no-interaction

echo "⏲️ Starting cron..."
service cron start

echo "✅ Application is ready!"

exec apache2-foreground
