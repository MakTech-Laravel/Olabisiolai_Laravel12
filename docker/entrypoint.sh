#!/bin/bash
set -euo pipefail

cd /var/www

echo "[entrypoint] Starting Gidira API container..."

# ─── Merge runtime env vars into .env (Coolify injects at boot) ───────────────
if [ -f .env ]; then
    cp .env .env.backup
fi

printenv | grep -E "^(APP_|DB_|REDIS_|MAIL_|REVERB_|PUSHER_|VITE_|BROADCAST_|QUEUE_|SESSION_|CACHE_|AWS_|FILESYSTEM_|PASSPORT_|FORTIFY_|CASHIER_|STRIPE_|GOOGLE_|SOCIALITE_|FRONTEND_|CORS_|NOTIFICATIONS_|THROTTLE_|TERMII_|PAYSTACK_|FLW_|REALTIME_|MESSAGING_)" | while IFS='=' read -r key value; do
    if [ -n "$value" ]; then
        if grep -q "^${key}=" .env 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${value}|" .env
        else
            echo "${key}=${value}" >> .env
        fi
    fi
done

echo "[entrypoint] Environment written to .env"

php artisan config:clear --ansi 2>/dev/null || true
php artisan route:clear --ansi 2>/dev/null || true

APP_KEY_VAL=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d'=' -f2-)
if [ -z "$APP_KEY_VAL" ] || [ "$APP_KEY_VAL" = '""' ]; then
    echo "[entrypoint] APP_KEY missing — generating one"
    php artisan key:generate --force --no-interaction || true
fi

php artisan migrate --force --no-interaction || echo "[entrypoint] WARNING: migrate failed (will continue)"

# shellcheck source=/dev/null
source /usr/local/bin/ensure-passport.sh
ensure_passport_keys
ensure_passport_clients

php artisan storage:link --force --ansi 2>/dev/null || true

php artisan config:cache 2>/dev/null || true
php artisan event:cache 2>/dev/null || true

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "[entrypoint] Launching: $*"
exec "$@"
