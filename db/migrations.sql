-- Migrações do banco de dados
-- Execute estes scripts em bancos de dados já existentes

-- Migration 001: Adicionar coluna prg_numero_op à tabela prg_programas
ALTER TABLE prg_programas ADD COLUMN IF NOT EXISTS prg_numero_op VARCHAR(120) NULL UNIQUE;

-- Migration 002: Adicionar coluna prg_itens_op na tabela prg_itens
ALTER TABLE prg_itens ADD COLUMN IF NOT EXISTS prg_itens_op VARCHAR(120) NULL;

-- Migration 003: Adicionar a parada consolidada do CODI no realizado
ALTER TABLE realizado_2026_excel
  ADD COLUMN IF NOT EXISTS parada_nomeParada VARCHAR(120) NULL AFTER fim_evento;
