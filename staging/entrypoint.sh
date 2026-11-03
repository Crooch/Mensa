#!/bin/sh
set -eu
if [ "${MENSA_STAGING_ONLY:-}" != "1" ] || [ "${MENSA_STAGING_DB_NAME:-}" != "mensa_staging" ]; then
    echo "REFUSO: configurazione staging non riconosciuta" >&2
    exit 2
fi
# Il file locale non viene copiato nel repository Git ne' in produzione.
cp /var/www/html/staging/config.local.php /var/www/html/config/config.local.php
cd /var/www/html
php bin/install.php
php staging/seed.php
echo "STAGING pronto: DB sintetico isolato, cron non attivo, email disabilitate"
exec "$@"
