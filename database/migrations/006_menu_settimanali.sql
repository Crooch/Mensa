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
