#!/bin/sh
set -eu

PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*) echo "Invalid PORT value: $PORT" >&2; exit 1 ;;
esac

sed -ri "s/^Listen[[:space:]]+80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:([0-9]+)>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
