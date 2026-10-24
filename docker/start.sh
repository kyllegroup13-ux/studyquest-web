#!/bin/sh
set -eu

cd /var/www/html

# Render-mounted Firebase service account.
SECRET_SOURCE="/etc/secrets/firebase-service-account.json"

# Private runtime location accessible to PHP-FPM.
SECRET_TARGET="/var/www/html/storage/app/firebase/service-account.json"

# Verify the secret file exists.
if [ ! -f "$SECRET_SOURCE" ]; then
    echo "ERROR: Firebase service account secret file is missing." >&2
    exit 1
fi

# Create a private directory owned by PHP-FPM's user.
install -d -m 700 -o www-data -g www-data \
    /var/www/html/storage/app/firebase

# Copy the Firebase credentials with restricted permissions.
install -m 600 -o www-data -g www-data \
    "$SECRET_SOURCE" "$SECRET_TARGET"

# Configure Laravel and Google authentication to use the runtime copy.
export FIREBASE_CREDENTIALS="$SECRET_TARGET"
export GOOGLE_APPLICATION_CREDENTIALS="$SECRET_TARGET"

# Cache Laravel configuration after setting runtime variables.
php artisan config:cache

# Start Nginx and PHP-FPM through Supervisor.
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/studyquest.conf