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
