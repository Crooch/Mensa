#!/usr/bin/env bash
# =============================================================================
# Avvio della demo
#
# Reinstalla PHP + MariaDB (vengono azzerati a ogni riavvio del pod perché
# stanno fuori da /app), ricrea database e dati di esempio e avvia il server
# integrato di PHP sulle porte 3000 (UI) e 8001 (rotte /api).
#
# Uso:
#   bash /app/mensa-caltavuturo/bin/avvia_demo.sh           avvio normale:
#            ricarica il seed solo se il DB è vuoto, poi prepara la giornata di oggi
#   bash /app/mensa-caltavuturo/bin/avvia_demo.sh --reset   dati SEMPRE freschi:
#            azzera e ricarica il seed con le date calcolate su oggi
#
# Lo storico presenze del seed copre le ultime 10 giornate lavorative rispetto
# alla data odierna, quindi con --reset la demo è sempre "di oggi".
# =============================================================================
set -e
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RESET=0
[ "${1:-}" = "--reset" ] && RESET=1

echo "== 1/6 Pacchetti PHP/MariaDB"
if ! command -v php > /dev/null; then
    # Il pod puo' avere un apt-get in corso subito dopo l'avvio: attendiamo il lock.
    for i in $(seq 1 60); do
        fuser /var/lib/dpkg/lock-frontend > /dev/null 2>&1 || break
        echo "   attendo il rilascio del lock apt ($i)..."
        sleep 5
    done
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-cli php-mysql mariadb-server > /tmp/apt_demo.log 2>&1
fi
php -v | head -1

echo "== 2/6 Avvio MariaDB"
mkdir -p /var/run/mysqld /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
if [ ! -d /var/lib/mysql/mysql ]; then
    mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1 || true
fi
if ! mysqladmin status > /dev/null 2>&1; then
    (mysqld_safe --user=mysql > /tmp/mysqld.log 2>&1 &)
    for i in $(seq 1 40); do mysqladmin status > /dev/null 2>&1 && break; sleep 1; done
fi
mysqladmin status | head -1

echo "== 3/6 Database e utenza applicativa"
mysql -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS mensa_caltavuturo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'mensa'@'%' IDENTIFIED BY 'mensa2026';
GRANT ALL PRIVILEGES ON mensa_caltavuturo.* TO 'mensa'@'%';
FLUSH PRIVILEGES;
SQL

echo "== 4/6 Schema e dati di esempio"
cd "$APP_DIR"
php bin/install.php
FAMIGLIE=$(mysql -N -u mensa -pmensa2026 mensa_caltavuturo -e "SELECT COUNT(*) FROM genitori" 2>/dev/null || echo 0)
if [ "$RESET" -eq 1 ]; then
    echo "--reset richiesto: ricarico i dati di esempio con le date di oggi."
    php bin/seed.php | tail -4
elif [ "${FAMIGLIE:-0}" -eq 0 ]; then
    php bin/seed.php | tail -4
else
    echo "Dati già presenti (${FAMIGLIE} famiglie): seed non rieseguito (usa --reset per azzerare)."
fi

echo "== 5/6 Giornata odierna"
# Crea i record di presenza di oggi (default calcolato, nessun buono scalato):
# il report catering mostra subito il conteggio provvisorio.
php bin/prepara_giornata.php
# Esegue il job per i plessi il cui cutoff è già passato: congela le presenze,
# scala i buoni, aggiorna il report e genera i solleciti oltre fido.
php bin/cron_daily.php | sed 's/^/   /'

echo "== 6/6 Server web"
# Le porte 3000 (UI) e 8001 (/api, via ingress) sono normalmente occupate dai
# servizi del template React/FastAPI: qui servono al server PHP.
supervisorctl stop frontend backend > /dev/null 2>&1 || true
pkill -f "php -S 0.0.0.0:3000" > /dev/null 2>&1 || true
pkill -f "php -S 0.0.0.0:8001" > /dev/null 2>&1 || true
(nohup php -S 0.0.0.0:3000 -t public bin/router_dev.php > /tmp/php3000.log 2>&1 &)
(nohup php -S 0.0.0.0:8001 -t public bin/router_dev.php > /tmp/php8001.log 2>&1 &)
sleep 3

CODICE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:3000/login)
echo "Pagina di accesso: HTTP ${CODICE}"
curl -s -X POST http://localhost:8001/api/auth/login -H 'Content-Type: application/json' \
     -d '{"username":"admin","password":"Admin2026!"}' | head -c 120
echo
echo
echo "Demo pronta: https://mensa.comune.caltavuturo.pa.it/login"
echo "Credenziali: admin / Admin2026!  ·  operatore / Operatore2026!  ·  scuola.primaria / Scuola2026!"
echo "             catering / Catering2026!  ·  lobianco.g / Genitore2026!"
