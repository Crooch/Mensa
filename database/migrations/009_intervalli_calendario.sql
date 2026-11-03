-- Estensione additiva delle eccezioni: il giorno originale resta la data
-- iniziale, giorno_fine opzionale definisce una chiusura/apertura per periodo.
-- Le eccezioni storiche con giorno_fine NULL restano valide per un solo giorno.
-- MariaDB 11.8: ripetibile senza modificare le righe preesistenti.
ALTER TABLE eccezioni_calendario
  ADD COLUMN IF NOT EXISTS giorno_fine DATE NULL AFTER giorno;
