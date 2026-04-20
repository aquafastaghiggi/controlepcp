-- Migration 006: Preservacao de eventos brutos do CODI para analise complementar
-- Mantem o agregado principal em realizado_2026_excel intacto e grava um detalhe
-- por evento/op em realizado_2026_eventos.

CREATE TABLE IF NOT EXISTS realizado_2026_eventos (
  evt_id INT AUTO_INCREMENT PRIMARY KEY,
  evt_chave_externa VARCHAR(160) NOT NULL,
  evt_codigo_evento BIGINT NULL,
  data_evento DATE NOT NULL,
  ordem_op VARCHAR(120) NOT NULL,
  quantidade DECIMAL(14,4) NOT NULL DEFAULT 0,
  inicio_evento DATETIME NULL,
  fim_evento DATETIME NULL,
  duracao_evento_minutos DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado_evento VARCHAR(40) NULL,
  parada_nomeParada VARCHAR(120) NULL,
  parada_tipo_nome VARCHAR(120) NULL,
  setup_duracao_minutos DECIMAL(10,2) NOT NULL DEFAULT 0,
  setup_eventos_count INT NOT NULL DEFAULT 0,
  payload_json LONGTEXT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_realizado_evento_chave (evt_chave_externa),
  INDEX idx_realizado_evento_data_op (data_evento, ordem_op),
  INDEX idx_realizado_evento_op (ordem_op),
  INDEX idx_realizado_evento_parada (parada_nomeParada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalhe bruto dos eventos CODI por OP para apoio de analise complementar.';
