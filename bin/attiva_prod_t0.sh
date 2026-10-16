#!/usr/bin/env bash
# Attivazione servizio Mensa DOPO rilascio T0: calendari ufficiali e UNICO cron.
# Nessuna importazione test, nessun cambio a saldi/anagrafiche.
set -Eeuo pipefail
umask 077

DEST=/srv/www/mensa/app
MAINT="$DEST/storage/maintenance.flag"
ENABLED="$DEST/config/scheduler_enabled.flag"
BACKUPS=/var/backups/mensa
OLD='# */5 * * * * /usr/bin/php /srv/www/mensa/app/bin/cron_daily.php >> /srv/www/mensa/logs/cron.log 2>&1'
NEW='*/5 * * * * /usr/bin/php /srv/www/mensa/app/bin/cron_daily.php >> /srv/www/mensa/logs/cron.log 2>&1'
die() { echo "STOP ATTIVAZIONE: $*" >&2; exit 1; }

[[ "$EUID" -eq 0 ]] || die "richiesto sudo"
[[ "${1:-}" == '--execute' && "$#" == 1 ]] || die "richiesto --execute"
[[ -f "$MAINT" ]] || die "non risulta la manutenzione della Mensa"
[[ ! -e "$ENABLED" ]] || die "il job risulta gia' attivo"
[[ -d /srv/www/mensa/logs ]] || die "cartella cron log mancante"

t0="$(mariadb -N --database=mensa_db -e \
  "SELECT valore FROM impostazioni WHERE chiave='contabilita_t0'")"
[[ "$t0" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]] ||
  die "T0 contabile non completato"
[[ "$(mariadb -N --database=mensa_db -e \
  'SELECT COUNT(*) FROM movimenti_crediti')" == 0 ]] ||
  die "ci sono movimenti: non attivare senza riconciliazione"

# Configurazione iniziale dei due plessi gia' previsti dal piano del Comune.
# Non sovrascrive configurazioni esistenti, ne' modifica anagrafiche.
infanzia="$(mariadb -N --database=mensa_db -e \
  "SELECT COUNT(*) FROM plessi WHERE nome='Scuola dell''Infanzia - Via Catania' AND cancellato_logicamente=0")"
secondaria="$(mariadb -N --database=mensa_db -e \
  "SELECT COUNT(*) FROM plessi WHERE nome='Scuola Secondaria di I grado' AND cancellato_logicamente=0")"
[[ "$infanzia" == 1 && "$secondaria" == 1 ]] ||
  die "i nomi/numero dei plessi non corrispondono: impostare calendari dall'Admin"

mariadb --database=mensa_db <<'SQL'
INSERT INTO calendari_servizio
  (plesso_id, data_inizio, lunedi, martedi, mercoledi, giovedi, venerdi, sabato, domenica)
SELECT p.id, '2026-10-19', 1,1,1,1,1,0,0
FROM plessi p
WHERE p.nome='Scuola dell''Infanzia - Via Catania'
AND NOT EXISTS (SELECT 1 FROM calendari_servizio c WHERE c.plesso_id=p.id);

INSERT INTO calendari_servizio
  (plesso_id, data_inizio, lunedi, martedi, mercoledi, giovedi, venerdi, sabato, domenica)
SELECT p.id, '2026-10-19', 1,0,0,1,0,0,0
FROM plessi p
WHERE p.nome='Scuola Secondaria di I grado'
AND NOT EXISTS (SELECT 1 FROM calendari_servizio c WHERE c.plesso_id=p.id);
SQL

# Non attivare se esiste una configurazione differente da quella concordata.
read -r cal_inf cal_sec < <(mariadb -N --database=mensa_db -e "
 SELECT
  (SELECT COUNT(*) FROM calendari_servizio c JOIN plessi p ON p.id=c.plesso_id
    WHERE p.nome='Scuola dell''Infanzia - Via Catania'
    AND c.data_inizio='2026-10-19'
    AND c.lunedi=1 AND c.martedi=1 AND c.mercoledi=1
    AND c.giovedi=1 AND c.venerdi=1 AND c.sabato=0 AND c.domenica=0),
  (SELECT COUNT(*) FROM calendari_servizio c JOIN plessi p ON p.id=c.plesso_id
    WHERE p.nome='Scuola Secondaria di I grado'
    AND c.data_inizio='2026-10-19'
    AND c.lunedi=1 AND c.martedi=0 AND c.mercoledi=0
    AND c.giovedi=1 AND c.venerdi=0 AND c.sabato=0 AND c.domenica=0)
")
[[ "$cal_inf" == 1 && "$cal_sec" == 1 ]] ||
  die "regole calendario discordanti: cron non attivato"

# Si riutilizza ESATTAMENTE la pianificazione preesistente.
before="$(crontab -u mensa -l)"
[[ "$(printf '%s\n' "$before" | grep -Fxc "$OLD")" == 1 ]] ||
  die "riga cron originale non trovata una sola volta"
if printf '%s\n' "$before" | grep -Ev '^[[:space:]]*(#|$)' |
   grep -Eq 'cron_daily\.php|prepara_giornata\.php|/scheduler'; then
  die "e' presente un secondo cron attivo"
fi
install -d -m 0700 "$BACKUPS"
printf '%s\n' "$before" > "$BACKUPS/crontab_mensa_pre_t0_$(date +%Y%m%d_%H%M%S).txt"
temp="$(mktemp)"
trap 'rm -f "$temp"' EXIT
printf '%s\n' "$before" | awk -v old="$OLD" -v new="$NEW" \
  '{ if ($0 == old) print new; else print; }' > "$temp"
[[ "$(grep -Fxc "$NEW" "$temp")" == 1 ]] ||
  die "la riga cron nuova non corrisponde a quella prevista"
crontab -u mensa "$temp"

# Riaprire l'interfaccia prima di abilitare il primo job.
rm -f "$MAINT"
install -m 0644 /dev/null "$ENABLED"
status="$(runuser -u mensa -- /usr/bin/php "$DEST/bin/cron_daily.php" --check)"
printf '%s\n' "$status"
printf '%s\n' "$status" | grep -q 'Scheduler: ABILITATO' ||
  die "il job non risulta abilitato"

echo 'ATTIVAZIONE COMPLETATA: app accessibile, calendari impostati,'
echo 'cron esistente ogni 5 minuti abilitato, ticket T0 invariati.'
echo 'Solleciti automatici lasciati DISABILITATI.'
