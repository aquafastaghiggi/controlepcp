ALTER TABLE realizado_2026_excel
    ADD COLUMN IF NOT EXISTS setup_duracao_minutos DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER parada_nomeParada,
    ADD COLUMN IF NOT EXISTS setup_eventos_count INT NOT NULL DEFAULT 0 AFTER setup_duracao_minutos;
