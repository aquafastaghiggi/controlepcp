-- Migration: Adicionar tabelas para sincronização CODI
-- FASE 6: Integração CODI
-- Criado: 2026-04-07

-- 1) Tabela de recursos CODI (máquinas/linhas)
CREATE TABLE IF NOT EXISTS codi_recursos (
  cod_id INT AUTO_INCREMENT PRIMARY KEY,
  cod_codigo_codi INT NOT NULL UNIQUE,
  cod_nome_recurso VARCHAR(200) NOT NULL,
  cod_descricao TEXT,
  cod_ativo BOOLEAN DEFAULT TRUE,
  cod_estabelecimento_codi INT,
  cod_coletor_codi INT,
  cod_dados_json LONGTEXT COMMENT 'Dados completos do recurso em JSON',
  cod_sincronizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  cod_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cod_linha FOREIGN KEY (cod_id) REFERENCES lin_linhas(lin_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recursos CODI sincronizados do servidor externo.';

-- 2) Tabela de calendário fabril CODI
CREATE TABLE IF NOT EXISTS codi_calendario (
  cal_id INT AUTO_INCREMENT PRIMARY KEY,
  cal_codigo_codi INT NOT NULL,
  cal_recurso_codi_id INT NOT NULL,
  cal_grandeza_codi INT,
  cal_turno_codi INT,
  cal_data DATE NOT NULL,
  cal_hora_inicio TIME NOT NULL,
  cal_hora_fim TIME NOT NULL,
  cal_dados_json LONGTEXT COMMENT 'Dados completos em JSON',
  cal_sincronizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  cal_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_cal_codi_unique (cal_codigo_codi),
  INDEX idx_cal_data (cal_data),
  INDEX idx_cal_recurso (cal_recurso_codi_id),
  CONSTRAINT fk_cal_recurso FOREIGN KEY (cal_recurso_codi_id) REFERENCES codi_recursos(cod_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Calendário fabril CODI: turnos e períodos de produção.';

-- 3) Tabela de performance CODI
CREATE TABLE IF NOT EXISTS codi_performance (
  perf_id INT AUTO_INCREMENT PRIMARY KEY,
  perf_codigo_codi INT NOT NULL UNIQUE,
  perf_recurso_codi_id INT,
  perf_item_codi INT,
  perf_ordem_producao VARCHAR(120),
  perf_dados_json LONGTEXT COMMENT 'Dados completos em JSON',
  perf_sincronizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  perf_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_perf_recurso (perf_recurso_codi_id),
  INDEX idx_perf_ordem (perf_ordem_producao),
  CONSTRAINT fk_perf_recurso FOREIGN KEY (perf_recurso_codi_id) REFERENCES codi_recursos(cod_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Performance data do CODI: métricas de execução.';

-- 4) Tabela para rastreamento de sincronizações
CREATE TABLE IF NOT EXISTS codi_sincronizacao (
  sinc_id INT AUTO_INCREMENT PRIMARY KEY,
  sinc_tipo VARCHAR(50) NOT NULL COMMENT 'recursos|calendario|performance',
  sinc_status VARCHAR(50) NOT NULL DEFAULT 'pendente' COMMENT 'pendente|processando|sucesso|erro',
  sinc_registros_processados INT DEFAULT 0,
  sinc_registros_falhados INT DEFAULT 0,
  sinc_mensagem_erro TEXT,
  sinc_iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sinc_finalizado_em DATETIME,
  CONSTRAINT chk_sinc_tipo CHECK (sinc_tipo IN ('recursos', 'calendario', 'performance')),
  CONSTRAINT chk_sinc_status CHECK (sinc_status IN ('pendente', 'processando', 'sucesso', 'erro')),
  INDEX idx_sinc_status (sinc_status),
  INDEX idx_sinc_tipo (sinc_tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de sincronizações com CODI API.';

-- 5) Tabela de mapeamento entre CODI e sistema local
CREATE TABLE IF NOT EXISTS codi_mapeamento (
  map_id INT AUTO_INCREMENT PRIMARY KEY,
  map_entidade VARCHAR(50) NOT NULL COMMENT 'recursos|calendario|performance',
  map_codi_id INT NOT NULL,
  map_local_id INT NOT NULL,
  map_tipo_local VARCHAR(50) COMMENT 'lin_linhas|prd_produtos|prg_programas',
  map_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_map_codi_local (map_entidade, map_codi_id),
  INDEX idx_map_local (map_tipo_local, map_local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mapeamento entre IDs do CODI e do sistema local.';
