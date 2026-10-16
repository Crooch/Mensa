#!/usr/bin/env bash
# Mensa Caltavuturo — RILASCIO codice + sole strutture + azzeramento ticket a T0.
# NON importa alcun dato di staging. NON attiva il cron.
# Si esegue sulla VPS con sudo, da una copia del commit GitHub collaudato.
# Dopo il comando resta in manutenzione: l'Admin configurera' i calendari
# e la riapertura e' un passaggio separato e volontario.
set -Eeuo pipefail
umask 077

SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="/srv/www/mensa/app"
BACKUP_DIR="/var/backups/mensa"
TODAY="$(TZ=Europe/Rome date +'%Y-%m-%d')"
STAMP="$(TZ=Europe/Rome date +'%Y%m%d_%H%M%S')"
BACKUP_DB="$BACKUP_DIR/pre_t0_${STAMP}.sql.gz"
BACKUP_APP="$BACKUP_DIR/pre_release_app_${STAMP}.tar.gz"
MAINT="$DEST/storage/maintenance.flag"

die() { echo "STOP RILASCIO: $*" >&2; exit 1; }
[[ "$EUID" -eq 0 ]] || die "eseguire con sudo come root"
[[ "${1:-}" == '--execute' && "$#" == 1 ]] || die "richiesta conferma --execute"
[[ "${CONFIRM_T0:-}" == "AZZERA-TICKET-${TODAY}" ]] ||
  die "manca conferma esplicita CONFIRM_T0=AZZERA-TICKET-${TODAY}"
[[ -f "$DEST/bootstrap.php" && -d "$DEST/public" && -d "$DEST/storage" ]] ||
  die "installazione di produzione inattesa"
[[ -f "$SOURCE/bin/reset_ticket_t0.php" &&
   -f "$SOURCE/database/deploy/upgrade_schema_only_2026_10.sql" &&
   -f "$SOURCE/public/index.php" ]] || die "bundle incompleto"
for cmd in mariadb mariadb-dump gzip tar php rsync sha256sum crontab; do
  command -v "$cmd" >/dev/null || die "comando assente: $cmd"
done

# Lo scheduler attuale deve essere sospeso: mai avere due job/trigger.
CRON="$(crontab -u mensa -l 2>/dev/null || true)"
if printf '%s\n' "$CRON" | grep -Ev '^[[:space:]]*(#|$)' |
   grep -Eq 'cron_daily\.php|prepara_giornata\.php|/scheduler'; then
  die "nel crontab mensa e' presente un job attivo"
fi

install -d -m 0700 "$BACKUP_DIR"
echo "[1/8] Backup integrale dell'applicazione di produzione"
tar -czf "$BACKUP_APP" -C /srv/www/mensa app
[[ -s "$BACKUP_APP" ]] && tar -tzf "$BACKUP_APP" >/dev/null ||
  die "backup codice non verificato"

# L'indice nuovo risponde 503 alla presenza del flag. Evita di fermare
# Apache che serve anche altre applicazioni sulla stessa VPS.
echo "[2/8] Attivo la manutenzione solo per Mensa"
touch "$MAINT"
cp "$SOURCE/public/index.php" "$DEST/public/index.php"
sleep 3

echo "[3/8] Backup MariaDB dei dati reali: mai importare dump di prova"
bash -o pipefail -c '
  mariadb-dump --single-transaction --quick --routines --triggers --events \
    --default-character-set=utf8mb4 --no-tablespaces mensa_db | gzip -c > "$1"
' sh "$BACKUP_DB"
[[ -s "$BACKUP_DB" ]] && gzip -t "$BACKUP_DB" ||
  die "dump del DB non verificato"
sha256sum "$BACKUP_DB" > "$BACKUP_DB.sha256"

echo "[4/8] Aggiungo SOLO le strutture mancanti, nessun seed/import"
mariadb --database=mensa_db < "$SOURCE/database/deploy/upgrade_schema_only_2026_10.sql"

echo "[5/8] Pubblico codice PHP/JS con esclusione di configurazione e dati"
rsync -rlt --no-perms --no-owner --no-group \
  --exclude='config/config.local.php' \
  --exclude='storage/' \
  --exclude='.git/' \
  --exclude='staging/' \
  --exclude='compose.staging.yml' \
  --exclude='*.sql.gz' \
  "$SOURCE/" "$DEST/"

# Svuotamento opcache del pool PHP, senza spegnere Apache.
if systemctl is-active --quiet php8.5-fpm; then
  systemctl reload php8.5-fpm
fi

echo "[6/8] Verifico lo switch del job senza collegarmi al database"
CHECK="$(runuser -u mensa -- /usr/bin/php "$DEST/bin/cron_daily.php" --check)"
printf '%s\n' "$CHECK"
printf '%s\n' "$CHECK" | grep -q 'Scheduler: DISABILITATO' ||
  die "scheduler abilitato: prima disabilitarlo in config.local.php"
printf '%s\n' "$CHECK" | grep -q 'DB configurato: mensa_db' ||
  die "app collegata a un database differente da mensa_db"

echo "[7/8] Reset T0 contabile: saldi e storico ticket, NON anagrafiche"
php "$DEST/bin/reset_ticket_t0.php" --execute --t0="$TODAY" --backup="$BACKUP_DB"

echo "[8/8] Conferma valori contabili e marker"
mariadb --database=mensa_db --batch <<'SQL'
SELECT 'movimenti_ticket' AS voce, COUNT(*) AS valore FROM movimenti_crediti
UNION ALL
SELECT 'somma_saldi_ticket', COALESCE(SUM(saldo),0) FROM pool_crediti
UNION ALL
SELECT 'pool_conservati', COUNT(*) FROM pool_crediti;
SELECT chiave, valore FROM impostazioni WHERE chiave='contabilita_t0';
SQL

echo
echo 'CODICE PUBBLICATO E CONTABILITA T0 AZZERATA.'
echo "Backup DB: $BACKUP_DB"
echo "Backup app: $BACKUP_APP"
echo 'APP IN MANUTENZIONE: NON rimuovere il flag finche non si'
echo 'completano i calendari e la verifica degli accessi.'
echo "Flag: $MAINT"
echo 'CRON: INVARIATO, SOSPESO.'
