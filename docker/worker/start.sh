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
composer install --no-interaction --no-progress --prefer-dist

mkdir -p /var/www/html/public/pdf
chown -R www-data:www-data /var/www/html/public/pdf
chmod -R 775 /var/www/html/public/pdf

until php bin/console doctrine:query:sql "SELECT 1 FROM flipify_import LIMIT 1" >/dev/null 2>&1; do
    echo "⏳ Waiting for database schema..."
    sleep 2
done

echo "✅ Database schema is ready"

php bin/console messenger:setup-transports --no-interaction

php bin/console messenger:consume async --time-limit=0 --sleep=1 --memory-limit=256M &
pids+=($!)

php bin/console app:shopfully:worker &
pids+=($!)

set +e
wait -n "${pids[@]}"
exit_code=$?
set -e

cleanup
exit "$exit_code"
