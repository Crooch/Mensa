-- Richiesta di acquisto idempotente: protegge dal doppio clic e dai retry HTTP.
-- Additiva: NULL ammesso per i vecchi movimenti e le integrazioni precedenti.
-- MariaDB 11.8: ADD COLUMN/INDEX IF NOT EXISTS rende la migration ri-eseguibile.
ALTER TABLE movimenti_crediti
  ADD COLUMN IF NOT EXISTS richiesta_id VARCHAR(64) NULL;

ALTER TABLE movimenti_crediti
  ADD UNIQUE INDEX IF NOT EXISTS uk_mov_richiesta_id (richiesta_id);
