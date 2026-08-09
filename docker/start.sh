#!/bin/sh
set -e

# Render injects PORT at runtime (not build time), so the nginx listen
# directive is templated in and rendered here on container start.
export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

if [ -n "$AIVEN_CA_CERT" ]; then
    printf '%s' "$AIVEN_CA_CERT" > /etc/ssl/certs/aiven-ca.pem
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

exec supervisord -c /etc/supervisord.conf
