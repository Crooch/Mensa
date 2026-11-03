-- Mensa Caltavuturo: UPGRADE SCHEMA-ONLY (ottobre 2026).
-- Sviluppato per il DB preesistente con schema iniziale 001.
-- NON contiene seed, import, UPDATE, INSERT, DELETE, TRUNCATE o DROP.
-- Va verificato su copia protetta del DB reale prima di qualsiasi uso.
-- Prerequisiti: backup completo, finestra di manutenzione, diff schema,
-- esito preflight e conferma esplicita. NON e' un comando da eseguire adesso.
-- NON applicare 001_schema.sql alla produzione. Nessun dato di staging.
-- N.B. CREATE IF NOT EXISTS non verifica la compatibilita' di tabelle
-- gia' esistenti: in caso di schema differente, STOP e analisi manuale.

-- =============== database/migrations/002_calendario_mensa.sql (solo DDL) ===============
-- Calendario del servizio mensa per plesso.
-- Migration additiva: nessuna modifica o cancellazione ai dati esistenti.
-- IMPORTANTE: applicare prima del deploy del codice che utilizza il calendario.

CREATE TABLE IF NOT EXISTS calendari_servizio (
  plesso_id INT UNSIGNED NOT NULL,
  data_inizio DATE NOT NULL,
  data_fine DATE NULL,
  lunedi TINYINT(1) NOT NULL DEFAULT 0,
  martedi TINYINT(1) NOT NULL DEFAULT 0,
  mercoledi TINYINT(1) NOT NULL DEFAULT 0,
  giovedi TINYINT(1) NOT NULL DEFAULT 0,
  venerdi TINYINT(1) NOT NULL DEFAULT 0,
  sabato TINYINT(1) NOT NULL DEFAULT 0,
  domenica TINYINT(1) NOT NULL DEFAULT 0,
  escludi_festivita_nazionali TINYINT(1) NOT NULL DEFAULT 1,
  aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (plesso_id),
  CONSTRAINT fk_cal_servizio_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eccezioni_calendario (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plesso_id INT UNSIGNED NOT NULL,
  giorno DATE NOT NULL,
  servizio_attivo TINYINT(1) NOT NULL,
  motivazione VARCHAR(255) NOT NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_eccezione_plesso_giorno (plesso_id, giorno),
  CONSTRAINT fk_eccezione_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============== database/migrations/003_richieste_pasta_bianco.sql (solo DDL) ===============
-- Richieste giornaliere pasta in bianco, indipendenti dalla presenza.
-- Migration additiva: nessuna modifica ai movimenti economici o allo storico.
CREATE TABLE IF NOT EXISTS richieste_pasta_bianco (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bambino_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  richiesta TINYINT(1) NOT NULL DEFAULT 1,
  utente_id INT UNSIGNED NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pasta_bambino_data (bambino_id, data),
  KEY idx_pasta_data (data, richiesta),
  CONSTRAINT fk_pasta_bambino FOREIGN KEY (bambino_id) REFERENCES bambini (id),
  CONSTRAINT fk_pasta_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============== database/migrations/004_audit_operazioni_presenze.sql (solo DDL) ===============
-- Log append-only delle operazioni su presenze e richieste speciali.
-- Non modifica ticket, saldi o presenze storiche.
CREATE TABLE IF NOT EXISTS log_operazioni_presenze (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bambino_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  operazione VARCHAR(40) NOT NULL,
  valore_precedente VARCHAR(32) NULL,
  valore_nuovo VARCHAR(32) NULL,
  motivazione VARCHAR(255) NULL,
  utente_id INT UNSIGNED NOT NULL,
  registrato_il DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_log_presenza_bambino_data (bambino_id, data, id),
  KEY idx_log_presenza_utente_data (utente_id, registrato_il),
  CONSTRAINT fk_log_presenza_bambino FOREIGN KEY (bambino_id) REFERENCES bambini (id),
  CONSTRAINT fk_log_presenza_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============== database/migrations/005_idempotenza_acquisti.sql (solo DDL) ===============
-- Richiesta di acquisto idempotente: protegge dal doppio clic e dai retry HTTP.
-- Additiva: NULL ammesso per i vecchi movimenti e le integrazioni precedenti.
-- MariaDB 11.8: ADD COLUMN/INDEX IF NOT EXISTS rende la migration ri-eseguibile.
ALTER TABLE movimenti_crediti
  ADD COLUMN IF NOT EXISTS richiesta_id VARCHAR(64) NULL;

ALTER TABLE movimenti_crediti
  ADD UNIQUE INDEX IF NOT EXISTS uk_mov_richiesta_id (richiesta_id);

-- =============== database/migrations/006_menu_settimanali.sql (solo DDL) ===============
-- Catalogo modelli settimanali: nessuna modifica allo storico dei menu giornalieri.
-- Revisioni e assegnazioni append-only, efficaci dal giorno successivo.
CREATE TABLE IF NOT EXISTS menu_settimanali (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(150) NOT NULL,
  tipologia_menu_id INT UNSIGNED NOT NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_menu_settimanali_tipo (tipologia_menu_id),
  CONSTRAINT fk_msw_tipo FOREIGN KEY (tipologia_menu_id) REFERENCES tipologie_menu (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_settimanali_revisioni (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_id INT UNSIGNED NOT NULL,
  valido_dal DATE NOT NULL,
  giorni_json TEXT NOT NULL,
  utente_id INT UNSIGNED NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mswr_data (menu_id, valido_dal, id),
  CONSTRAINT fk_mswr_menu FOREIGN KEY (menu_id) REFERENCES menu_settimanali (id),
  CONSTRAINT fk_mswr_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_settimanali_assegnazioni (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plesso_id INT UNSIGNED NOT NULL,
  tipologia_menu_id INT UNSIGNED NOT NULL,
  menu_id INT UNSIGNED NULL,
  valido_dal DATE NOT NULL,
  utente_id INT UNSIGNED NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mswa_plesso_tipo_data (plesso_id, tipologia_menu_id, valido_dal, id),
  CONSTRAINT fk_mswa_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id),
  CONSTRAINT fk_mswa_tipo FOREIGN KEY (tipologia_menu_id) REFERENCES tipologie_menu (id),
  CONSTRAINT fk_mswa_menu FOREIGN KEY (menu_id) REFERENCES menu_settimanali (id),
  CONSTRAINT fk_mswa_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============== database/migrations/007_storico_nomi_modelli.sql (solo DDL) ===============
-- Nome del modello nella revisione: anche le etichette storiche sono congelate.
-- Si tratta solo di tabelle del nuovo catalogo, non dei menu già somministrati.
ALTER TABLE menu_settimanali_revisioni
  ADD COLUMN IF NOT EXISTS nome VARCHAR(150) NULL AFTER valido_dal;

-- =============== database/migrations/008_foto_menu_settimanali.sql (solo DDL) ===============
-- Foto immutabili per ogni giorno dei modelli settimanali.
-- Ogni revisione conserva i propri percorsi: le modifiche future non
-- sovrascrivono le immagini mostrate nello storico delle giornate.
ALTER TABLE menu_settimanali_revisioni
  ADD COLUMN IF NOT EXISTS foto_json TEXT NULL AFTER giorni_json;

-- =============== database/migrations/009_intervalli_calendario.sql (solo DDL) ===============
-- Estensione additiva delle eccezioni: il giorno originale resta la data
-- iniziale, giorno_fine opzionale definisce una chiusura/apertura per periodo.
-- Le eccezioni storiche con giorno_fine NULL restano valide per un solo giorno.
-- MariaDB 11.8: ripetibile senza modificare le righe preesistenti.
ALTER TABLE eccezioni_calendario
  ADD COLUMN IF NOT EXISTS giorno_fine DATE NULL AFTER giorno;
