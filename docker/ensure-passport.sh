#!/bin/bash
# Auto-provision Laravel Passport RSA keys and DB clients on container start.
# Do not set PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY in Coolify .env — file keys are used.

set -euo pipefail

ensure_passport_keys() {
    local private_key="storage/oauth-private.key"
    local public_key="storage/oauth-public.key"

    if [ ! -f "${private_key}" ] || [ ! -f "${public_key}" ]; then
        echo "[entrypoint] Passport keys missing — generating storage/oauth-*.key"
        php artisan passport:keys --force --ansi
    fi

    if [ -f "${private_key}" ] && [ -f "${public_key}" ]; then
        chown www-data:www-data "${private_key}" "${public_key}" 2>/dev/null || true
        chmod 600 "${private_key}" "${public_key}" 2>/dev/null || true
    else
        echo "[entrypoint] WARNING: Passport key files still missing after passport:keys" >&2
    fi
}

ensure_passport_clients() {
    # Idempotent seeder — creates personal access clients for users/admins when absent.
    php artisan db:seed --class=PassportClientSeeder --force --no-interaction --ansi 2>/dev/null \
        || echo "[entrypoint] Passport client seed skipped (database not ready yet)" >&2
}
