-- Migration 004: classificar paradas de setup no realizado importado do CODI
ALTER TABLE realizado_2026_excel
  ADD COLUMN IF NOT EXISTS parada_nomeParada VARCHAR(120) NULL AFTER fim_evento;
