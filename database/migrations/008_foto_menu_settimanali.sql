-- Foto immutabili per ogni giorno dei modelli settimanali.
-- Ogni revisione conserva i propri percorsi: le modifiche future non
-- sovrascrivono le immagini mostrate nello storico delle giornate.
ALTER TABLE menu_settimanali_revisioni
  ADD COLUMN IF NOT EXISTS foto_json TEXT NULL AFTER giorni_json;
