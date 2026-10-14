-- Nome del modello nella revisione: anche le etichette storiche sono congelate.
-- Si tratta solo di tabelle del nuovo catalogo, non dei menu già somministrati.
ALTER TABLE menu_settimanali_revisioni
  ADD COLUMN IF NOT EXISTS nome VARCHAR(150) NULL AFTER valido_dal;

UPDATE menu_settimanali_revisioni r
JOIN menu_settimanali m ON m.id = r.menu_id
SET r.nome = m.nome
WHERE r.nome IS NULL;
