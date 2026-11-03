-- Solo nuove strutture. Nessuna configurazione o rettifica dei dati reali.
CREATE TABLE IF NOT EXISTS audit_amministrativo (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 utente_id INT UNSIGNED NOT NULL,
 operazione VARCHAR(80) NOT NULL,
 riferimento VARCHAR(120) NOT NULL,
 precedente LONGTEXT NULL,
 successivo LONGTEXT NULL,
 motivazione VARCHAR(255) NOT NULL,
 registrato_il DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (utente_id) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS impostazioni_servizio_plesso (
 plesso_id INT UNSIGNED PRIMARY KEY,
 ordine_scolastico ENUM('infanzia','primaria','secondaria') NULL,
 primo_giorno_sospensione DATE NULL,
 FOREIGN KEY (plesso_id) REFERENCES plessi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS pasti_personale_regole (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 plesso_id INT UNSIGNED NOT NULL,
 dal DATE NOT NULL,
 giorno TINYINT UNSIGNED NOT NULL,
 categoria ENUM('insegnanti','ata','assistenti','asacom') NOT NULL,
 tipologia_menu_id INT UNSIGNED NOT NULL,
 quantita SMALLINT UNSIGNED NOT NULL,
 UNIQUE KEY regola (plesso_id,dal,giorno,categoria,tipologia_menu_id),
 FOREIGN KEY (plesso_id) REFERENCES plessi(id),
 FOREIGN KEY (tipologia_menu_id) REFERENCES tipologie_menu(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS pasti_personale_giorni (
 plesso_id INT UNSIGNED NOT NULL,
 data DATE NOT NULL,
 righe LONGTEXT NOT NULL,
 consolidato TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY (plesso_id,data),
 FOREIGN KEY (plesso_id) REFERENCES plessi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS presenze_tipologie_storiche (
 presenza_id INT UNSIGNED PRIMARY KEY,
 tipologia_menu_id INT UNSIGNED NOT NULL,
 tipologia_nome VARCHAR(120) NOT NULL,
 FOREIGN KEY (presenza_id) REFERENCES presenze_giornaliere(id),
 FOREIGN KEY (tipologia_menu_id) REFERENCES tipologie_menu(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
