#!/bin/bash
set -e

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

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Validating Doctrine schema..."
php bin/console doctrine:schema:validate || echo "Schema validation warnings"

echo "Starting Apache..."
exec apache2-foreground
