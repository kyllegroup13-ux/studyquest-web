#!/bin/sh
set -eu
cd /var/www/html
# Cache production config only after Render injects runtime environment vars.
php artisan config:cache
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/studyquest.conf
