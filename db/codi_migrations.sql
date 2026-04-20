-- ============================================================================
-- Migrações para Integração CODI - Sistema ControlePCP Sandbox
-- Data: 2026-04-06
-- Descrição: Tabelas para sincronização de dados e cálculo de eficiência
-- ============================================================================

-- 1) Configuração da conexão CODI
CREATE TABLE IF NOT EXISTS cdi_configuracao (
  cdi_id INT AUTO_INCREMENT PRIMARY KEY,
  cdi_servidor_url VARCHAR(255) NOT NULL,
  cdi_usuario VARCHAR(100) NOT NULL,
  cdi_senha VARCHAR(255) NOT NULL COMMENT 'Armazenada criptografada',
  cdi_codename_empresa VARCHAR(100) NOT NULL,
  cdi_ativo TINYINT(1) NOT NULL DEFAULT 1,
  cdi_ultima_sincronizacao DATETIME DEFAULT NULL,
  cdi_timeout_ms INT DEFAULT 30000,
  cdi_retry_count TINYINT DEFAULT 3,
  cdi_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cdi_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_cdi_servidor (cdi_servidor_url, cdi_codename_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Armazena credenciais e URLs de conexão com sistema CODI';

-- ============================================================================
-- 2) Sincronização de Eventos do CODI
-- ============================================================================

CREATE TABLE IF NOT EXISTS cdi_eventos (
  cdi_evento_id INT AUTO_INCREMENT PRIMARY KEY,
  cdi_codigo_evento_codi VARCHAR(100) NOT NULL COMMENT 'ID único do evento no CODI',
  cdi_codigo_ordem_producao VARCHAR(50) NOT NULL,
  cdi_codigo_item VARCHAR(100) NOT NULL COMMENT 'SKU no CODI',
  cdi_nome_item VARCHAR(255),
  cdi_quantidade_produzida DECIMAL(15,4) NOT NULL,
  cdi_data_evento DATETIME NOT NULL COMMENT 'Quando o evento ocorreu no CODI',
  cdi_recurso_nome VARCHAR(150),
  cdi_tipo_evento ENUM('PRODUCAO', 'SETUP', 'REJEITO', 'PARADA', 'OUTRO') DEFAULT 'PRODUCAO',
  cdi_unidade_medida VARCHAR(20),
  cdi_sync_id VARCHAR(100) COMMENT 'ID do batch de sincronização',
  cdi_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cdi_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY ux_cdi_evento_codi (cdi_codigo_evento_codi),
  INDEX idx_cdi_data_evento (cdi_data_evento),
  INDEX idx_cdi_ordem (cdi_codigo_ordem_producao),
  INDEX idx_cdi_item (cdi_codigo_item),
  INDEX idx_cdi_tipo (cdi_tipo_evento),
  INDEX idx_cdi_sync_id (cdi_sync_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de eventos de produção sincronizados do CODI';

-- ============================================================================
-- 3) Performance em Tempo Real
-- ============================================================================

CREATE TABLE IF NOT EXISTS cdi_performance (
  cdi_perf_id INT AUTO_INCREMENT PRIMARY KEY,
  cdi_codigo_recurso VARCHAR(100) NOT NULL,
  cdi_nome_recurso VARCHAR(150),
  cdi_timestamp_coleta DATETIME NOT NULL COMMENT 'Quando os dados foram coletados do CODI',
  cdi_disponibilidade DECIMAL(5,2) COMMENT 'Percentual 0-100',
  cdi_performance DECIMAL(5,2) COMMENT 'Percentual 0-100',
  cdi_oee DECIMAL(5,2) COMMENT 'Overall Equipment Effectiveness 0-100',
  cdi_estado_atual ENUM('PRODUCAO', 'PARADO', 'SETUP', 'MANUTENCAO', 'DESCONHECIDO') DEFAULT 'DESCONHECIDO',
  cdi_ordem_producao_current VARCHAR(50),
  cdi_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cdi_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_cdi_recurso (cdi_codigo_recurso),
  INDEX idx_cdi_timestamp (cdi_timestamp_coleta),
  INDEX idx_cdi_estado (cdi_estado_atual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Snapshot de performance de recursos capturados do CODI';

-- ============================================================================
-- 4) Log de Sincronizações
-- ============================================================================

CREATE TABLE IF NOT EXISTS cdi_sincronizacao_log (
  cdi_sync_log_id INT AUTO_INCREMENT PRIMARY KEY,
  cdi_sync_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'ID único desta sincronização',
  cdi_timestamp_inicio DATETIME NOT NULL,
  cdi_timestamp_fim DATETIME,
  cdi_endpoint_consultado VARCHAR(255) NOT NULL,
  cdi_status ENUM('SUCESSO', 'ERRO', 'PENDENTE', 'PARCIAL') DEFAULT 'PENDENTE',
  cdi_registros_sincronizados INT DEFAULT 0,
  cdi_registros_duplicados INT DEFAULT 0,
  cdi_mensagem_erro TEXT,
  cdi_duracao_ms INT COMMENT 'Tempo total em milissegundos',
  cdi_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_cdi_status (cdi_status),
  INDEX idx_cdi_timestamp_inicio (cdi_timestamp_inicio),
  INDEX idx_cdi_endpoint (cdi_endpoint_consultado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de todas as sincronizações realizadas com CODI';

-- ============================================================================
-- 5) Mapeamento de SKUs entre sistemas
-- ============================================================================

CREATE TABLE IF NOT EXISTS cdi_sku_mapping (
  cdi_sku_map_id INT AUTO_INCREMENT PRIMARY KEY,
  cdi_sku_codi VARCHAR(100) NOT NULL COMMENT 'SKU identificador no CODI',
  cdi_sku_controlepcp VARCHAR(100) NOT NULL COMMENT 'SKU no ControlePCP',
  cdi_nome_produto VARCHAR(255),
  cdi_unidade_medida_origem VARCHAR(20),
  cdi_unidade_medida_destino VARCHAR(20),
  cdi_fator_conversao DECIMAL(10,4) DEFAULT 1.0000 COMMENT 'Multiplicador para converter origem → destino',
  cdi_ativo TINYINT(1) DEFAULT 1,
  cdi_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cdi_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY ux_cdi_sku_codi (cdi_sku_codi),
  INDEX idx_cdi_sku_controlepcp (cdi_sku_controlepcp),
  INDEX idx_cdi_ativo (cdi_ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mapeamento entre SKUs do CODI e ControlePCP';

-- ============================================================================
-- 6) CORE: Cálculo de Eficiência (Previsto vs Realizado)
-- ============================================================================

CREATE TABLE IF NOT EXISTS cdi_eficiencia_medicao (
  cdi_efic_id INT AUTO_INCREMENT PRIMARY KEY,
  cdi_data_medicao DATE NOT NULL COMMENT 'Data de referência',
  cdi_codigo_ordem_producao VARCHAR(50) NOT NULL,
  cdi_codigo_item VARCHAR(100) NOT NULL,
  cdi_sku_controlepcp VARCHAR(100) COMMENT 'SKU mapeado',
  
  -- Dados Programados (do ControlePCP)
  cdi_quantidade_programada DECIMAL(15,4) DEFAULT 0,
  cdi_tempo_producao_programado_min INT DEFAULT 0 COMMENT 'Tempo em minutos',
  cdi_velocidade_programada DECIMAL(10,4) COMMENT 'qtd/min',
  
  -- Dados Reais (do CODI)
  cdi_quantidade_realizada DECIMAL(15,4) DEFAULT 0,
  cdi_tempo_producao_real_min INT DEFAULT 0 COMMENT 'Tempo em minutos',
  cdi_velocidade_real DECIMAL(10,4) COMMENT 'qtd/min',
  
  -- Cálculos de Eficiência
  cdi_desvio_quantidade DECIMAL(15,4) COMMENT 'Realizado - Programado',
  cdi_desvio_percentual DECIMAL(8,2) COMMENT 'Percentual (realizado/programado)*100',
  cdi_eficiencia DECIMAL(5,2) COMMENT 'Métrica final de eficiência %',
  cdi_status ENUM('ON_TIME', 'ATRASADO', 'ADIANTADO', 'NAO_PRODUZIDO') DEFAULT 'NAO_PRODUZIDO',
  cdi_margem_dias INT COMMENT 'Dias de diferença (+/- quanto está de prazo)',
  
  -- Classificação de Qualidade
  cdi_classificacao ENUM('EXCELENTE', 'BOM', 'ACEITAVEL', 'RUIM', 'CRITICO') DEFAULT 'ACEITAVEL',
  
  cdi_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cdi_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_cdi_data_medicao (cdi_data_medicao),
  INDEX idx_cdi_ordem (cdi_codigo_ordem_producao),
  INDEX idx_cdi_item (cdi_codigo_item),
  INDEX idx_cdi_status (cdi_status),
  INDEX idx_cdi_classificacao (cdi_classificacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cruzamento de dados: Previsto (ControlePCP) vs Realizado (CODI)';

-- ============================================================================
-- 7) Histórico de mudanças de status
-- ============================================================================

CREATE TABLE IF NOT EXISTS cdi_eficiencia_historico (
  cdi_hist_id INT AUTO_INCREMENT PRIMARY KEY,
  cdi_efic_id INT NOT NULL,
  cdi_status_anterior ENUM('ON_TIME', 'ATRASADO', 'ADIANTADO', 'NAO_PRODUZIDO'),
  cdi_status_novo ENUM('ON_TIME', 'ATRASADO', 'ADIANTADO', 'NAO_PRODUZIDO'),
  cdi_motivo VARCHAR(255),
  cdi_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  CONSTRAINT fk_cdi_hist_eficiencia FOREIGN KEY (cdi_efic_id) REFERENCES cdi_eficiencia_medicao(cdi_efic_id) ON DELETE CASCADE,
  INDEX idx_cdi_efic_id (cdi_efic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoria de mudanças de status de eficiência';

-- ============================================================================
-- 8) Resumo Diário de Eficiência (cache para performance)
-- ============================================================================

CREATE TABLE IF NOT EXISTS cdi_resumo_diario (
  cdi_resumo_id INT AUTO_INCREMENT PRIMARY KEY,
  cdi_data_resumo DATE NOT NULL UNIQUE,
  cdi_total_ops INT DEFAULT 0,
  cdi_ops_no_prazo INT DEFAULT 0,
  cdi_ops_atrasadas INT DEFAULT 0,
  cdi_ops_adiantadas INT DEFAULT 0,
  cdi_eficiencia_media DECIMAL(5,2),
  cdi_eficiencia_minima DECIMAL(5,2),
  cdi_eficiencia_maxima DECIMAL(5,2),
  cdi_quantidade_programada_total DECIMAL(15,4),
  cdi_quantidade_realizada_total DECIMAL(15,4),
  cdi_desvio_total_pct DECIMAL(8,2),
  cdi_taxa_conclusao DECIMAL(5,2) COMMENT 'Percentual de OPs concluídas',
  cdi_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cdi_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_cdi_data_resumo (cdi_data_resumo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache: Resumo diário de eficiência para dashboard';

-- ============================================================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ============================================================================

ALTER TABLE cdi_eventos ADD FULLTEXT INDEX ft_cdi_nome_item (cdi_nome_item);
ALTER TABLE cdi_performance ADD FULLTEXT INDEX ft_cdi_nome_recurso (cdi_nome_recurso);

-- ============================================================================
-- TRIGGERS (Opcional mas recomendado)
-- ============================================================================

-- Atualizar resumo diário quando inserir/atualizar eficiência
DELIMITER //

CREATE TRIGGER IF NOT EXISTS tr_cdi_atualizar_resumo_insercao
AFTER INSERT ON cdi_eficiencia_medicao
FOR EACH ROW
BEGIN
  -- Atualizar cache de resumo diário será feito via application logic
  -- Deixado comentado para evitar complexity inicial
  -- INSERT INTO cdi_resumo_diario (...) ON DUPLICATE KEY UPDATE ...
END //

DELIMITER ;

-- ============================================================================
-- Views (Para facilitar queries)
-- ============================================================================

CREATE OR REPLACE VIEW cdi_view_eficiencia_atual AS
SELECT 
  e.cdi_efic_id,
  e.cdi_data_medicao,
  e.cdi_codigo_ordem_producao,
  e.cdi_codigo_item,
  e.cdi_quantidade_programada,
  e.cdi_quantidade_realizada,
  e.cdi_desvio_percentual,
  e.cdi_eficiencia,
  e.cdi_status,
  e.cdi_classificacao,
  DATEDIFF(CURDATE(), e.cdi_data_medicao) as dias_passados
FROM cdi_eficiencia_medicao e
WHERE e.cdi_data_medicao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
ORDER BY e.cdi_data_medicao DESC;

-- ============================================================================
-- DEFINIÇÕES DE DADOS INICIAIS
-- ============================================================================

-- Valores padrão para configuração (será preenchido via aplicação)
-- INSERT INTO cdi_configuracao (cdi_servidor_url, cdi_usuario, cdi_senha, cdi_codename_empresa) 
-- VALUES ('http://192.168.0.1:8080', 'admin', 'encrypted_password', 'matriz');

-- ============================================================================
-- FIM DAS MIGRAÇÕES
-- ============================================================================
