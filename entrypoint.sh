#!/bin/bash
set -e

# Wait for DB to be ready (optional, avoids race conditions)
until php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; do
  echo "Waiting for database..."
  sleep 2
done

# Run migrations (create the migration file)
php bin/console make:migration --no-interaction

# Optionally run the migration
php bin/console doctrine:migrations:migrate --no-interaction

echo "📥 Running custom SQL script..."
mysql -h db -u root -p1203 dicrawler < /sql/post_migration.sql

# Run the original Apache CMD
exec apache2-foreground