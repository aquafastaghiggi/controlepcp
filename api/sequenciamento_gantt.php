<?php
/**
 * API: Sequenciamento Gantt (Previsto x Realizado)
 * 
 * Retorna dados formatados para renderizar o gráfico Gantt
 * Integra: sch_linhas (previsto) + realizado_2026_excel (realizado)
 * 
 * Parâmetros:
 * - programacao_id: ID da programação
 * - data_inicio: YYYY-MM-DD (opcional)
 * - data_fim: YYYY-MM-DD (opcional)
 * - semana: número da semana (1-53, opcional - sobrescreve datas)
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Connection;

header('Content-Type: application/json; charset=utf-8');

// Autenticação (leniente)
try {
    Auth::startSession();
} catch (Exception $ignored) {}

try {
    $programacao_id = (int)($_GET['programacao_id'] ?? 0);
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim = $_GET['data_fim'] ?? null;
    $semana = (int)($_GET['semana'] ?? 0);
    
    if ($programacao_id <= 0) {
        throw new Exception('programacao_id é obrigatória');
    }
    
    $pdo = Connection::get();
    
    // Obter programação
    $stmt = $pdo->prepare("
        SELECT p.prg_id, p.prg_numero_op, l.lin_codigo
        FROM prg_programas p
        LEFT JOIN lin_linhas l ON p.prg_linha_id = l.lin_id
        WHERE p.prg_id = ?
    ");
    $stmt->execute([$programacao_id]);
    $programacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$programacao) {
        throw new Exception('Programação não encontrada');
    }
    
    // Se forneceu número de semana, calcular datas
    if ($semana > 0 && $semana <= 53) {
        $ano = date('Y');
        $inicio_semana = new DateTime();
        $inicio_semana->setISODate($ano, $semana, 1); // Primeira segunda-feira
        $fim_semana = clone $inicio_semana;
        $fim_semana->modify('+6 days'); // Até domingo
        
        $data_inicio = $inicio_semana->format('Y-m-d');
        $data_fim = $fim_semana->format('Y-m-d');
    }
    
    // Se não forneceu datas, usar período da programação
    if (!$data_inicio || !$data_fim) {
        $stmt = $pdo->prepare("
            SELECT MIN(sch_data_inicio) as inicio, MAX(sch_data_inicio) as fim
            FROM sch_linhas
            WHERE sch_programa_id = ?
        ");
        $stmt->execute([$programacao_id]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($period && $period['inicio']) {
            $data_inicio = $period['inicio'];
            $data_fim = $period['fim'] ?: $period['inicio'];
        } else {
            throw new Exception('Programação sem schedule');
        }
    }
    
    // ====== PREVISTO: Buscar dados de sch_linhas ======
    // Observacao: o schedule (sch_linhas) pode estar no nivel de SKU e nao trazer a OP diretamente.
    // Para ficar fiel ao PDF (OP por linha), resolvemos a OP em PHP usando prg_itens.
    $stmt = $pdo->prepare("
        SELECT prg_sku, prg_itens_op, prg_quantidade, prg_sequencia, prg_id_item
        FROM prg_itens
        WHERE prg_programa_id = ?
        ORDER BY prg_sequencia, prg_id_item
    ");
    $stmt->execute([$programacao_id]);
    $itens_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itens_by_sku = [];
    foreach ($itens_rows as $it) {
        $sku = (string)($it['prg_sku'] ?? '');
        if ($sku === '') continue;
        if (!isset($itens_by_sku[$sku])) $itens_by_sku[$sku] = [];
        $itens_by_sku[$sku][] = [
            'op' => (string)($it['prg_itens_op'] ?? 'SEM_OP'),
            'quantidade' => (float)($it['prg_quantidade'] ?? 0),
            'used' => false,
        ];
    }

    $stmt = $pdo->prepare("
        SELECT 
            s.sch_id,
            s.sch_sequencia,
            s.sch_descricao,
            s.sch_quantidade,
            s.sch_tipo,
            s.sch_sku,
            s.sch_data_inicio,
            s.sch_hora_inicio,
            s.sch_hora_fim,
            s.sch_duracao_minutos
        FROM sch_linhas s
        WHERE s.sch_programa_id = ?
        AND s.sch_data_inicio >= ?
        AND s.sch_data_inicio <= ?
        ORDER BY s.sch_data_inicio, s.sch_sequencia
    ");
    $stmt->execute([$programacao_id, $data_inicio, $data_fim]);
    $previsto_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ====== REALIZADO: Buscar dados agrupados por OP ======
    $stmt = $pdo->prepare("
        SELECT 
            ordem_op,
            SUM(quantidade) as total_realizado
        FROM realizado_2026_excel
        WHERE data_evento >= ? AND data_evento <= ?
        GROUP BY ordem_op
    ");
    $stmt->execute([$data_inicio, $data_fim]);
    $realizado_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapear realizado por OP
    $realizado_map = [];
    foreach ($realizado_rows as $row) {
        $realizado_map[(string)$row['ordem_op']] = (float)$row['total_realizado'];
    }
    
    // Calcular semana
    $dt_inicio = new DateTime($data_inicio);
    $semana_numero = (int)$dt_inicio->format('W');
    $semana_ano = $dt_inicio->format('Y');
    
    // Helper: Converter TIME string (HH:MM:SS) para horas decimais
    $timeToHours = function(?string $timeStr): float {
        if (!$timeStr) return 0.0;
        $parts = explode(':', trim($timeStr));
        if (count($parts) < 2) return 0.0;
        $horas = (int)$parts[0];
        $minutos = (int)$parts[1];
        $segundos = isset($parts[2]) ? (int)$parts[2] : 0;
        return $horas + ($minutos / 60.0) + ($segundos / 3600.0);
    };
    
    // Construir timeline
    $timeline = [];
    $total_previsto = 0;
    $total_realizado = 0;
    
    foreach ($previsto_rows as $row) {
        $rawTipo = strtolower(trim((string)($row['sch_tipo'] ?? '')));
        $isSetup = ($rawTipo === 'setup');

        $quantidade_prevista = $isSetup ? 0.0 : (float)($row['sch_quantidade'] ?? 0);

        // Resolver OP para producao quando houver mais de uma OP por SKU.
        // Regras:
        // 1) tenta casar pela quantidade prevista do schedule
        // 2) se nao casar, consome a primeira OP ainda nao usada daquele SKU
        $ordem_op = $isSetup ? 'SETUP' : 'SEM_OP';
        if (!$isSetup) {
            $sku = (string)($row['sch_sku'] ?? '');
            if ($sku !== '' && isset($itens_by_sku[$sku]) && count($itens_by_sku[$sku]) > 0) {
                $pickedIdx = null;

                foreach ($itens_by_sku[$sku] as $idx => $it) {
                    if ($it['used']) continue;
                    if (abs(((float)$it['quantidade']) - $quantidade_prevista) < 0.0001) {
                        $pickedIdx = $idx;
                        break;
                    }
                }

                if ($pickedIdx === null) {
                    foreach ($itens_by_sku[$sku] as $idx => $it) {
                        if (!$it['used']) {
                            $pickedIdx = $idx;
                            break;
                        }
                    }
                }

                if ($pickedIdx !== null) {
                    $ordem_op = (string)$itens_by_sku[$sku][$pickedIdx]['op'];
                    $itens_by_sku[$sku][$pickedIdx]['used'] = true;
                }
            }
        }

        $quantidade_realizada = $isSetup ? 0.0 : ($realizado_map[$ordem_op] ?? 0.0);
        
        // Converter TIME (HH:MM:SS) para horas decimais
        $hora_inicio_h = $timeToHours($row['sch_hora_inicio']);
        $hora_fim_h = $timeToHours($row['sch_hora_fim']);
        
        // Se hora_fim <= hora_inicio, usar duração_minutos ou default 1 hora
        if ($hora_fim_h <= $hora_inicio_h && !empty($row['sch_duracao_minutos'])) {
            $hora_fim_h = $hora_inicio_h + ((int)$row['sch_duracao_minutos'] / 60.0);
        } elseif ($hora_fim_h <= $hora_inicio_h) {
            $hora_fim_h = $hora_inicio_h + 1.0; // 1 hora default
        }
        
        // Calcular posição absoluta na timeline (dias + horas desde início)
        $dt_data = new DateTime($row['sch_data_inicio']);
        $dias_offset = $dt_data->diff($dt_inicio)->days;
        
        $start_hours = round($dias_offset * 24 + $hora_inicio_h, 2);
        $end_hours = round($dias_offset * 24 + $hora_fim_h, 2);
        $duracao_horas = round($end_hours - $start_hours, 2);
        
        // Determinar status
        if ($isSetup) {
            $status = 'setup';
        } elseif ($quantidade_realizada > 0) {
            $percentual = $quantidade_prevista > 0 ? ($quantidade_realizada / $quantidade_prevista) * 100 : 100;
            if ($percentual >= 100) {
                $status = 'done'; // Concluido
            } else {
                $status = 'running'; // Em execucao / parcial
            }
        } else {
            $status = 'planned'; // Planejado / nao iniciado
        }
        
        // Percentual de cumprimento (realizado vs previsto)
        $pct_cumprimento = $quantidade_prevista > 0
            ? round(($quantidade_realizada / $quantidade_prevista) * 100, 1) 
            : 0;
        
        // Formatar horas e data
        $hora_inicio_fmt = sprintf('%02d:%02d', (int)$hora_inicio_h, (int)(($hora_inicio_h - (int)$hora_inicio_h) * 60));
        $hora_fim_fmt = sprintf('%02d:%02d', (int)$hora_fim_h, (int)(($hora_fim_h - (int)$hora_fim_h) * 60));
        
        $timeline[] = [
            'id' => (string)$row['sch_id'],
            'op' => $ordem_op,
            'nome' => $row['sch_descricao'] ?: $ordem_op,
            'start' => round($start_hours, 2),
            'end' => round($end_hours, 2),
            'duracao_horas' => $duracao_horas,
            'status' => $status,
            'tipo' => $isSetup ? 'setup' : 'producao',
            'dia' => $dias_offset,
            'data' => $row['sch_data_inicio'],
            'hora_inicio' => $hora_inicio_fmt,
            'hora_fim' => $hora_fim_fmt,
            'quantidade_prevista' => $quantidade_prevista,
            'quantidade_realizada' => $quantidade_realizada,
            'percentual_cumprimento' => $pct_cumprimento,
            'duracao_minutos' => (int)$row['sch_duracao_minutos']
        ];
        
        if (!$isSetup) {
            $total_previsto += $quantidade_prevista;
            $total_realizado += $quantidade_realizada;
        }
    }
    
    // Calcular métricas
    $ordens = array_filter($timeline, fn($item) => $item['tipo'] === 'producao');
    $setups = array_filter($timeline, fn($item) => $item['tipo'] === 'setup');
    
    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'programacao' => [
            'id' => (int)$programacao['prg_id'],
            'numero' => $programacao['prg_numero_op'],
            'linha' => $programacao['lin_codigo'] ?? 'N/A'
        ],
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim,
            'semana' => $semana_numero,
            'ano' => $semana_ano
        ],
        'metricas' => [
            'total_linhas' => count($timeline),
            'producoes' => count($ordens),
            'setups' => count($setups),
            'total_previsto' => round($total_previsto, 2),
            'total_realizado' => round($total_realizado, 2),
            'diferenca' => round($total_realizado - $total_previsto, 2),
            'percentual' => $total_previsto > 0 ? round(($total_realizado / $total_previsto) * 100, 1) : 0
        ],
        'timeline' => $timeline
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'programacao_id' => $_GET['programacao_id'] ?? 'não fornecido'
        ]
    ], JSON_UNESCAPED_UNICODE);
    error_log('API sequenciamento_gantt error: ' . $e->getMessage());
}
