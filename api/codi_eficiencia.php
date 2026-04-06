<?php
/**
 * FASE 5 - API REST: Eficiência
 * 
 * Endpoints para consumir dados de eficiência calculados na FASE 4
 * 
 * Uso:
 * GET /api/codi_eficiencia.php?action=listar&periodo=7
 * GET /api/codi_eficiencia.php?action=detalhe&id=123
 * GET /api/codi_eficiencia.php?action=filtrar&status=critico
 * GET /api/codi_eficiencia.php?action=resumo&data_inicio=2026-04-01&data_fim=2026-04-06
 * GET /api/codi_eficiencia.php?action=por_recurso&recurso_id=1
 * GET /api/codi_eficiencia.php?action=tendencia&dias=30
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once __DIR__ . '/../../bootstrap.php';

use Src\Database\Connection;

class CodiEficienciaAPI
{
    private $db;
    private $action;
    private $response = [];

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->action = $_GET['action'] ?? 'listar';
    }

    public function processar()
    {
        try {
            switch ($this->action) {
                case 'listar':
                    return $this->listar();
                case 'detalhe':
                    return $this->detalhe();
                case 'filtrar':
                    return $this->filtrar();
                case 'resumo':
                    return $this->resumo();
                case 'por_recurso':
                    return $this->porRecurso();
                case 'tendencia':
                    return $this->tendencia();
                case 'criticos':
                    return $this->listarCriticos();
                case 'exportar':
                    return $this->exportar();
                default:
                    return $this->erro('Ação inválida: ' . $this->action);
            }
        } catch (Exception $e) {
            return $this->erro('Erro: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Listar eficiências recentes com paginação
     */
    private function listar()
    {
        $periodo = (int)($_GET['periodo'] ?? 7);  // Últimos N dias
        $pagina = (int)($_GET['pagina'] ?? 1);
        $por_pagina = (int)($_GET['por_pagina'] ?? 50);
        $offset = ($pagina - 1) * $por_pagina;

        try {
            // Total de registros
            $sql_count = "SELECT COUNT(*) as total FROM cdi_eficiencia_medicao 
                          WHERE DATE(data_medicao) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $stmt = $this->db->prepare($sql_count);
            $stmt->execute([$periodo]);
            $total = $stmt->fetch()['total'];

            // Registros paginados
            $sql = "
                SELECT 
                    id,
                    programacao_id,
                    recurso_id,
                    taxa_eficiencia,
                    taxa_performance,
                    taxa_disponibilidade,
                    oee,
                    produtividade,
                    status_geral,
                    desvio_quantidade,
                    desvio_dias,
                    data_medicao
                FROM cdi_eficiencia_medicao
                WHERE DATE(data_medicao) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY data_medicao DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$periodo, $por_pagina, $offset]);
            $registros = $stmt->fetchAll();

            return $this->sucesso([
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $por_pagina,
                'total_paginas' => ceil($total / $por_pagina),
                'registros' => $registros
            ]);

        } catch (Exception $e) {
            return $this->erro('Erro ao listar: ' . $e->getMessage());
        }
    }

    /**
     * Detalhe de uma eficiência específica
     */
    private function detalhe()
    {
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id) {
            return $this->erro('ID é obrigatório');
        }

        try {
            $sql = "
                SELECT 
                    em.*,
                    p.recurso_id as prog_recurso_id,
                    p.sku_id,
                    p.quantidade as prog_quantidade,
                    p.data_prevista_inicio,
                    p.data_prevista_fim
                FROM cdi_eficiencia_medicao em
                LEFT JOIN programacoes p ON em.programacao_id = p.id
                WHERE em.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $eficiencia = $stmt->fetch();

            if (!$eficiencia) {
                return $this->erro('Registro não encontrado');
            }

            return $this->sucesso($eficiencia);

        } catch (Exception $e) {
            return $this->erro('Erro ao buscar detalhe: ' . $e->getMessage());
        }
    }

    /**
     * Filtrar por status, recurso, período
     */
    private function filtrar()
    {
        $status = $_GET['status'] ?? null;
        $recurso_id = (int)($_GET['recurso_id'] ?? 0);
        $data_inicio = $_GET['data_inicio'] ?? null;
        $data_fim = $_GET['data_fim'] ?? null;
        $limite = (int)($_GET['limite'] ?? 100);

        try {
            $sql = "SELECT * FROM cdi_eficiencia_medicao WHERE 1=1";
            $params = [];

            if ($status) {
                $sql .= " AND status_geral = ?";
                $params[] = $status;
            }

            if ($recurso_id > 0) {
                $sql .= " AND recurso_id = ?";
                $params[] = $recurso_id;
            }

            if ($data_inicio) {
                $sql .= " AND DATE(data_medicao) >= ?";
                $params[] = $data_inicio;
            }

            if ($data_fim) {
                $sql .= " AND DATE(data_medicao) <= ?";
                $params[] = $data_fim;
            }

            $sql .= " ORDER BY data_medicao DESC LIMIT ?";
            $params[] = $limite;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $registros = $stmt->fetchAll();

            return $this->sucesso([
                'filtros_aplicados' => [
                    'status' => $status,
                    'recurso_id' => $recurso_id ?: null,
                    'data_inicio' => $data_inicio,
                    'data_fim' => $data_fim
                ],
                'total' => count($registros),
                'registros' => $registros
            ]);

        } catch (Exception $e) {
            return $this->erro('Erro ao filtrar: ' . $e->getMessage());
        }
    }

    /**
     * Resumo agregado de eficiências
     */
    private function resumo()
    {
        $data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $data_fim = $_GET['data_fim'] ?? date('Y-m-d');

        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_registros,
                    AVG(taxa_eficiencia) as eficiencia_media,
                    AVG(taxa_performance) as performance_media,
                    AVG(taxa_disponibilidade) as disponibilidade_media,
                    AVG(oee) as oee_media,
                    AVG(produtividade) as produtividade_media,
                    MIN(taxa_eficiencia) as eficiencia_minima,
                    MAX(taxa_eficiencia) as eficiencia_maxima,
                    SUM(CASE WHEN status_geral = 'critico' THEN 1 ELSE 0 END) as total_criticos,
                    SUM(CASE WHEN status_geral = 'aviso' THEN 1 ELSE 0 END) as total_avisos,
                    SUM(CASE WHEN status_geral = 'ok' THEN 1 ELSE 0 END) as total_ok
                FROM cdi_eficiencia_medicao
                WHERE DATE(data_medicao) >= ? AND DATE(data_medicao) <= ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data_inicio, $data_fim]);
            $resumo = $stmt->fetch();

            // Distribuição por status
            $sql_dist = "
                SELECT status_geral, COUNT(*) as quantidade
                FROM cdi_eficiencia_medicao
                WHERE DATE(data_medicao) >= ? AND DATE(data_medicao) <= ?
                GROUP BY status_geral
            ";
            $stmt = $this->db->prepare($sql_dist);
            $stmt->execute([$data_inicio, $data_fim]);
            $distribuicao = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Distribuição por recurso
            $sql_rec = "
                SELECT recurso_id, COUNT(*) as quantidade, AVG(oee) as oee_medio
                FROM cdi_eficiencia_medicao
                WHERE DATE(data_medicao) >= ? AND DATE(data_medicao) <= ?
                GROUP BY recurso_id
                ORDER BY oee_medio DESC
            ";
            $stmt = $this->db->prepare($sql_rec);
            $stmt->execute([$data_inicio, $data_fim]);
            $por_recurso = $stmt->fetchAll();

            return $this->sucesso([
                'periodo' => [
                    'data_inicio' => $data_inicio,
                    'data_fim' => $data_fim
                ],
                'resumo' => $this->formatar_numeros($resumo),
                'distribuicao_por_status' => $distribuicao,
                'top_recursos' => $por_recurso
            ]);

        } catch (Exception $e) {
            return $this->erro('Erro ao gerar resumo: ' . $e->getMessage());
        }
    }

    /**
     * Eficiências por recurso específico
     */
    private function porRecurso()
    {
        $recurso_id = (int)($_GET['recurso_id'] ?? 0);
        $dias = (int)($_GET['dias'] ?? 30);

        if (!$recurso_id) {
            return $this->erro('recurso_id é obrigatório');
        }

        try {
            $sql = "
                SELECT 
                    DATA FORMAT(data_medicao, '%Y-%m-%d') as data,
                    recurso_id,
                    COUNT(*) as total_mediciones,
                    AVG(taxa_eficiencia) as eficiencia_media,
                    AVG(oee) as oee_medio,
                    SUM(CASE WHEN status_geral = 'critico' THEN 1 ELSE 0 END) as criticos
                FROM cdi_eficiencia_medicao
                WHERE recurso_id = ? AND DATE(data_medicao) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(data_medicao), recurso_id
                ORDER BY data_medicao DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$recurso_id, $dias]);
            $resultados = $stmt->fetchAll();

            return $this->sucesso([
                'recurso_id' => $recurso_id,
                'periodo_dias' => $dias,
                'total_registros' => count($resultados),
                'dados' => $resultados
            ]);

        } catch (Exception $e) {
            return $this->erro('Erro ao buscar por recurso: ' . $e->getMessage());
        }
    }

    /**
     * Tendência de eficiência ao longo do tempo
     */
    private function tendencia()
    {
        $dias = (int)($_GET['dias'] ?? 30);
        $recurso_id = (int)($_GET['recurso_id'] ?? 0);

        try {
            $sql = "
                SELECT 
                    DATE(data_medicao) as data,
                    AVG(taxa_eficiencia) as eficiencia_media,
                    AVG(oee) as oee_medio,
                    AVG(taxa_performance) as performance_media,
                    AVG(taxa_disponibilidade) as disponibilidade_media,
                    COUNT(*) as amostras
                FROM cdi_eficiencia_medicao
                WHERE DATE(data_medicao) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ";

            $params = [$dias];

            if ($recurso_id > 0) {
                $sql .= " AND recurso_id = ?";
                $params[] = $recurso_id;
            }

            $sql .= " GROUP BY DATE(data_medicao) ORDER BY data_medicao ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $tendencia = $stmt->fetchAll();

            // Calcular tendência (linha de regressão simplificada)
            $pontos = count($tendencia);
            if ($pontos > 1) {
                $primeiro = $tendencia[0]['oee_medio'];
                $ultimo = $tendencia[$pontos - 1]['oee_medio'];
                $variacao = $ultimo - $primeiro;
                $percentual_variacao = ($primeiro != 0) ? ($variacao / $primeiro) * 100 : 0;
            } else {
                $variacao = 0;
                $percentual_variacao = 0;
            }

            return $this->sucesso([
                'periodo_dias' => $dias,
                'recurso_id' => $recurso_id ?: 'todos',
                'total_pontos' => $pontos,
                'tendencia' => [
                    'variacao_absoluta' => round($variacao, 2),
                    'variacao_percentual' => round($percentual_variacao, 2),
                    'direcao' => $variacao > 0 ? 'positiva' : ($variacao < 0 ? 'negativa' : 'estável')
                ],
                'dados' => $tendencia
            ]);

        } catch (Exception $e) {
            return $this->erro('Erro ao gerar tendência: ' . $e->getMessage());
        }
    }

    /**
     * Listar apenas registros críticos
     */
    private function listarCriticos()
    {
        $dias = (int)($_GET['dias'] ?? 7);
        $limite = (int)($_GET['limite'] ?? 50);

        try {
            $sql = "
                SELECT 
                    *,
                    DATEDIFF(NOW(), data_medicao) as dias_desde_critico
                FROM cdi_eficiencia_medicao
                WHERE status_geral = 'critico'
                AND DATE(data_medicao) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY data_medicao DESC
                LIMIT ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$dias, $limite]);
            $criticos = $stmt->fetchAll();

            return $this->sucesso([
                'periodo_dias' => $dias,
                'total_criticos' => count($criticos),
                'registros' => $criticos
            ]);

        } catch (Exception $e) {
            return $this->erro('Erro ao listar críticos: ' . $e->getMessage());
        }
    }

    /**
     * Exportar dados (CSV ou JSON)
     */
    private function exportar()
    {
        $formato = $_GET['formato'] ?? 'json';  // json ou csv
        $data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
        $status = $_GET['status'] ?? null;

        try {
            $sql = "
                SELECT * FROM cdi_eficiencia_medicao
                WHERE DATE(data_medicao) >= ? AND DATE(data_medicao) <= ?
            ";
            $params = [$data_inicio, $data_fim];

            if ($status) {
                $sql .= " AND status_geral = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY data_medicao DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll();

            if ($formato === 'csv') {
                return $this->gerarCSV($dados);
            } else {
                return $this->sucesso([
                    'total_registros' => count($dados),
                    'periodo' => "$data_inicio até $data_fim",
                    'dados' => $dados
                ]);
            }

        } catch (Exception $e) {
            return $this->erro('Erro ao exportar: ' . $e->getMessage());
        }
    }

    /**
     * Gerar CSV
     */
    private function gerarCSV($dados)
    {
        if (empty($dados)) {
            return $this->erro('Nenhum dado para exportar');
        }

        $csv = "ID,Programacao,Recurso,Eficiencia,Performance,Disponibilidade,OEE,Produtividade,Status,Desvio Qtd,Desvio Dias,Data\n";

        foreach ($dados as $row) {
            $csv .= implode(',', [
                $row['id'],
                $row['programacao_id'],
                $row['recurso_id'],
                $row['taxa_eficiencia'],
                $row['taxa_performance'],
                $row['taxa_disponibilidade'],
                $row['oee'],
                $row['produtividade'],
                $row['status_geral'],
                $row['desvio_quantidade'],
                $row['desvio_dias'],
                $row['data_medicao']
            ]) . "\n";
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=eficiencia_' . date('Y-m-d_His') . '.csv');
        echo $csv;
        exit;
    }

    /**
     * Resposta de sucesso
     */
    private function sucesso($dados)
    {
        http_response_code(200);
        echo json_encode([
            'sucesso' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'dados' => $dados
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Resposta de erro
     */
    private function erro($mensagem, $codigo = 400)
    {
        http_response_code($codigo);
        echo json_encode([
            'sucesso' => false,
            'erro' => $mensagem,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Formatar números com 2 casas decimais
     */
    private function formatar_numeros($array)
    {
        foreach ($array as $key => $value) {
            if (is_numeric($value) && strpos($key, 'media') !== false || strpos($key, 'minima') !== false || strpos($key, 'maxima') !== false) {
                $array[$key] = round($value, 2);
            }
        }
        return $array;
    }
}

// Executar API
$api = new CodiEficienciaAPI();
$api->processar();
