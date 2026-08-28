#!/bin/sh
set -e

# Render injects PORT at runtime (not build time), so the nginx listen
# directive is templated in and rendered here on container start.
export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

if [ -n "$AIVEN_CA_CERT" ]; then
    printf '%s' "$AIVEN_CA_CERT" > /etc/ssl/certs/aiven-ca.pem
fi

if [ "$APP_ENV" = "local" ]; then
    # Local dev: skip framework caching so code/blade/route edits show up
    # immediately without needing a container restart. Same image, same
    # PHP/nginx stack as production — only this caching step differs, and
    # it's driven by the same APP_ENV value Render already sets to
    # "production" (see render.yaml), not a separate code path.
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
else
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi
php artisan storage:link || true

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

exec supervisord -c /etc/supervisord.conf
