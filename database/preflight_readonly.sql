-- Mensa Caltavuturo — preflight NON DISTRUTTIVO del database.
-- Compatibile con lo schema iniziale 001, senza richiedere le migrazioni 002-009.
-- Esecuzione prevista con client mariadb dal terminale, su copia o produzione.
-- SOLO SELECT / informazioni di schema. Nessun nome di bambino/genitore e
-- nessun saldo individuale sono restituiti nell'output.
-- IMPORTANTE: non costituisce backup ne' autorizzazione al rilascio.

START TRANSACTION READ ONLY;

SELECT '00_AMBIENTE' AS controllo,
       DATABASE() AS database_corrente,
       VERSION() AS versione_mariadb,
       NOW() AS istante_verifica;

SELECT '01_TABELLE_BASE' AS controllo,
       t.TABLE_NAME AS tabella,
       t.TABLE_TYPE AS tipo
FROM information_schema.TABLES t
WHERE t.TABLE_SCHEMA = DATABASE()
  AND t.TABLE_NAME IN (
      'plessi','utenti','genitori','bambini','classi_sezioni','assegnazioni_classe',
      'pool_crediti','movimenti_crediti','presenze_giornaliere','esecuzioni_job',
      'menu_giornalieri','log_solleciti_inviati'
  )
ORDER BY t.TABLE_NAME;

-- Serve a distinguere database iniziale e schema gia' migrato.
-- Non interroga tabelle non ancora esistenti, percio' puo' essere eseguito
-- sia prima sia dopo il rilascio.
SELECT '02_SCHEMA_AGGIORNAMENTI' AS controllo,
       c.TABLE_NAME AS tabella,
       c.COLUMN_NAME AS colonna,
       c.DATA_TYPE AS tipo
FROM information_schema.COLUMNS c
WHERE c.TABLE_SCHEMA = DATABASE()
  AND (
       (c.TABLE_NAME = 'movimenti_crediti' AND c.COLUMN_NAME = 'richiesta_id')
    OR (c.TABLE_NAME = 'eccezioni_calendario' AND c.COLUMN_NAME = 'giorno_fine')
    OR (c.TABLE_NAME = 'menu_settimanali_revisioni'
        AND c.COLUMN_NAME IN ('nome','foto_json'))
  )
ORDER BY c.TABLE_NAME, c.COLUMN_NAME;

SELECT '03_TABELLE_NUOVE' AS controllo,
       t.TABLE_NAME AS tabella
FROM information_schema.TABLES t
WHERE t.TABLE_SCHEMA = DATABASE()
  AND t.TABLE_TYPE = 'BASE TABLE'
  AND t.TABLE_NAME IN (
      'calendari_servizio', 'eccezioni_calendario',
      'richieste_pasta_bianco','log_operazioni_presenze',
      'menu_settimanali','menu_settimanali_revisioni',
      'menu_settimanali_assegnazioni'
  )
ORDER BY t.TABLE_NAME;

-- Solo nomi dei plessi (non anagrafiche familiari) e aggregati di classi:
-- controllo essenziale delle corrispondenze esatte richieste dalla migration 002.
SELECT '04_PLESSI' AS controllo,
       p.id AS plesso_id,
       p.nome AS plesso,
       p.cancellato_logicamente AS disattivato,
       TIME_FORMAT(p.cutoff_orario, '%H:%i') AS cutoff,
       COUNT(DISTINCT CASE
           WHEN cs.anno_scolastico = '2026/2027'
            AND cs.cancellata_logicamente = 0 THEN cs.id
           ELSE NULL END) AS classi_2026_2027
FROM plessi p
LEFT JOIN classi_sezioni cs ON cs.plesso_id = p.id
GROUP BY p.id, p.nome, p.cancellato_logicamente, p.cutoff_orario
ORDER BY p.nome, p.id;

SELECT '05_PLESSI_ATTESI' AS controllo,
       SUM(nome = 'Scuola dell''Infanzia - Via Catania'
           AND cancellato_logicamente = 0) AS corrispondenze_infanzia,
       SUM(nome = 'Scuola Secondaria di I grado'
           AND cancellato_logicamente = 0) AS corrispondenze_secondaria,
       COUNT(*) AS numero_plessi_totale
FROM plessi;

SELECT '06_CONTEGGI_BASE' AS controllo,
       (SELECT COUNT(*) FROM utenti) AS utenti,
       (SELECT COUNT(*) FROM genitori) AS nuclei,
       (SELECT COUNT(*) FROM bambini) AS bambini,
       (SELECT COUNT(*) FROM classi_sezioni) AS classi,
       (SELECT COUNT(*) FROM assegnazioni_classe) AS assegnazioni,
       (SELECT COUNT(*) FROM presenze_giornaliere) AS presenze,
       (SELECT COUNT(*) FROM menu_giornalieri) AS menu_giornalieri;

-- La somma dei saldi e quella dei movimenti sono due valori diversi,
-- letti separatamente: una loro differenza non viene corretta in automatico.
SELECT '07_CONTABILITA_AGGREGATA' AS controllo,
       (SELECT COUNT(*) FROM pool_crediti) AS pool,
       (SELECT COALESCE(SUM(saldo),0) FROM pool_crediti) AS somma_saldi_buoni,
       (SELECT COUNT(*) FROM movimenti_crediti) AS numero_movimenti,
       (SELECT COALESCE(SUM(delta),0) FROM movimenti_crediti) AS somma_movimenti_buoni,
       (SELECT COUNT(*) FROM movimenti_crediti WHERE tipo='acquisto') AS acquisti,
       (SELECT COUNT(*) FROM movimenti_crediti WHERE tipo='consumo_pasto') AS consumi,
       (SELECT COUNT(*) FROM movimenti_crediti WHERE tipo='rettifica_manuale') AS rettifiche;

SELECT '08_POOL_NON_ALLINEATI' AS controllo,
       COUNT(*) AS numero_pool_non_allineati,
       COALESCE(SUM(ABS(differenza)),0) AS scostamento_assoluto_buoni
FROM (
    SELECT pc.id,
           pc.saldo - COALESCE(SUM(m.delta),0) AS differenza
    FROM pool_crediti pc
    LEFT JOIN movimenti_crediti m ON m.pool_id = pc.id
    GROUP BY pc.id, pc.saldo
    HAVING differenza <> 0
) as verifica_pool;

SELECT '09_CONSUMI_DUPLICATI' AS controllo,
       COUNT(*) AS coppie_bambino_giorno_con_piu_consumi
FROM (
    SELECT bambino_id, data
    FROM movimenti_crediti
    WHERE tipo='consumo_pasto' AND bambino_id IS NOT NULL
    GROUP BY bambino_id, data
    HAVING COUNT(*) > 1
) AS consumi_duplicati;

-- Tre righe anche se il database non contiene ancora alcun job.
SELECT '10_JOB_PER_ESITO' AS controllo, stati.esito,
       COUNT(j.id) AS esecuzioni,
       COALESCE(SUM(j.id IS NOT NULL AND j.concluso_il IS NULL),0) AS senza_data_chiusura
FROM (
    SELECT 'in_corso' AS esito
    UNION ALL SELECT 'ok'
    UNION ALL SELECT 'errore'
) AS stati
LEFT JOIN esecuzioni_job j ON j.esito=stati.esito
GROUP BY stati.esito
ORDER BY stati.esito;

-- Informazione tecnica per valutare anomalie pregresse senza leggere dati
-- di alunni, email o famiglie. Un job in_corso va verificato prima del deploy.
SELECT '11_JOB_DA_VERIFICARE' AS controllo,
       p.nome AS plesso,
       j.data AS data_servizio,
       j.esito,
       j.avviato_il,
       j.concluso_il
FROM esecuzioni_job j
JOIN plessi p ON p.id=j.plesso_id
WHERE j.esito IN ('errore','in_corso')
ORDER BY j.data DESC, j.id DESC
LIMIT 30;

COMMIT;
