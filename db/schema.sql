-- Esquema de banco de dados para o projeto Controle PCP
-- Nomes de tabelas e colunas em português, com prefixo de 3 letras.

-- 1) Linhas de produção
CREATE TABLE IF NOT EXISTS lin_linhas (
  lin_id INT AUTO_INCREMENT PRIMARY KEY,
  lin_codigo VARCHAR(50) NOT NULL UNIQUE,
  lin_nome VARCHAR(150) NOT NULL,
  lin_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lin_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Armazena as linhas de produção disponíveis.';

-- 2) Calendários
CREATE TABLE IF NOT EXISTS cal_calendarios (
  cal_id INT AUTO_INCREMENT PRIMARY KEY,
  cal_linha_id INT NOT NULL,
  cal_nome VARCHAR(120) NOT NULL DEFAULT 'Calendário padrão',
  cal_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cal_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cal_calendarios_linha FOREIGN KEY (cal_linha_id) REFERENCES lin_linhas(lin_id) ON DELETE CASCADE,
  UNIQUE KEY ux_cal_calendarios_linha (cal_linha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Calendários de produção por linha, com horários de atendimento e feriados.';

-- 2.1) Intervalos de trabalho por calendário
CREATE TABLE IF NOT EXISTS cal_intervalos (
  cal_id INT AUTO_INCREMENT PRIMARY KEY,
  cal_calendario_id INT NOT NULL,
  cal_inicio TIME NOT NULL,
  cal_fim TIME NOT NULL,
  cal_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cal_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cal_intervalos_calendario FOREIGN KEY (cal_calendario_id) REFERENCES cal_calendarios(cal_id) ON DELETE CASCADE,
  UNIQUE KEY ux_cal_intervalos_calendario_inicio_fim (cal_calendario_id, cal_inicio, cal_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Intervalos de trabalho válidos em um calendário, como turnos ou janelas de produção.';

-- 2.2) Dias úteis por intervalo (1=Seg ... 7=Dom)
CREATE TABLE IF NOT EXISTS cal_dias_uteis (
  diu_intervalo_id INT NOT NULL,
  diu_dia_peq TINYINT NOT NULL,
  PRIMARY KEY (diu_intervalo_id, diu_dia_peq),
  CONSTRAINT fk_cal_dias_uteis_intervalo FOREIGN KEY (diu_intervalo_id) REFERENCES cal_intervalos(cal_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dias da semana em que cada intervalo de trabalho é válido.';

-- 2.3) Feriados / pausas
CREATE TABLE IF NOT EXISTS cal_feriados (
  cal_id INT AUTO_INCREMENT PRIMARY KEY,
  cal_calendario_id INT NOT NULL,
  cal_data DATE NOT NULL,
  cal_nome VARCHAR(150) NOT NULL DEFAULT '',
  cal_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cal_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_cal_feriados_calendario_data (cal_calendario_id, cal_data),
  CONSTRAINT fk_cal_feriados_calendario FOREIGN KEY (cal_calendario_id) REFERENCES cal_calendarios(cal_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datas de feriado ou pausas dentro de um calendário de produção.';

-- 3) Produtos / SKUs
CREATE TABLE IF NOT EXISTS prd_produtos (
  prd_sku VARCHAR(120) PRIMARY KEY,
  prd_descricao VARCHAR(255) NOT NULL,
  prd_referencia_setup VARCHAR(120) NOT NULL,
  prd_linha_id INT NOT NULL,
  prd_taxa_por_hora DECIMAL(12,4) NOT NULL DEFAULT 0,
  prd_unidade VARCHAR(20) NOT NULL DEFAULT 'cx',
  prd_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  prd_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_prd_produtos_linha FOREIGN KEY (prd_linha_id) REFERENCES lin_linhas(lin_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Produtos (SKUs) fabricados, com referência de setup e linha associada.';

-- 4) Matriz de setup (tempo entre um produto e outro)
CREATE TABLE IF NOT EXISTS mat_matriz_setup (
  mat_id INT AUTO_INCREMENT PRIMARY KEY,
  mat_linha_id INT NOT NULL,
  mat_sku_origem VARCHAR(120) NOT NULL,
  mat_sku_destino VARCHAR(120) NOT NULL,
  mat_duracao_minutos INT NOT NULL,
  mat_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  mat_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_mat_matriz_setup_linha_origem_destino (mat_linha_id, mat_sku_origem, mat_sku_destino),
  CONSTRAINT fk_mat_matriz_setup_linha FOREIGN KEY (mat_linha_id) REFERENCES lin_linhas(lin_id) ON DELETE CASCADE,
  CONSTRAINT fk_mat_matriz_setup_origem FOREIGN KEY (mat_sku_origem) REFERENCES prd_produtos(prd_sku) ON DELETE RESTRICT,
  CONSTRAINT fk_mat_matriz_setup_destino FOREIGN KEY (mat_sku_destino) REFERENCES prd_produtos(prd_sku) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Matriz de tempos de setup entre produtos, por linha de produção.';

-- 5) Programações / execuções de cálculo
CREATE TABLE IF NOT EXISTS prg_programas (
  prg_id INT AUTO_INCREMENT PRIMARY KEY,
  prg_numero_op VARCHAR(120) NULL UNIQUE,
  prg_linha_id INT NOT NULL,
  prg_base_inicio DATETIME NOT NULL,
  prg_data_consulta DATETIME NULL,
  prg_eficiencia DECIMAL(5,2) NOT NULL DEFAULT 100,
  prg_status VARCHAR(50) NOT NULL DEFAULT 'rascunho',
  prg_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  prg_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_prg_programas_linha FOREIGN KEY (prg_linha_id) REFERENCES lin_linhas(lin_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Programações de produção (execuções de cálculo) por linha e referência de data.';

CREATE TABLE IF NOT EXISTS prg_itens (
  prg_id_item INT AUTO_INCREMENT PRIMARY KEY,
  prg_programa_id INT NOT NULL,
  prg_sequencia INT NOT NULL,
  prg_sku VARCHAR(120) NOT NULL,
  prg_quantidade DECIMAL(14,4) NOT NULL DEFAULT 0,
  prg_inicio_planejado DATETIME NULL,
  prg_itens_op VARCHAR(120) NULL,
  prg_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  prg_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_prg_itens_programa FOREIGN KEY (prg_programa_id) REFERENCES prg_programas(prg_id) ON DELETE CASCADE,
  CONSTRAINT fk_prg_itens_produto FOREIGN KEY (prg_sku) REFERENCES prd_produtos(prd_sku) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens incluídos na programação de produção, com quantidades e sequência.';

-- 6) Resultado do cálculo (histórico opcional)
CREATE TABLE IF NOT EXISTS sch_linhas (
  sch_id INT AUTO_INCREMENT PRIMARY KEY,
  sch_programa_id INT NOT NULL,
  sch_tipo ENUM('setup','producao') NOT NULL,
  sch_sequencia INT NOT NULL,
  sch_sku VARCHAR(120) NULL,
  sch_descricao VARCHAR(255) NULL,
  sch_quantidade DECIMAL(14,4) NULL,
  sch_taxa_por_hora DECIMAL(12,4) NULL,
  sch_duracao_minutos INT NULL,
  sch_sku_anterior VARCHAR(120) NULL,
  sch_inicio_planejado DATETIME NULL,
  sch_data_inicio DATE NULL,
  sch_hora_inicio TIME NULL,
  sch_hora_fim TIME NULL,
  sch_inicio_producao DATETIME NULL,
  sch_fim_producao DATETIME NULL,
  sch_produzido_estimado DECIMAL(14,4) NULL,
  sch_status VARCHAR(100) NULL,
  sch_memoria_calculo TEXT NULL,
  sch_criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sch_atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sch_linhas_programa FOREIGN KEY (sch_programa_id) REFERENCES prg_programas(prg_id) ON DELETE CASCADE,
  CONSTRAINT fk_sch_linhas_sku FOREIGN KEY (sch_sku) REFERENCES prd_produtos(prd_sku) ON DELETE SET NULL,
  CONSTRAINT fk_sch_linhas_sku_anterior FOREIGN KEY (sch_sku_anterior) REFERENCES prd_produtos(prd_sku) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de linhas de cálculo de produção (setup e produção) gerados pelos cálculos.';

-- 99) Dados iniciais (seed)
-- Valores iniciais para permitir que o sistema seja usado imediatamente após criar o schema.

INSERT INTO lin_linhas (lin_codigo, lin_nome) VALUES ('L2', 'Linha L2')
  ON DUPLICATE KEY UPDATE lin_nome = VALUES(lin_nome);

INSERT INTO cal_calendarios (cal_linha_id, cal_nome)
SELECT lin_id, 'Calendário padrão'
FROM lin_linhas
WHERE lin_codigo = 'L2'
ON DUPLICATE KEY UPDATE cal_nome = VALUES(cal_nome);

INSERT IGNORE INTO cal_intervalos (cal_calendario_id, cal_inicio, cal_fim)
SELECT cal_id, '07:10', '11:28' FROM cal_calendarios WHERE cal_linha_id = (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2');
INSERT IGNORE INTO cal_intervalos (cal_calendario_id, cal_inicio, cal_fim)
SELECT cal_id, '13:35', '17:40' FROM cal_calendarios WHERE cal_linha_id = (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2');
INSERT IGNORE INTO cal_intervalos (cal_calendario_id, cal_inicio, cal_fim)
SELECT cal_id, '17:40', '22:00' FROM cal_calendarios WHERE cal_linha_id = (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2');
INSERT IGNORE INTO cal_intervalos (cal_calendario_id, cal_inicio, cal_fim)
SELECT cal_id, '23:00', '03:00' FROM cal_calendarios WHERE cal_linha_id = (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2');

INSERT IGNORE INTO prd_produtos (prd_sku, prd_descricao, prd_referencia_setup, prd_linha_id, prd_taxa_por_hora, prd_unidade)
VALUES
  ('AGUA SANITARIA 5L', 'Agua Sanitaria 5L', 'AGUA SANITARIA 5L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 200.0, 'cx'),
  ('ALVEJANTE S/ CLORO 3L', 'Alvejante S/ Cloro 3L', 'ALVEJANTE S/ CLORO 3L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 180.0, 'cx'),
  ('DESINFETANTE CAMPOS LAVANDA 5L', 'Desinfetante Campos Lavanda 5L', 'DESINFETANTE CAMPOS LAVANDA 5L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 200.0, 'cx'),
  ('DESINFETANTE ENERGIA 5L', 'Desinfetante Energia 5L', 'DESINFETANTE ENERGIA 5L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 200.0, 'cx'),
  ('DESINFETANTE FL. DE EUCALIPTO 5L', 'Desinfetante Fl. de Eucalipto 5L', 'DESINFETANTE FL. DE EUCALIPTO 5L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 200.0, 'cx'),
  ('DESINFETANTE HARMONIA NATURAL 5L', 'Desinfetante Harmonia Natural 5L', 'DESINFETANTE HARMONIA NATURAL 5L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 200.0, 'cx'),
  ('DESINFETANTE JARDIM FLORIDO 5L', 'Desinfetante Jardim Florido 5L', 'DESINFETANTE JARDIM FLORIDO 5L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 200.0, 'cx'),
  ('DESINFETANTE MARINE 5L', 'Desinfetante Marine 5L', 'DESINFETANTE MARINE 5L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 200.0, 'cx'),
  ('DESINFETANTE PAIXAO 5L', 'Desinfetante Paixao 5L', 'DESINFETANTE PAIXAO 5L', (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), 200.0, 'cx')
ON DUPLICATE KEY UPDATE prd_descricao = VALUES(prd_descricao), prd_referencia_setup = VALUES(prd_referencia_setup), prd_taxa_por_hora = VALUES(prd_taxa_por_hora), prd_unidade = VALUES(prd_unidade);

-- Matriz de setup exemplo (deixa em 20 min entre produtos e 30 min quando troca para/da lista especial)
INSERT IGNORE INTO mat_matriz_setup (mat_linha_id, mat_sku_origem, mat_sku_destino, mat_duracao_minutos)
SELECT (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2'), p1.prd_sku, p2.prd_sku,
  CASE
    WHEN p1.prd_sku <> p2.prd_sku AND (p1.prd_sku IN ('AGUA SANITARIA 5L','ALVEJANTE S/ CLORO 3L') OR p2.prd_sku IN ('AGUA SANITARIA 5L','ALVEJANTE S/ CLORO 3L')) THEN 30
    ELSE 20
  END
FROM prd_produtos p1
CROSS JOIN prd_produtos p2
WHERE p1.prd_linha_id = (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2')
  AND p2.prd_linha_id = (SELECT lin_id FROM lin_linhas WHERE lin_codigo = 'L2');
