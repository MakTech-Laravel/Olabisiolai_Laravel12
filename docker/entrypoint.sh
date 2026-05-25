#!/bin/bash
set -euo pipefail

cd /var/www

php artisan config:clear --ansi 2>/dev/null || true
php artisan route:clear --ansi 2>/dev/null || true

# Public disk uploads (review images, logos, etc.)
php artisan storage:link --force --ansi 2>/dev/null || true

# nginx :80 (public). Reverb :8089 (internal). Do not set REVERB_SERVER_PORT=80.
exec "$@"
