-- =====================================================================
-- Sistema di Gestione Mensa Scolastica - Comune di Caltavuturo
-- Migration 001: schema iniziale (MySQL 8)
-- Charset utf8mb4 / InnoDB / nomi tabelle e campi in italiano (dominio)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Impostazioni globali (SMTP, email protocollo, chiave scheduler...)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS impostazioni (
  chiave        VARCHAR(64)  NOT NULL,
  valore        TEXT         NULL,
  aggiornato_il DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (chiave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Plessi: ogni plesso ha propri parametri economici e proprio cutoff
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plessi (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome                    VARCHAR(120)  NOT NULL,
  tariffa_base_pasto      DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  fido_buoni              INT           NOT NULL DEFAULT 0,
  cutoff_orario           TIME          NOT NULL DEFAULT '09:30:00',
  dirigente_email         VARCHAR(190)  NULL,
  email_protocollo        VARCHAR(190)  NULL, -- se NULL si usa impostazione globale
  cancellato_logicamente  TINYINT(1)    NOT NULL DEFAULT 0,
  data_cancellazione      DATETIME      NULL,
  creato_il               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Utenti: create SOLO dall'ufficio comunale (nessuna auto-registrazione).
-- username = stringa libera univoca (spesso una email, ma non validata come tale)
-- plesso_id valorizzato per le utenze di ruolo "scuola" (una o piu' per plesso)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS utenti (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ruolo         ENUM('admin','operatore','scuola','catering','genitore') NOT NULL,
  username      VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nome_completo VARCHAR(190) NULL,
  plesso_id     INT UNSIGNED NULL,
  attivo        TINYINT(1)   NOT NULL DEFAULT 1,
  creato_il     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_utenti_username (username),
  KEY idx_utenti_plesso (plesso_id),
  CONSTRAINT fk_utenti_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Token di autenticazione per le API (Bearer). Pensati anche per app nativa.
CREATE TABLE IF NOT EXISTS api_token (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utente_id  INT UNSIGNED NOT NULL,
  token      CHAR(64)     NOT NULL,
  creato_il  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  scade_il   DATETIME     NOT NULL,
  ultimo_uso DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_api_token (token),
  KEY idx_api_token_utente (utente_id),
  CONSTRAINT fk_api_token_utente FOREIGN KEY (utente_id) REFERENCES utenti (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Anagrafiche
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS genitori (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utente_id              INT UNSIGNED NULL,
  nome                   VARCHAR(80)  NOT NULL,
  cognome                VARCHAR(80)  NOT NULL,
  telefono               VARCHAR(40)  NOT NULL,          -- obbligatorio
  email                  VARCHAR(190) NULL,              -- facoltativa (escalation debito)
  note                   VARCHAR(255) NULL,
  cancellato_logicamente TINYINT(1)   NOT NULL DEFAULT 0,
  data_cancellazione     DATETIME     NULL,
  creato_il              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_genitori_utente (utente_id),
  KEY idx_genitori_cognome (cognome, nome),
  CONSTRAINT fk_genitori_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipologie_menu (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome                   VARCHAR(120) NOT NULL,
  cancellata_logicamente TINYINT(1)   NOT NULL DEFAULT 0,
  data_cancellazione     DATETIME     NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un solo riferimento a tipologia_menu per bambino: per esigenze combinate
-- l'Admin crea una nuova tipologia dedicata (scelta di analisi, non m-a-m).
CREATE TABLE IF NOT EXISTS bambini (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome                   VARCHAR(80)  NOT NULL,
  cognome                VARCHAR(80)  NOT NULL,
  genitore_id            INT UNSIGNED NOT NULL,
  tipologia_menu_id      INT UNSIGNED NOT NULL,
  cancellato_logicamente TINYINT(1)   NOT NULL DEFAULT 0,
  data_cancellazione     DATETIME     NULL,
  creato_il              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bambini_genitore (genitore_id),
  CONSTRAINT fk_bambini_genitore FOREIGN KEY (genitore_id) REFERENCES genitori (id),
  CONSTRAINT fk_bambini_tipologia FOREIGN KEY (tipologia_menu_id) REFERENCES tipologie_menu (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classi_sezioni (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plesso_id              INT UNSIGNED NOT NULL,
  nome                   VARCHAR(60)  NOT NULL,
  anno_scolastico        VARCHAR(9)   NOT NULL, -- es. 2025/2026
  cancellata_logicamente TINYINT(1)   NOT NULL DEFAULT 0,
  data_cancellazione     DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_classi_plesso (plesso_id, anno_scolastico),
  CONSTRAINT fk_classi_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STORICIZZAZIONE: mai UPDATE della classe. Il cambio classe/plesso chiude il
-- record corrente (data_fine) e ne apre uno nuovo. Cosi' ogni presenza/pasto di
-- una data D e' riconducibile all'assegnazione attiva in D.
CREATE TABLE IF NOT EXISTS assegnazioni_classe (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bambino_id        INT UNSIGNED NOT NULL,
  classe_sezione_id INT UNSIGNED NOT NULL,
  data_inizio       DATE         NOT NULL,
  data_fine         DATE         NULL,     -- NULL = assegnazione corrente
  creato_il         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_assegnazioni_bambino (bambino_id, data_inizio),
  KEY idx_assegnazioni_classe (classe_sezione_id),
  CONSTRAINT fk_assegnazioni_bambino FOREIGN KEY (bambino_id) REFERENCES bambini (id),
  CONSTRAINT fk_assegnazioni_classe FOREIGN KEY (classe_sezione_id) REFERENCES classi_sezioni (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tariffe e crediti
-- ---------------------------------------------------------------------
-- Granularita' (genitore, plesso). L'override attivo alla data D e' quello con
-- data_inserimento massima <= D. Nessuna data_fine: la storia e' la sequenza.
CREATE TABLE IF NOT EXISTS override_tariffe (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  genitore_id      INT UNSIGNED NOT NULL,
  plesso_id        INT UNSIGNED NOT NULL,
  tariffa          DECIMAL(6,2) NOT NULL,
  data_inserimento DATE         NOT NULL,
  motivazione      TEXT         NOT NULL,
  utente_id        INT UNSIGNED NULL,
  creato_il        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_override_gp (genitore_id, plesso_id, data_inserimento),
  CONSTRAINT fk_override_genitore FOREIGN KEY (genitore_id) REFERENCES genitori (id),
  CONSTRAINT fk_override_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id),
  CONSTRAINT fk_override_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pool crediti condiviso fra tutti i figli di un genitore in un plesso.
-- Saldo in BUONI (intero), puo' essere negativo: nessun blocco del servizio.
CREATE TABLE IF NOT EXISTS pool_crediti (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  genitore_id   INT UNSIGNED NOT NULL,
  plesso_id     INT UNSIGNED NOT NULL,
  saldo         INT          NOT NULL DEFAULT 0,
  aggiornato_il DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pool_gp (genitore_id, plesso_id),
  CONSTRAINT fk_pool_genitore FOREIGN KEY (genitore_id) REFERENCES genitori (id),
  CONSTRAINT fk_pool_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimenti_crediti (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id       INT UNSIGNED NOT NULL,
  tipo          ENUM('acquisto','consumo_pasto','rettifica_manuale') NOT NULL,
  delta         INT          NOT NULL,
  data          DATE         NOT NULL,
  bambino_id    INT UNSIGNED NULL,          -- valorizzato per i consumi
  importo_euro  DECIMAL(8,2) NULL,          -- valorizzato solo per gli acquisti
  utente_id     INT UNSIGNED NULL,          -- NULL per i consumi automatici del job
  note          VARCHAR(255) NULL,
  creato_il     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mov_pool (pool_id, data),
  KEY idx_mov_bambino (bambino_id, data),
  CONSTRAINT fk_mov_pool FOREIGN KEY (pool_id) REFERENCES pool_crediti (id),
  CONSTRAINT fk_mov_bambino FOREIGN KEY (bambino_id) REFERENCES bambini (id),
  CONSTRAINT fk_mov_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Presenze
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS assenze_massa (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  classe_sezione_id     INT UNSIGNED NOT NULL,
  data                  DATE         NOT NULL,
  motivo                VARCHAR(255) NOT NULL,
  utente_id             INT UNSIGNED NULL,
  timestamp_inserimento DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_assenze_massa (classe_sezione_id, data),
  CONSTRAINT fk_am_classe FOREIGN KEY (classe_sezione_id) REFERENCES classi_sezioni (id),
  CONSTRAINT fk_am_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tre fonti conservate separatamente + stato effettivo calcolato.
-- congelato = 1 dopo il cutoff: da quel momento dichiarazioni/override tardivi
-- vengono salvati ma marcati fuori_cutoff e non alterano stato_effettivo.
CREATE TABLE IF NOT EXISTS presenze_giornaliere (
  id                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bambino_id                INT UNSIGNED NOT NULL,
  data                      DATE         NOT NULL,
  default_calcolato         ENUM('presente','assente') NOT NULL DEFAULT 'presente',
  dichiarazione_genitore    ENUM('presente','assente') NULL,
  dichiarazione_timestamp   DATETIME     NULL,
  dichiarazione_utente_id   INT UNSIGNED NULL,
  override_scuola           ENUM('presente','assente') NULL,
  override_timestamp        DATETIME     NULL,
  override_utente_id        INT UNSIGNED NULL,
  override_motivazione      VARCHAR(255) NULL,
  stato_effettivo           ENUM('presente','assente') NOT NULL DEFAULT 'presente',
  fuori_cutoff              TINYINT(1)   NOT NULL DEFAULT 0,
  congelato                 TINYINT(1)   NOT NULL DEFAULT 0,
  consumo_registrato        TINYINT(1)   NOT NULL DEFAULT 0,
  aggiornato_il             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_presenza_bambino_data (bambino_id, data),
  KEY idx_presenza_data (data, stato_effettivo),
  CONSTRAINT fk_pres_bambino FOREIGN KEY (bambino_id) REFERENCES bambini (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catering: menu del giorno e feedback
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS menu_giornalieri (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plesso_id         INT UNSIGNED NOT NULL,
  tipologia_menu_id INT UNSIGNED NOT NULL,
  data              DATE         NOT NULL,
  descrizione       TEXT         NULL,
  foto_path         VARCHAR(255) NULL,
  utente_id         INT UNSIGNED NULL,
  aggiornato_il     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_menu_ptd (plesso_id, tipologia_menu_id, data),
  CONSTRAINT fk_menu_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id),
  CONSTRAINT fk_menu_tipologia FOREIGN KEY (tipologia_menu_id) REFERENCES tipologie_menu (id),
  CONSTRAINT fk_menu_utente FOREIGN KEY (utente_id) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sempre storicizzati, mai sovrascritti. Almeno uno tra voto e commento.
CREATE TABLE IF NOT EXISTS feedback_pasti (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bambino_id        INT UNSIGNED NOT NULL,
  data              DATE         NOT NULL,
  tipologia_menu_id INT UNSIGNED NOT NULL,
  voto              TINYINT      NULL,
  commento          VARCHAR(500) NULL,
  utente_id         INT UNSIGNED NULL,
  creato_il         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_feedback_data (data),
  KEY idx_feedback_bambino (bambino_id, data),
  CONSTRAINT fk_fb_bambino FOREIGN KEY (bambino_id) REFERENCES bambini (id),
  CONSTRAINT fk_fb_tipologia FOREIGN KEY (tipologia_menu_id) REFERENCES tipologie_menu (id),
  CONSTRAINT chk_fb_voto CHECK (voto IS NULL OR (voto BETWEEN 1 AND 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Escalation debito
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS destinatari_fissi_email (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  etichetta VARCHAR(120) NOT NULL,
  email     VARCHAR(190) NOT NULL,
  attivo    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modelli_email_sollecito (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plesso_id     INT UNSIGNED NOT NULL,
  oggetto       VARCHAR(190) NOT NULL,
  testo         TEXT         NOT NULL,
  attivo        TINYINT(1)   NOT NULL DEFAULT 1,
  aggiornato_il DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_modello_plesso (plesso_id),
  CONSTRAINT fk_modello_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_solleciti_inviati (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  genitore_id             INT UNSIGNED NOT NULL,
  plesso_id               INT UNSIGNED NOT NULL,
  data_invio              DATE         NOT NULL,
  saldo_al_momento_invio  INT          NOT NULL,
  destinatari             TEXT         NULL,
  esito                   ENUM('inviato','errore') NOT NULL DEFAULT 'inviato',
  errore                  VARCHAR(500) NULL,
  creato_il               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sollecito_giorno (genitore_id, plesso_id, data_invio),
  CONSTRAINT fk_sol_genitore FOREIGN KEY (genitore_id) REFERENCES genitori (id),
  CONSTRAINT fk_sol_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Esecuzioni del job giornaliero (una per plesso/data): idempotenza
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS esecuzioni_job (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plesso_id   INT UNSIGNED NOT NULL,
  data        DATE         NOT NULL,
  avviato_il  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluso_il DATETIME     NULL,
  esito       ENUM('in_corso','ok','errore') NOT NULL DEFAULT 'in_corso',
  dettaglio   TEXT         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_job_plesso_data (plesso_id, data),
  CONSTRAINT fk_job_plesso FOREIGN KEY (plesso_id) REFERENCES plessi (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
