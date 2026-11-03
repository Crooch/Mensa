-- Metadato aggiuntivo; i conteggi precedenti restano invariati.
ALTER TABLE pasti_personale_giorni ADD COLUMN IF NOT EXISTS origine ENUM('registrato','programmazione','variazione') NOT NULL DEFAULT 'registrato';
