#!/bin/bash
set -e

echo "Ensuring runtime directories exist and have correct permissions..."
mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/www/html/public/csv

# Fix permissions
chown -R www-data:www-data /var/www/html/var /var/www/html/public/csv
chmod -R 775 /var/www/html/var /var/www/html/public/csv

# Wait for database connection (with timeout)
timeout=60
while ! php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  echo "Waiting for database..."
  sleep 2
  ((timeout--))
  if [ $timeout -le 0 ]; then
    echo "Timed out waiting for database"
    exit 1
  fi
done

# Running database migration if any migration file are available
if ls src/Migrations/*.php > /dev/null 2>&1; then
  echo "Running database migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction
else
  echo "No migration files found. Skipping migrations."
fi


echo "Validating Doctrine schema..."
php bin/console doctrine:schema:validate || echo "Schema validation warnings"

echo "Starting Apache..."
exec apache2-foreground
