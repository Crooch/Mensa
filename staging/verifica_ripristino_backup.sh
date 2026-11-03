#!/usr/bin/env bash
# Prova un backup e il suo ripristino in una SECONDA base sintetica Docker.
# Non accede alla VPS, non elimina lo staging di lavoro, non usa dati reali.
set -euo pipefail

if [[ "${CI:-}" != "true" ]]; then
  echo "Questo test è riservato alla CI, non va eseguito sulla VPS o sul Mac." >&2
  exit 2
fi

compose=(docker compose -f compose.staging.yml)
origine="mensa_staging"
destinazione="mensa_restore_ci"

# L'ambiente deve essere inequivocabilmente lo staging sintetico.
verifica_env=$("${compose[@]}" exec -T database sh -c \
  'printf "%s" "${MARIADB_DATABASE:-}"')
if [[ "$verifica_env" != "$origine" ]]; then
  echo "Database di partenza inatteso. Operazione interrotta." >&2
  exit 2
fi
if ! "${compose[@]}" exec -T app sh -c \
  'test "$MENSA_STAGING_ONLY" = "1" && test "$MENSA_STAGING_DB_NAME" = "mensa_staging"'; then
  echo "Applicazione di staging non verificata. Operazione interrotta." >&2
  exit 2
fi

sql() {
  local db="$1"
  "${compose[@]}" exec -T database sh -c \
    'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --batch --skip-column-names "$1"' sh "$db"
}

esiste=$(printf "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='%s';\n" \
  "$destinazione" | sql information_schema)
if [[ "$esiste" != "0" ]]; then
  echo "Database di ripristino già presente: non verrà sovrascritto." >&2
  exit 2
fi

dump=$(mktemp)
database_creato=0
pulizia() {
  if [[ "$database_creato" == 1 ]]; then
    printf 'DROP DATABASE IF EXISTS `%s`;\n' "$destinazione" | sql information_schema || true
  fi
  rm -f "$dump"
}
trap pulizia EXIT

"${compose[@]}" exec -T database sh -c \
  'exec mariadb-dump --single-transaction --quick --routines --triggers --events --no-tablespaces -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
  > "$dump"

if [[ ! -s "$dump" ]]; then
  echo "Dump vuoto: test non valido." >&2
  exit 1
fi
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' \
  "$destinazione" | sql information_schema
database_creato=1
sql "$destinazione" < "$dump"

tabelle=$(printf "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='%s' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;\n" \
  "$origine" | sql information_schema)
tabelle_ripristinate=$(printf "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='%s' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;\n" \
  "$destinazione" | sql information_schema)
if [[ -z "$tabelle" || "$tabelle" != "$tabelle_ripristinate" ]]; then
  echo "L'elenco delle tabelle ripristinate non corrisponde all'originale." >&2
  exit 1
fi

while IFS= read -r tabella; do
  [[ "$tabella" =~ ^[A-Za-z0-9_]+$ ]] || exit 2
  prima=$(printf 'SELECT COUNT(*) FROM `%s`;\n' "$tabella" | sql "$origine")
  dopo=$(printf 'SELECT COUNT(*) FROM `%s`;\n' "$tabella" | sql "$destinazione")
  if [[ "$prima" != "$dopo" ]]; then
    echo "Ripristino non coerente nella tabella $tabella." >&2
    exit 1
  fi
done <<< "$tabelle"

# Controllo esplicito dei dati economici, oltre al numero di record.
for query in \
  'SELECT COALESCE(SUM(saldo),0) FROM pool_crediti' \
  'SELECT COALESCE(SUM(delta),0) FROM movimenti_crediti' \
  'SELECT COUNT(*) FROM movimenti_crediti WHERE tipo="acquisto"' \
  'SELECT COUNT(*) FROM richieste_pasta_bianco' \
  'SELECT COUNT(*) FROM menu_settimanali_revisioni'; do
  prima=$(printf '%s;\n' "$query" | sql "$origine")
  dopo=$(printf '%s;\n' "$query" | sql "$destinazione")
  if [[ "$prima" != "$dopo" ]]; then
    echo "Dati non coerenti dopo il ripristino di staging." >&2
    exit 1
  fi
done

echo "OK: dump e ripristino in database sintetico separato, struttura e dati coerenti."
echo "Il database provvisorio viene eliminato; lo staging originario resta invariato."
