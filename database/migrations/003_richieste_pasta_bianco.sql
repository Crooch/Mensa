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
