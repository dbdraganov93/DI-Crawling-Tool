#!/usr/bin/env bash
set -euo pipefail

cleanup_already_run=0
pids=()

cleanup() {
    if [[ $cleanup_already_run -eq 1 ]]; then
        return
    fi
    cleanup_already_run=1

    for pid in "${pids[@]}"; do
        if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null || true
        fi
    done

    for pid in "${pids[@]}"; do
        if [[ -n "$pid" ]]; then
            wait "$pid" 2>/dev/null || true
        fi
    done
}

trap cleanup EXIT INT TERM

git config --global --add safe.directory /var/www/html || true

MYSQL_HOST=${MYSQL_HOST:-mysql}
MYSQL_USER=${MYSQL_USER:-root}
MYSQL_PASSWORD=${MYSQL_PASSWORD:-1203}

until mysqladmin ping -h "$MYSQL_HOST" -u"$MYSQL_USER" "-p$MYSQL_PASSWORD" --silent; do
    echo "⏳ Waiting for MySQL..."
    sleep 2
done

echo "✅ MySQL is up"

export COMPOSER_ALLOW_SUPERUSER=1
echo "📦 Installing Composer dependencies..."
composer install --no-interaction --no-progress --prefer-dist

echo "📁 Ensuring Flipify storage directories are writable..."
mkdir -p /var/www/html/public/pdf
chown -R www-data:www-data /var/www/html/public/pdf
chmod -R 775 /var/www/html/public/pdf

echo "🗄️ Creating database if needed..."

php bin/console doctrine:database:create --if-not-exists --no-interaction

echo "🧱 Generating database migrations (if needed)..."

php bin/console doctrine:migrations:diff --no-interaction --allow-empty-diff

echo "🚀 Running database migrations..."

php bin/console doctrine:migrations:migrate --no-interaction

echo "🌱 Loading data fixtures..."

php bin/console doctrine:fixtures:load --no-interaction

echo "🪢 Preparing messenger transports..."

php bin/console messenger:setup-transports --no-interaction

echo "▶️ Starting async messenger consumer..."

php bin/console messenger:consume async --time-limit=3600 --sleep=1 --memory-limit=256M --no-interaction &
pids+=($!)

echo "▶️ Starting Flipify import worker..."

php bin/console app:flipify:worker --sleep=5 &
pids+=($!)

echo "▶️ Starting legacy Shopfully worker..."

php bin/console app:shopfully:worker &
pids+=($!)

set +e
wait -n "${pids[@]}"
exit_code=$?
set -e

cleanup
exit "$exit_code"
