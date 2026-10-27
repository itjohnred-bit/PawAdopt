#!/bin/bash
# PawAdopt container entrypoint.
# Apache config files cannot reference ${PORT} at build time — Render injects
# PORT at RUNTIME, so the Listen directives are rewritten here on every boot.
set -euo pipefail

PORT="${PORT:-10000}"
echo "PawAdopt: Apache listening on 0.0.0.0:${PORT}" >&2

sed -i "s/^Listen .*/Listen 0.0.0.0:${PORT}/"  /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# uploads dir must be writable even when a Render disk is freshly mounted
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads || true

exec apache2-foreground
