<?php

namespace Codi;

use PDO;
use Exception;

/**
 * FASE 4 - EficienciaCalculator
 * 
 * Responsável por cruzar dados programados (ControlePCP) com dados reais (CODI)
 * e calcular indicadores de eficiência e desvios.
 * 
 * Fluxo:
 * 1. Programações (previsto) vem do ControlePCP
 * 2. Performance (realizado) vem do CODI via sync
 * 3. Cruzar dados por: recurso, período, SKU
 * 4. Calcular: desvios, OEE, produtividade, etc
 * 5. Armazenar em cdi_eficiencia_medicao
 * 6. Gerar logs em cdi_eficiencia_historico
 */
class EficienciaCalculator
{
    private $db;
    private $logging = true;
    private $logs = [];

    /**
     * Construtor
     * 
     * @param PDO $connection Conexão com banco de dados
     */
    public function __construct(PDO $connection)
    {
        $this->db = $connection;
    }

    /**
     * Calcula eficiência para um período completo
     * Sincroniza dados de produção com KPIs do CODI
     * 
     * @param string $dataInicio YYYY-MM-DD
     * @param string $dataFim YYYY-MM-DD
     * @param array $opcoes Filtros e configurações
     * @return array Resultado com estatísticas
     */
    public function calcularEficienciaCompleta($dataInicio, $dataFim, $opcoes = [])
    {
        try {
            $this->log("Iniciando cálculo completo de eficiência: $dataInicio até $dataFim");

            $resultado = [
                'sucesso' => false,
                'periodosProcessados' => 0,
                'desviosCalculados' => 0,
                'erros' => [],
                'detalhes' => []
            ];

            // 1. Obter programações do período
            $programacoes = $this->obterProgramacoes($dataInicio, $dataFim, $opcoes);
            $this->log("Encontrados " . count($programacoes) . " programações no período");

            // 2. Para cada programação, buscar performance real
            foreach ($programacoes as $prog) {
                try {
                    $eficiencia = $this->calcularPorProgramacao($prog, $opcoes);
                    $resultado['detalhes'][] = $eficiencia;
                    $resultado['periodosProcessados']++;
                    $resultado['desviosCalculados'] += count($eficiencia['desvios'] ?? []);
                } catch (Exception $e) {
                    $resultado['erros'][] = [
                        'programacaoId' => $prog['id'] ?? 'desconhecido',
                        'erro' => $e->getMessage()
                    ];
                    $this->log("Erro ao calcular programação: " . $e->getMessage(), 'error');
                }
            }

            $resultado['sucesso'] = true;
            $this->log("Cálculo concluído: {$resultado['periodosProcessados']} períodos, {$resultado['desviosCalculados']} desvios");

            return $resultado;

        } catch (Exception $e) {
            $this->log("Erro crítico em calcularEficienciaCompleta: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Calcula eficiência para uma programação específica
     * 
     * @param array $programacao Dados da programação
     * @param array $opcoes Filtros e configurações
     * @return array Dados de eficiência calculados
     */
    private function calcularPorProgramacao($programacao, $opcoes = [])
    {
        $progId = $programacao['id'] ?? null;
        $dataInicio = $programacao['data_prevista_inicio'] ?? null;
        $dataFim = $programacao['data_prevista_fim'] ?? null;
        $recurso = $programacao['recurso_id'] ?? null;

        // Buscar dados reais do CODI para o período
        $performance = $this->obterPerformanceReal($recurso, $dataInicio, $dataFim);

        // Calcular indicadores
        $previsto = [
            'quantidade' => $programacao['quantidade_programada'] ?? 0,
            'tempo_padrao_horas' => $programacao['tempo_padrao_min'] / 60 ?? 0,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ];

        $realizado = [
            'quantidade' => $performance['quantidade_realizada'] ?? 0,
            'tempo_real_horas' => $performance['tempo_real_min'] / 60 ?? 0,
            'tempo_parado_min' => $performance['tempo_parado_min'] ?? 0,
            'data_inicio' => $performance['primeira_producao'] ?? $dataInicio,
            'data_fim' => $performance['ultima_producao'] ?? $dataFim
        ];

        // Calcular desvios
        $desvios = $this->calcularDesvios($previsto, $realizado);

        // Calcular KPIs
        $kpis = $this->calcularKPIs($previsto, $realizado);

        // Determinar status
        $status = $this->determinarStatus($desvios, $kpis, $opcoes);

        // Preparar resultado
        $resultado = [
            'programacao_id' => $progId,
            'recurso_id' => $recurso,
            'previsto' => $previsto,
            'realizado' => $realizado,
            'desvios' => $desvios,
            'kpis' => $kpis,
            'status' => $status,
            'data_calculo' => date('Y-m-d H:i:s')
        ];

        // Persistir resultado
        $this->persistirEficiencia($resultado);

        return $resultado;
    }

    /**
     * Calcula desvios entre previsto e realizado
     * 
     * @param array $previsto Dados programados
     * @param array $realizado Dados reais
     * @return array Desvios calculados
     */
    private function calcularDesvios($previsto, $realizado)
    {
        $desvios = [];

        // Desvio de quantidade
        if ($previsto['quantidade'] > 0) {
            $desvios['quantidade'] = [
                'previsto' => $previsto['quantidade'],
                'realizado' => $realizado['quantidade'],
                'desvio_unidades' => $realizado['quantidade'] - $previsto['quantidade'],
                'desvio_percentual' => (($realizado['quantidade'] - $previsto['quantidade']) / $previsto['quantidade']) * 100
            ];
        }

        // Desvio de tempo
        if ($previsto['tempo_padrao_horas'] > 0) {
            $desvios['tempo'] = [
                'tempo_padrao_horas' => $previsto['tempo_padrao_horas'],
                'tempo_real_horas' => $realizado['tempo_real_horas'],
                'desvio_horas' => $realizado['tempo_real_horas'] - $previsto['tempo_padrao_horas'],
                'desvio_percentual' => (($realizado['tempo_real_horas'] - $previsto['tempo_padrao_horas']) / $previsto['tempo_padrao_horas']) * 100
            ];
        }

        // Desvio de data (atraso/adiantamento)
        if ($previsto['data_fim'] && $realizado['data_fim']) {
            $dtPrevista = new \DateTime($previsto['data_fim']);
            $dtRealizada = new \DateTime($realizado['data_fim']);
            $interval = $dtRealizada->diff($dtPrevista);
            
            $desvios['data'] = [
                'data_prevista' => $previsto['data_fim'],
                'data_realizada' => $realizado['data_fim'],
                'dias_atraso' => ($dtRealizada > $dtPrevista) ? $interval->days : -$interval->days,
                'status_prazo' => ($dtRealizada <= $dtPrevista) ? 'no_prazo' : 'atrasado'
            ];
        }

        return $desvios;
    }

    /**
     * Calcula KPIs (OEE, Produtividade, etc)
     * 
     * @param array $previsto Dados programados
     * @param array $realizado Dados reais
     * @return array KPIs calculados
     */
    private function calcularKPIs($previsto, $realizado)
    {
        $kpis = [];

        // 1. Taxa de Eficiência (Realizado / Previsto)
        if ($previsto['quantidade'] > 0) {
            $kpis['eficiencia_quantidade'] = ($realizado['quantidade'] / $previsto['quantidade']) * 100;
        } else {
            $kpis['eficiencia_quantidade'] = 0;
        }

        // 2. Taxa de Performance (Tempo Padrão / Tempo Real)
        if ($realizado['tempo_real_horas'] > 0) {
            $kpis['performance_tempo'] = ($previsto['tempo_padrao_horas'] / $realizado['tempo_real_horas']) * 100;
        } else {
            $kpis['performance_tempo'] = 0;
        }

        // 3. Taxa de Disponibilidade (Tempo Disponível - Tempo Parado / Tempo Disponível)
        $tempo_total_min = $realizado['tempo_real_horas'] * 60 + ($realizado['tempo_parado_min'] ?? 0);
        if ($tempo_total_min > 0) {
            $kpis['disponibilidade'] = (($tempo_total_min - ($realizado['tempo_parado_min'] ?? 0)) / $tempo_total_min) * 100;
        } else {
            $kpis['disponibilidade'] = 100;
        }

        // 4. OEE (Overall Equipment Effectiveness) = Disponibilidade × Performance × Qualidade
        // Usando simplificado: (Eficiência × Performance × Disponibilidade) / 10000
        $kpis['oee'] = ($kpis['eficiencia_quantidade'] * $kpis['performance_tempo'] * $kpis['disponibilidade']) / 10000;

        // 5. Produtividade (Quantidade / Horas)
        if ($realizado['tempo_real_horas'] > 0) {
            $kpis['produtividade_por_hora'] = $realizado['quantidade'] / $realizado['tempo_real_horas'];
        } else {
            $kpis['produtividade_por_hora'] = 0;
        }

        return $kpis;
    }

    /**
     * Determina status baseado em desvios e KPIs
     * 
     * @param array $desvios Desvios calculados
     * @param array $kpis KPIs calculados
     * @param array $opcoes Configurações de limites
     * @return array Status detalhado
     */
    private function determinarStatus($desvios, $kpis, $opcoes = [])
    {
        // Limites padrão
        $limites = [
            'eficiencia_critica' => $opcoes['eficiencia_critica'] ?? 70,  // Abaixo = Crítico
            'eficiencia_aviso' => $opcoes['eficiencia_aviso'] ?? 85,      // Abaixo = Aviso
            'oee_critica' => $opcoes['oee_critica'] ?? 50,
            'oee_aviso' => $opcoes['oee_aviso'] ?? 75,
            'atraso_dias_critico' => $opcoes['atraso_dias_critico'] ?? 5,
            'atraso_dias_aviso' => $opcoes['atraso_dias_aviso'] ?? 2
        ];

        $status = [
            'geral' => 'ok',
            'niveis' => [
                'eficiencia' => 'ok',
                'oee' => 'ok',
                'prazo' => 'ok'
            ],
            'detalhes' => []
        ];

        // Verificar eficiência
        $eficiencia = $desvios['quantidade']['desvio_percentual'] ?? 0;
        if ($eficiencia < $limites['eficiencia_critica']) {
            $status['niveis']['eficiencia'] = 'critico';
            $status['detalhes'][] = "Eficiência crítica: {$eficiencia}%";
        } elseif ($eficiencia < $limites['eficiencia_aviso']) {
            $status['niveis']['eficiencia'] = 'aviso';
            $status['detalhes'][] = "Eficiência em aviso: {$eficiencia}%";
        }

        // Verificar OEE
        if ($kpis['oee'] < $limites['oee_critica']) {
            $status['niveis']['oee'] = 'critico';
            $status['detalhes'][] = "OEE crítico: {$kpis['oee']}%";
        } elseif ($kpis['oee'] < $limites['oee_aviso']) {
            $status['niveis']['oee'] = 'aviso';
            $status['detalhes'][] = "OEE em aviso: {$kpis['oee']}%";
        }

        // Verificar prazo
        $atraso = $desvios['data']['dias_atraso'] ?? 0;
        if ($atraso > $limites['atraso_dias_critico']) {
            $status['niveis']['prazo'] = 'critico';
            $status['detalhes'][] = "Atraso crítico: {$atraso} dias";
        } elseif ($atraso > $limites['atraso_dias_aviso']) {
            $status['niveis']['prazo'] = 'aviso';
            $status['detalhes'][] = "Atraso em aviso: {$atraso} dias";
        }

        // Status geral = pior nível
        $niveis_valor = ['ok' => 0, 'aviso' => 1, 'critico' => 2];
        $pior = max(array_map(fn($n) => $niveis_valor[$n], $status['niveis']));
        $status['geral'] = array_search($pior, $niveis_valor);

        return $status;
    }

    /**
     * Obtém programações do período
     * 
     * @param string $dataInicio YYYY-MM-DD
     * @param string $dataFim YYYY-MM-DD
     * @param array $opcoes Filtros
     * @return array Programações
     */
    private function obterProgramacoes($dataInicio, $dataFim, $opcoes = [])
    {
        try {
            $sql = "  
                SELECT 
                    p.id,
                    p.recurso_id,
                    p.sku_id,
                    p.quantidade,
                    p.data_prevista_inicio,
                    p.data_prevista_fim,
                    p.tempo_padrao_min,
                    r.nome as recurso_nome
                FROM programacoes p
                INNER JOIN recursos r ON p.recurso_id = r.id
                WHERE p.data_prevista_inicio >= ?
                  AND p.data_prevista_fim <= ?
                  AND p.status = 'finalizada'
            ";

            $params = [$dataInicio, $dataFim];

            // Filtrar por recurso se especificado
            if (!empty($opcoes['recurso_id'])) {
                $sql .= " AND p.recurso_id = ?";
                $params[] = $opcoes['recurso_id'];
            }

            $sql .= " ORDER BY p.data_prevista_inicio ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

        } catch (Exception $e) {
            $this->log("Erro ao obter programações: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Obtém performance real do CODI para um período
     * 
     * @param int $recursoId ID do recurso
     * @param string $dataInicio YYYY-MM-DD
     * @param string $dataFim YYYY-MM-DD
     * @return array Performance real
     */
    private function obterPerformanceReal($recursoId, $dataInicio, $dataFim)
    {
        try {
            $sql = "
                SELECT 
                    recurso_id,
                    SUM(quantidade_produzida) as quantidade_realizada,
                    SUM(tempo_producao_min) as tempo_real_min,
                    SUM(tempo_parado_min) as tempo_parado_min,
                    MIN(data_hora) as primeira_producao,
                    MAX(data_hora) as ultima_producao,
                    COUNT(*) as eventos_registrados
                FROM cdi_performance
                WHERE recurso_id = ?
                  AND DATE(data_hora) >= ?
                  AND DATE(data_hora) <= ?
                GROUP BY recurso_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$recursoId, $dataInicio, $dataFim]);
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado) {
                return $resultado;
            }

            // Se não encontrou dados, retornar zeros
            return [
                'quantidade_realizada' => 0,
                'tempo_real_min' => 0,
                'tempo_parado_min' => 0,
                'primeira_producao' => $dataInicio,
                'ultima_producao' => $dataFim,
                'eventos_registrados' => 0
            ];

        } catch (Exception $e) {
            $this->log("Erro ao obter performance: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Persiste dados de eficiência calculados
     * 
     * @param array $eficiencia Dados calculados
     * @return bool Sucesso
     */
    private function persistirEficiencia($eficiencia)
    {
        try {
            $sql = "
                INSERT INTO cdi_eficiencia_medicao (
                    programacao_id,
                    recurso_id,
                    previsto_quantidade,
                    previsto_tempo_horas,
                    realizado_quantidade,
                    realizado_tempo_horas,
                    desvio_quantidade,
                    desvio_quantidade_perc,
                    desvio_tempo,
                    desvio_tempo_perc,
                    desvio_dias,
                    status_prazo,
                    taxa_eficiencia,
                    taxa_performance,
                    taxa_disponibilidade,
                    oee,
                    produtividade,
                    status_geral,
                    data_medicao
                ) VALUES (
                    :prog_id, :rec_id, :prev_qtd, :prev_tempo,
                    :real_qtd, :real_tempo, :des_qtd, :des_qtd_perc,
                    :des_tempo, :des_tempo_perc, :des_dias, :status_prazo,
                    :taxa_ef, :taxa_perf, :taxa_disp, :oee, :produt,
                    :status, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    realizado_quantidade = VALUES(realizado_quantidade),
                    realizado_tempo_horas = VALUES(realizado_tempo_horas),
                    desvio_quantidade = VALUES(desvio_quantidade),
                    desvio_quantidade_perc = VALUES(desvio_quantidade_perc),
                    desvio_tempo = VALUES(desvio_tempo),
                    desvio_tempo_perc = VALUES(desvio_tempo_perc),
                    taxa_eficiencia = VALUES(taxa_eficiencia),
                    taxa_performance = VALUES(taxa_performance),
                    oee = VALUES(oee),
                    status_geral = VALUES(status_geral),
                    atualizado_em = NOW()
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':prog_id' => $eficiencia['programacao_id'],
                ':rec_id' => $eficiencia['recurso_id'],
                ':prev_qtd' => $eficiencia['previsto']['quantidade'],
                ':prev_tempo' => $eficiencia['previsto']['tempo_padrao_horas'],
                ':real_qtd' => $eficiencia['realizado']['quantidade'],
                ':real_tempo' => $eficiencia['realizado']['tempo_real_horas'],
                ':des_qtd' => $eficiencia['desvios']['quantidade']['desvio_unidades'] ?? 0,
                ':des_qtd_perc' => $eficiencia['desvios']['quantidade']['desvio_percentual'] ?? 0,
                ':des_tempo' => $eficiencia['desvios']['tempo']['desvio_horas'] ?? 0,
                ':des_tempo_perc' => $eficiencia['desvios']['tempo']['desvio_percentual'] ?? 0,
                ':des_dias' => $eficiencia['desvios']['data']['dias_atraso'] ?? 0,
                ':status_prazo' => $eficiencia['desvios']['data']['status_prazo'] ?? 'desconhecido',
                ':taxa_ef' => $eficiencia['kpis']['eficiencia_quantidade'],
                ':taxa_perf' => $eficiencia['kpis']['performance_tempo'],
                ':taxa_disp' => $eficiencia['kpis']['disponibilidade'],
                ':oee' => $eficiencia['kpis']['oee'],
                ':produt' => $eficiencia['kpis']['produtividade_por_hora'],
                ':status' => $eficiencia['status']['geral']
            ]);

            $this->log("Eficiência persistida: prog_id={$eficiencia['programacao_id']}, OEE={$eficiencia['kpis']['oee']}%");
            return true;

        } catch (Exception $e) {
            $this->log("Erro ao persistir eficiência: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Registra log de atividades
     * 
     * @param string $mensagem Mensagem de log
     * @param string $nivel INFO|WARNING|ERROR
     */
    public function log($mensagem, $nivel = 'info')
    {
        if (!$this->logging) return;

        $timestamp = date('Y-m-d H:i:s');
        $this->logs[] = [
            'timestamp' => $timestamp,
            'nivel' => strtoupper($nivel),
            'mensagem' => $mensagem
        ];

        // Opcional: persistir em BD
        try {
            $sql = "INSERT INTO cdi_sincronizacao_log (tipo, mensagem, nivel, data_hora) VALUES (?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['eficiencia_calculator', $mensagem, strtoupper($nivel)]);
        } catch (Exception $e) {
            // Silenciosamente ignorar erro de log
        }
    }

    /**
     * Habilita/desabilita logging
     */
    public function setLogging($enabled)
    {
        $this->logging = $enabled;
        return $this;
    }

    /**
     * Obtém logs recentes
     */
    public function getLogs($nivel = null)
    {
        $logs = $this->logs;
        
        if ($nivel) {
            $logs = array_filter($logs, fn($l) => $l['nivel'] === strtoupper($nivel));
        }

        return $logs;
    }
}
