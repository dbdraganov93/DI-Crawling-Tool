#!/bin/bash
set -e

# Wait for database connection
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  echo "Waiting for database..."
  sleep 2
done

# Update database schema to match entities
php bin/console doctrine:schema:update --force

# Validate schema
php bin/console doctrine:schema:validate

# Start Apache
exec apache2-foreground
