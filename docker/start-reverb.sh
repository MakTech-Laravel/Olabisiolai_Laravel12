#!/bin/bash
set -euo pipefail

HOST="${REVERB_SERVER_HOST:-0.0.0.0}"
PORT="${REVERB_SERVER_PORT:-8089}"

exec /usr/local/bin/php /var/www/artisan reverb:start --host="${HOST}" --port="${PORT}"
