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

-- Inizializzazione esplicita dei soli plessi per cui il Comune ha indicato
-- i giorni di servizio. Gli altri rimangono BLOCCATI fino a configurazione.
-- Ripetere la migration non sovrascrive le configurazioni gia' modificate.
INSERT INTO calendari_servizio
  (plesso_id, data_inizio, lunedi, martedi, mercoledi, giovedi, venerdi, sabato, domenica)
SELECT id, '2026-10-19', 1, 1, 1, 1, 1, 0, 0
FROM plessi
WHERE nome = 'Scuola dell''Infanzia - Via Catania'
ON DUPLICATE KEY UPDATE plesso_id = plesso_id;

INSERT INTO calendari_servizio
  (plesso_id, data_inizio, lunedi, martedi, mercoledi, giovedi, venerdi, sabato, domenica)
SELECT id, '2026-10-19', 1, 0, 0, 1, 0, 0, 0
FROM plessi
WHERE nome = 'Scuola Secondaria di I grado'
ON DUPLICATE KEY UPDATE plesso_id = plesso_id;
