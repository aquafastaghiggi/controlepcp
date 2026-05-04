<?php
// encoding: UTF-8
/**
 * Gantt PCP - Modelo visual por Semana / Dia / Hora
 * Tela experimental: não substitui o gantt.php principal.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Repository\ProgramacaoRepository;
use App\Database\Connection;

Auth::startSession();

$repo = new ProgramacaoRepository();
$pdo = Connection::get();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function brDateTime(?string $value): string
{
    if (!$value || strtotime($value) === false) {
        return '-';
    }
    return date('d/m H:i', strtotime($value));
}

function brDateTimeSmart(?string $startValue, ?string $endValue): array
{
    if (!$startValue || !$endValue || strtotime($startValue) === false || strtotime($endValue) === false) {
        return [brDateTime($startValue), brDateTime($endValue)];
    }

    $startTs = strtotime($startValue);
    $endTs = strtotime($endValue);
    if ($startTs === false || $endTs === false) {
        return [brDateTime($startValue), brDateTime($endValue)];
    }

    // Quando cai no mesmo minuto, exibir segundos para não parecer duração zero.
    if (date('Y-m-d H:i', $startTs) === date('Y-m-d H:i', $endTs)) {
        return [date('d/m H:i:s', $startTs), date('d/m H:i:s', $endTs)];
    }

    return [date('d/m H:i', $startTs), date('d/m H:i', $endTs)];
}

function brDate(?DateTimeInterface $date): string
{
    return $date ? $date->format('d/m/Y') : '-';
}

function weekLabel(DateTimeInterface $date): string
{
    return 'SEMANA ' . $date->format('W');
}

function dayLabel(DateTimeInterface $date): string
{
    $dias = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];
    return $dias[(int) $date->format('w')] . '<br><small>' . $date->format('d/m') . '</small>';
}

function isSunday(DateTimeInterface $date): bool
{
    return (int) $date->format('w') === 0;
}

function isWeekend(DateTimeInterface $date): bool
{
    $w = (int) $date->format('w');
    return $w === 0 || $w === 6;
}

function tableExists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?
             LIMIT 1"
        );
        $stmt->execute([$tableName]);
        $cache[$tableName] = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function isSetupTargetParada(?string $nomeParada): bool
{
    $nomeParada = strtoupper(trim((string) $nomeParada));
    return in_array($nomeParada, ['TROCA DE KIT', 'TROCA DE LIQUIDO'], true);
}

function normalizeLineLabel(?string $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'S/Linha';
    }
    if (preg_match('/^(?:linha|ln)\s*0*(\d+)$/iu', $raw, $m) === 1 || preg_match('/^0*(\d+)$/u', $raw, $m) === 1) {
        return 'Linha ' . str_pad((string) (int) $m[1], 2, '0', STR_PAD_LEFT);
    }
    return $raw;
}

function minutesInVisibleAxis(DateTimeInterface $date, array $dayOffsets, int $visibleStartHour, int $visibleEndHour): ?int
{
    $key = $date->format('Y-m-d');
    if (!array_key_exists($key, $dayOffsets)) {
        return null;
    }

    $hour = (int) $date->format('H');
    $minute = (int) $date->format('i');
    $rawMinutes = ($hour * 60) + $minute;
    $start = $visibleStartHour * 60;
    $end = $visibleEndHour * 60;
    $clamped = max($start, min($end, $rawMinutes));

    return ($dayOffsets[$key] * (($visibleEndHour - $visibleStartHour) * 60)) + ($clamped - $start);
}

function buildVisibleSegments(string $startValue, string $endValue, array $dayOffsets, int $visibleStartHour, int $visibleEndHour): array
{
    $startTs = strtotime($startValue);
    $endTs = strtotime($endValue);
    if ($startTs === false || $endTs === false || $endTs <= $startTs) {
        return [];
    }

    $start = new DateTimeImmutable(date('Y-m-d H:i:s', $startTs));
    $end = new DateTimeImmutable(date('Y-m-d H:i:s', $endTs));

    $visibleDates = array_keys($dayOffsets);
    if (empty($visibleDates)) {
        return [];
    }

    $firstVisible = new DateTimeImmutable($visibleDates[0] . ' ' . str_pad((string) $visibleStartHour, 2, '0', STR_PAD_LEFT) . ':00:00');
    $lastVisible = new DateTimeImmutable(end($visibleDates) . ' ' . str_pad((string) $visibleEndHour, 2, '0', STR_PAD_LEFT) . ':00:00');

    if ($end <= $firstVisible || $start >= $lastVisible) {
        return [];
    }

    if ($start < $firstVisible) {
        $start = $firstVisible;
    }
    if ($end > $lastVisible) {
        $end = $lastVisible;
    }

    $moveStartToVisible = static function (DateTimeImmutable $dt) use ($dayOffsets, $visibleStartHour, $visibleEndHour): ?DateTimeImmutable {
        for ($i = 0; $i < 14; $i++) {
            $dateKey = $dt->format('Y-m-d');
            if (isset($dayOffsets[$dateKey])) {
                $dayStart = $dt->setTime($visibleStartHour, 0);
                $dayEnd = $dt->setTime($visibleEndHour, 0);
                if ($dt < $dayStart) {
                    return $dayStart;
                }
                if ($dt >= $dayEnd) {
                    $dt = $dt->modify('+1 day')->setTime($visibleStartHour, 0);
                    continue;
                }
                return $dt;
            }
            $dt = $dt->modify('+1 day')->setTime($visibleStartHour, 0);
        }
        return null;
    };

    $moveEndToVisible = static function (DateTimeImmutable $dt) use ($dayOffsets, $visibleStartHour, $visibleEndHour): ?DateTimeImmutable {
        for ($i = 0; $i < 14; $i++) {
            $dateKey = $dt->format('Y-m-d');
            if (isset($dayOffsets[$dateKey])) {
                $dayStart = $dt->setTime($visibleStartHour, 0);
                $dayEnd = $dt->setTime($visibleEndHour, 0);
                if ($dt > $dayEnd) {
                    return $dayEnd;
                }
                if ($dt <= $dayStart) {
                    $dt = $dt->modify('-1 day')->setTime($visibleEndHour, 0);
                    continue;
                }
                return $dt;
            }
            $dt = $dt->modify('-1 day')->setTime($visibleEndHour, 0);
        }
        return null;
    };

    $start = $moveStartToVisible($start);
    $end = $moveEndToVisible($end);
    if (!$start || !$end || $end <= $start) {
        return [];
    }

    $left = minutesInVisibleAxis($start, $dayOffsets, $visibleStartHour, $visibleEndHour);
    $right = minutesInVisibleAxis($end, $dayOffsets, $visibleStartHour, $visibleEndHour);

    if ($left === null || $right === null || $right <= $left) {
        return [];
    }

    // Um único segmento contínuo na escala comprimida.
    // Assim, quando um item atravessa dias intermediários, a barra não "some" no miolo.
    // Sábados e domingos continuam removidos porque eles não existem em $dayOffsets.
    return [['left' => $left, 'width' => $right - $left]];
}

$palette = [
    'prod' => '#2f78c4',
    'setup' => '#f57c00',
    'setupReal' => '#7c3aed',
    'setupMissing' => '#f97316',
    'ok' => '#1fa34a',
    'real' => '#d71920',
    'header' => '#082344',
    'grid' => '#dbe4ee',
    'gridStrong' => '#aebdcb',
    'muted' => '#5b6775',
];

$programacoes = $repo->getAllProgramacoes(100, 0);
$selectedProgramId = (int) ($_GET['programacao_id'] ?? $_GET['id'] ?? 0);
if ($selectedProgramId <= 0 && !empty($programacoes)) {
    $selectedProgramId = (int) $programacoes[0]['prg_id'];
}

$programacaoInfo = $selectedProgramId > 0 ? $repo->getProgramacaoById($selectedProgramId) : null;

$schedule = [];
if ($selectedProgramId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM sch_linhas WHERE sch_programa_id = :id ORDER BY sch_inicio_producao ASC, sch_sequencia ASC, sch_id ASC");
    $stmt->execute(['id' => $selectedProgramId]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar OPs por programa + SKU, mesma estratégia do gantt.php atual.
$opBuckets = [];
if (!empty($schedule)) {
    $programIds = array_values(array_unique(array_map(static fn(array $row): int => (int) ($row['sch_programa_id'] ?? 0), $schedule)));
    $programIds = array_values(array_filter($programIds, static fn(int $id): bool => $id > 0));
    if (!empty($programIds)) {
        $placeholders = implode(',', array_fill(0, count($programIds), '?'));
        $stmtOp = $pdo->prepare("SELECT prg_programa_id, prg_sku, prg_quantidade, prg_sequencia, prg_id_item, prg_itens_op FROM prg_itens WHERE prg_programa_id IN ($placeholders) ORDER BY prg_programa_id ASC, prg_sequencia ASC, prg_id_item ASC");
        $stmtOp->execute($programIds);
        foreach ($stmtOp->fetchAll(PDO::FETCH_ASSOC) as $opRow) {
            $key = $opRow['prg_programa_id'] . '|' . $opRow['prg_sku'];
            $opBuckets[$key] ??= [];
            $opBuckets[$key][] = [
                'op' => (string) ($opRow['prg_itens_op'] ?? 'S/OP'),
                'quantidade' => (float) ($opRow['prg_quantidade'] ?? 0),
                'used' => false,
            ];
        }
    }
}

$assignedOps = [];
foreach ($schedule as $row) {
    $schId = (int) ($row['sch_id'] ?? 0);
    $isSetup = strtolower(trim((string) ($row['sch_tipo'] ?? ''))) === 'setup';
    $assignedOps[$schId] = 'S/OP';
    if ($isSetup || empty($row['sch_sku'])) {
        continue;
    }

    $key = ((int) ($row['sch_programa_id'] ?? 0)) . '|' . $row['sch_sku'];
    if (empty($opBuckets[$key])) {
        continue;
    }

    $qtd = (float) ($row['sch_quantidade'] ?? 0);
    $picked = null;
    foreach ($opBuckets[$key] as $idx => $item) {
        if (!$item['used'] && abs($item['quantidade'] - $qtd) < 0.0001) {
            $picked = $idx;
            break;
        }
    }
    if ($picked === null) {
        foreach ($opBuckets[$key] as $idx => $item) {
            if (!$item['used']) {
                $picked = $idx;
                break;
            }
        }
    }
    if ($picked !== null) {
        $assignedOps[$schId] = $opBuckets[$key][$picked]['op'];
        $opBuckets[$key][$picked]['used'] = true;
    }
}

// Base unificada conforme relgantt.php:
// A leitura de previsto x realizado deve ser por OP, não por janela individual da linha do Gantt.
// - Produção prevista: soma de sch_linhas.sch_quantidade por OP resolvida
// - Produção realizada: realizado_2026_excel.quantidade agrupado por ordem_op
// - Setup previsto: sch_linhas.sch_duracao_minutos acumulado até a próxima OP
// - Setup realizado: realizado_2026_excel.setup_duracao_minutos para TROCA DE KIT / TROCA DE LIQUIDO
$programPeriodStart = null;
$programPeriodEnd = null;
foreach ($schedule as $schedRow) {
    $schedStart = trim((string) ($schedRow['sch_inicio_producao'] ?? ''));
    $schedEnd = trim((string) ($schedRow['sch_fim_producao'] ?? ''));

    if ($schedStart !== '' && strtotime($schedStart) !== false) {
        $day = date('Y-m-d', strtotime($schedStart));
        $programPeriodStart = $programPeriodStart === null ? $day : min($programPeriodStart, $day);
    }
    if ($schedEnd !== '' && strtotime($schedEnd) !== false) {
        $day = date('Y-m-d', strtotime($schedEnd));
        $programPeriodEnd = $programPeriodEnd === null ? $day : max($programPeriodEnd, $day);
    }
}
if ($programPeriodStart !== null) {
    $programPeriodStart = (new DateTimeImmutable($programPeriodStart))->modify('-1 day')->format('Y-m-d');
}
if ($programPeriodEnd !== null) {
    $programPeriodEnd = (new DateTimeImmutable($programPeriodEnd))->modify('+1 day')->format('Y-m-d');
}

$prodPlanByOp = [];
$setupRowPlanMinutes = [];
$setupRowNextOp = [];
$setupPlanTotalByOp = [];
$pendingSetupIds = [];

foreach ($schedule as $schedRow) {
    $schedId = (int) ($schedRow['sch_id'] ?? 0);
    $schedType = strtolower(trim((string) ($schedRow['sch_tipo'] ?? '')));

    if ($schedType === 'setup') {
        $setupStart = (string) ($schedRow['sch_inicio_producao'] ?? '');
        $setupEnd = (string) ($schedRow['sch_fim_producao'] ?? '');
        $duration = (float) ($schedRow['sch_duracao_minutos'] ?? 0);
        if ($duration <= 0 && $setupStart !== '' && $setupEnd !== '' && strtotime($setupStart) !== false && strtotime($setupEnd) !== false) {
            $duration = max(0.0, (strtotime($setupEnd) - strtotime($setupStart)) / 60);
        }

        $setupRowPlanMinutes[$schedId] = $duration;
        $pendingSetupIds[] = $schedId;
        continue;
    }

    $op = $assignedOps[$schedId] ?? 'S/OP';
    if ($op !== 'S/OP') {
        $prodPlanByOp[$op] = ($prodPlanByOp[$op] ?? 0.0) + (float) ($schedRow['sch_quantidade'] ?? 0);
    }

    // Igual ao relgantt.php: o setup pendente é atribuído à próxima OP de produção.
    if ($op !== 'S/OP' && !empty($pendingSetupIds)) {
        foreach ($pendingSetupIds as $setupId) {
            $setupRowNextOp[$setupId] = $op;
            $setupPlanTotalByOp[$op] = ($setupPlanTotalByOp[$op] ?? 0.0) + (float) ($setupRowPlanMinutes[$setupId] ?? 0.0);
        }
        $pendingSetupIds = [];
    } elseif ($op === 'S/OP') {
        $pendingSetupIds = [];
    }
}

$realizadoByOp = [];
$opsToRead = array_values(array_unique(array_filter(array_keys($prodPlanByOp + $setupPlanTotalByOp), static fn(string $op): bool => $op !== '' && $op !== 'S/OP')));

if (!empty($opsToRead) && $programPeriodStart !== null && $programPeriodEnd !== null && tableExists($pdo, 'realizado_2026_excel')) {
    $placeholders = implode(',', array_fill(0, count($opsToRead), '?'));
    try {
        $stmtReal = $pdo->prepare(
            "
            SELECT
                ordem_op,
                SUM(quantidade) AS total_realizado,
                SUM(
                    CASE
                        WHEN parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                        THEN COALESCE(setup_duracao_minutos, 0)
                        ELSE 0
                    END
                ) AS setup_realizado_min,
                SUM(
                    CASE
                        WHEN parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                        THEN COALESCE(setup_eventos_count, 0)
                        ELSE 0
                    END
                ) AS setup_realizado_eventos,
                MIN(
                    CASE
                        WHEN inicio_evento IS NOT NULL
                             AND LENGTH(TRIM(inicio_evento)) > 0
                        THEN inicio_evento
                        ELSE NULL
                    END
                ) AS inicio_real,
                MAX(
                    CASE
                        WHEN fim_evento IS NOT NULL
                             AND LENGTH(TRIM(fim_evento)) > 0
                        THEN fim_evento
                        ELSE NULL
                    END
                ) AS fim_real,
                MIN(
                    CASE
                        WHEN parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                             AND inicio_evento IS NOT NULL
                             AND LENGTH(TRIM(inicio_evento)) > 0
                        THEN inicio_evento
                        ELSE NULL
                    END
                ) AS setup_inicio_real,
                MAX(
                    CASE
                        WHEN parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                             AND fim_evento IS NOT NULL
                             AND LENGTH(TRIM(fim_evento)) > 0
                        THEN fim_evento
                        ELSE NULL
                    END
                ) AS setup_fim_real
            FROM realizado_2026_excel
            WHERE data_evento BETWEEN ? AND ?
              AND ordem_op IN ($placeholders)
            GROUP BY ordem_op
            "
        );
        $stmtReal->execute(array_merge([$programPeriodStart, $programPeriodEnd], $opsToRead));

        foreach ($stmtReal->fetchAll(PDO::FETCH_ASSOC) as $realRow) {
            $op = trim((string) ($realRow['ordem_op'] ?? ''));
            if ($op === '') {
                continue;
            }

            $realizadoByOp[$op] = [
                'total' => (float) ($realRow['total_realizado'] ?? 0),
                'inicio' => $realRow['inicio_real'] ?? null,
                'fim' => $realRow['fim_real'] ?? null,
                'setup_minutes' => (float) ($realRow['setup_realizado_min'] ?? 0),
                'setup_events' => (int) ($realRow['setup_realizado_eventos'] ?? 0),
                'setup_inicio' => $realRow['setup_inicio_real'] ?? null,
                'setup_fim' => $realRow['setup_fim_real'] ?? null,
            ];
        }
    } catch (Throwable $e) {
        $realizadoByOp = [];
    }
}

// Ajuste de janela por horario (CODI):
// - Para PRODUCAO, usar apenas eventos com estado_evento='PRODUCAO' (evita que setup/paradas antecipem a barra da OP).
// - Para SETUP principal (TROCA DE KIT / TROCA DE LIQUIDO), usar os eventos PARADA alvo.
if (!empty($realizadoByOp) && $programPeriodStart !== null && $programPeriodEnd !== null && tableExists($pdo, 'realizado_2026_eventos')) {
    $placeholders = implode(',', array_fill(0, count($opsToRead), '?'));
    try {
        $stmtEvt = $pdo->prepare(
            "
            SELECT
                ordem_op,
                MIN(
                    CASE
                        WHEN estado_evento = 'PRODUCAO'
                             AND inicio_evento IS NOT NULL
                             AND LENGTH(TRIM(inicio_evento)) > 0
                        THEN inicio_evento
                        ELSE NULL
                    END
                ) AS prod_inicio,
                MAX(
                    CASE
                        WHEN estado_evento = 'PRODUCAO'
                             AND fim_evento IS NOT NULL
                             AND LENGTH(TRIM(fim_evento)) > 0
                        THEN fim_evento
                        ELSE NULL
                    END
                ) AS prod_fim,
                MIN(
                    CASE
                        WHEN estado_evento = 'PARADA'
                             AND parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                             AND inicio_evento IS NOT NULL
                             AND LENGTH(TRIM(inicio_evento)) > 0
                        THEN inicio_evento
                        ELSE NULL
                    END
                ) AS setup_inicio,
                MAX(
                    CASE
                        WHEN estado_evento = 'PARADA'
                             AND parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                             AND fim_evento IS NOT NULL
                             AND LENGTH(TRIM(fim_evento)) > 0
                        THEN fim_evento
                        ELSE NULL
                    END
                ) AS setup_fim,
                SUM(
                    CASE
                        WHEN estado_evento = 'PARADA'
                             AND parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                        THEN COALESCE(duracao_evento_minutos, 0)
                        ELSE 0
                    END
                ) AS setup_minutes,
                SUM(
                    CASE
                        WHEN estado_evento = 'PARADA'
                             AND parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                        THEN 1
                        ELSE 0
                    END
                ) AS setup_events
            FROM realizado_2026_eventos
            WHERE data_evento BETWEEN ? AND ?
              AND ordem_op IN ($placeholders)
            GROUP BY ordem_op
            "
        );
        $stmtEvt->execute(array_merge([$programPeriodStart, $programPeriodEnd], $opsToRead));

        foreach ($stmtEvt->fetchAll(PDO::FETCH_ASSOC) as $evtRow) {
            $op = trim((string) ($evtRow['ordem_op'] ?? ''));
            if ($op === '' || !isset($realizadoByOp[$op])) {
                continue;
            }

            $prodInicio = $evtRow['prod_inicio'] ?? null;
            $prodFim = $evtRow['prod_fim'] ?? null;
            if (!empty($prodInicio) && !empty($prodFim) && strtotime((string) $prodInicio) !== false && strtotime((string) $prodFim) !== false) {
                $realizadoByOp[$op]['inicio'] = $prodInicio;
                $realizadoByOp[$op]['fim'] = $prodFim;
            }

            $setupInicio = $evtRow['setup_inicio'] ?? null;
            $setupFim = $evtRow['setup_fim'] ?? null;
            if (!empty($setupInicio) && strtotime((string) $setupInicio) !== false) {
                $realizadoByOp[$op]['setup_inicio'] = $setupInicio;
            }
            if (!empty($setupFim) && strtotime((string) $setupFim) !== false) {
                $realizadoByOp[$op]['setup_fim'] = $setupFim;
            }

            $setupMinutes = isset($evtRow['setup_minutes']) ? (float) $evtRow['setup_minutes'] : 0.0;
            $setupEvents = isset($evtRow['setup_events']) ? (int) $evtRow['setup_events'] : 0;
            if ($setupEvents > 0) {
                $realizadoByOp[$op]['setup_minutes'] = $setupMinutes;
                $realizadoByOp[$op]['setup_events'] = $setupEvents;
            }
        }
    } catch (Throwable $e) {
        // Mantem fallback no agregado do realizado_2026_excel.
    }
}

$rows = [];
$minTs = null;
$maxTs = null;
foreach ($schedule as $rowIdx => $row) {
    $start = (string) ($row['sch_inicio_producao'] ?? '');
    $end = (string) ($row['sch_fim_producao'] ?? '');
    if ($start === '' || $end === '' || strtotime($start) === false || strtotime($end) === false) {
        continue;
    }
    $isSetup = strtolower(trim((string) ($row['sch_tipo'] ?? ''))) === 'setup';
    $schId = (int) ($row['sch_id'] ?? 0);
    $op = $isSetup ? 'S/OP' : ($assignedOps[$schId] ?? 'S/OP');
    $real = (!$isSetup && $op !== 'S/OP')
        ? ($realizadoByOp[$op] ?? ['total' => 0, 'inicio' => null, 'fim' => null])
        : ['total' => 0, 'inicio' => null, 'fim' => null];

    $setupNextOp = $isSetup ? (string) ($setupRowNextOp[$schId] ?? 'S/OP') : '';
    $setupReal = ($isSetup && $setupNextOp !== 'S/OP')
        ? ($realizadoByOp[$setupNextOp] ?? ['setup_minutes' => 0, 'setup_events' => 0, 'setup_inicio' => null, 'setup_fim' => null])
        : ['setup_minutes' => 0, 'setup_events' => 0, 'setup_inicio' => null, 'setup_fim' => null];

    // Datas da OP conforme relgantt.php:
    // para produção, quando existir CODI, usar MIN(inicio_evento) e MAX(fim_evento)
    // vindos de realizado_2026_excel. Isso evita mostrar a OP na data errada do envelope
    // do sch_linhas quando o relatório analítico já tem a janela real correta.
    $visualStart = $start;
    $visualEnd = $end;
    if (!$isSetup) {
        if (!empty($real['inicio']) && strtotime((string) $real['inicio']) !== false) {
            $visualStart = (string) $real['inicio'];
        }
        if (!empty($real['fim']) && strtotime((string) $real['fim']) !== false) {
            $visualEnd = (string) $real['fim'];
        }
    }

    // Para SETUP, quando existir evento real no CODI, a data visual deve vir do próprio evento.
    // A data do sch_linhas pode estar como envelope/posição planejada e ficar distante da OP.
    if ($isSetup && !empty($setupReal['setup_inicio']) && strtotime((string) $setupReal['setup_inicio']) !== false) {
        $visualStart = (string) $setupReal['setup_inicio'];
        $setupDurationForVisual = (float) ($setupRowPlanMinutes[$schId] ?? (float) ($row['sch_duracao_minutos'] ?? 0));
        if ($setupDurationForVisual > 0) {
            $visualEnd = date('Y-m-d H:i:s', strtotime($visualStart) + ((int) round($setupDurationForVisual) * 60));
        } elseif (!empty($setupReal['setup_fim']) && strtotime((string) $setupReal['setup_fim']) !== false) {
            $visualEnd = (string) $setupReal['setup_fim'];
        }
    }

    $rows[] = [
        'id' => $schId,
        'is_setup' => $isSetup,
        'op' => $op,
        'sku' => (string) ($row['sch_sku'] ?? ''),
        'descricao' => trim((string) ($row['sch_descricao'] ?? ($isSetup ? 'Setup' : '-'))),
        // Mantém o visual do Gantt por LINHA do cronograma.
        // A fonte do realizado continua sendo a mesma do relgantt.php, porém distribuída proporcionalmente
        // quando uma mesma OP aparece em mais de uma linha do cronograma.
        'qtd_prev' => (float) ($row['sch_quantidade'] ?? 0),
        'qtd_prev_op' => !$isSetup && $op !== 'S/OP' ? (float) ($prodPlanByOp[$op] ?? (float) ($row['sch_quantidade'] ?? 0)) : (float) ($row['sch_quantidade'] ?? 0),
        'qtd_real_op' => (float) ($real['total'] ?? 0),
        'qtd_real' => (!$isSetup && $op !== 'S/OP' && (float) ($prodPlanByOp[$op] ?? 0) > 0)
            ? ((float) ($real['total'] ?? 0) * ((float) ($row['sch_quantidade'] ?? 0) / (float) ($prodPlanByOp[$op] ?? 1)))
            : (float) ($real['total'] ?? 0),
        'start' => $visualStart,
        'end' => $visualEnd,
        'schedule_start' => $start,
        'schedule_end' => $end,
        'real_start' => $real['inicio'] ?? null,
        'real_end' => $real['fim'] ?? null,
        'setup_next_op' => $setupNextOp,
        'setup_prev_min' => $isSetup ? (float) ($setupRowPlanMinutes[$schId] ?? (float) ($row['sch_duracao_minutos'] ?? 0)) : 0.0,
        'setup_real_min' => $isSetup ? (float) ($setupReal['setup_minutes'] ?? 0) : 0.0,
        'setup_real_events' => $isSetup ? (int) ($setupReal['setup_events'] ?? 0) : 0,
        'setup_real_start' => $setupReal['setup_inicio'] ?? null,
        'setup_real_end' => $setupReal['setup_fim'] ?? null,
    ];
    $minTs = $minTs === null ? strtotime($start) : min($minTs, strtotime($start));
    $maxTs = $maxTs === null ? strtotime($end) : max($maxTs, strtotime($end));
    if (!empty($real['inicio']) && strtotime((string) $real['inicio']) !== false) {
        $minTs = min($minTs, strtotime((string) $real['inicio']));
    }
    if (!empty($real['fim']) && strtotime((string) $real['fim']) !== false) {
        $maxTs = max($maxTs, strtotime((string) $real['fim']));
    }
}

$visibleStartHour = (int) ($_GET['hora_inicio'] ?? 0);
$visibleEndHour = (int) ($_GET['hora_fim'] ?? 24);
$visibleStartHour = max(0, min(23, $visibleStartHour));
$visibleEndHour = max($visibleStartHour + 1, min(24, $visibleEndHour));

$days = [];
if ($minTs !== null && $maxTs !== null) {
    $startDay = (new DateTimeImmutable(date('Y-m-d 00:00:00', $minTs)))->modify('-1 day');
    $endDay = new DateTimeImmutable(date('Y-m-d 00:00:00', $maxTs));
    for ($d = $startDay; $d <= $endDay; $d = $d->modify('+1 day')) {
        if (!isWeekend($d)) {
            $days[] = $d;
        }
    }
}

$dayOffsets = [];
foreach ($days as $idx => $day) {
    $dayOffsets[$day->format('Y-m-d')] = $idx;
}

$minutesPerDay = ($visibleEndHour - $visibleStartHour) * 60;
$totalMinutes = max(1, count($days) * $minutesPerDay);
$timelineWidth = max(1200, count($days) * 190);
$pxPerMinute = $timelineWidth / $totalMinutes;

$weekGroups = [];
foreach ($days as $idx => $day) {
    $key = $day->format('o-W');
    if (!isset($weekGroups[$key])) {
        $weekGroups[$key] = ['label' => weekLabel($day), 'start' => $idx, 'count' => 0, 'start_date' => $day, 'end_date' => $day];
    }
    $weekGroups[$key]['count']++;
    $weekGroups[$key]['end_date'] = $day;
}

$lineLabel = 'Linha';
if ($programacaoInfo) {
    $lineLabel = normalizeLineLabel((string) ($programacaoInfo['linha_excel_dominante'] ?? $programacaoInfo['lin_codigo'] ?? 'Linha'));
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gantt PCP - Modelo Hora</title>
    <style>
        :root {
            --prod: <?= e($palette['prod']) ?>;
            --setup: <?= e($palette['setup']) ?>;
            --setup-real: <?= e($palette['setupReal']) ?>;
            --setup-real-good: #16a34a;
            --setup-real-bad: #dc2626;
            --setup-real-equal: #2563eb;
            --setup-missing: <?= e($palette['setupMissing']) ?>;
            --ok: <?= e($palette['ok']) ?>;
            --real: <?= e($palette['real']) ?>;
            --header: <?= e($palette['header']) ?>;
            --grid: <?= e($palette['grid']) ?>;
            --grid-strong: <?= e($palette['gridStrong']) ?>;
            --muted: <?= e($palette['muted']) ?>;
            --left-width: 475px;
            --timeline-width: <?= (int) $timelineWidth ?>px;
            --row-height: 108px;
            --hour-header-height: 30px;
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; font-family: Arial, Helvetica, sans-serif; background: #f3f6fa; color: #102033; }
        .page { background: #fff; border: 1px solid #d8e0ea; box-shadow: 0 4px 18px rgba(15, 35, 60, .10); overflow: hidden; }
        .top { padding: 18px 22px 14px; display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; }
        .title h1 { margin: 0; color: var(--header); font-size: 28px; letter-spacing: -.4px; }
        .title .sub { margin-top: 8px; font-size: 16px; color: #16395f; letter-spacing: .2px; }
        .actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        select, input { border: 1px solid #c6d2df; border-radius: 8px; padding: 8px 10px; background: #fff; }
.btn { background: var(--header); color: white; text-decoration: none; border: 0; border-radius: 8px; padding: 9px 12px; font-weight: 700; cursor: pointer; }
.btn.btn-sync {
    background: #16a34a;
}
.btn.btn-sync:hover {
    background: #15803d;
}
.btn.btn-analitico,
.btn.btn-voltar {
    background: #0f2d59;
}
.btn.btn-analitico:hover,
.btn.btn-voltar:hover {
    background: #0b2346;
}

.app-build-badge {
    display: inline-flex;
    align-items: center;
    margin-left: 10px;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.88);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    line-height: 1;
    white-space: nowrap;
}

.header-nav-buttons {
    display: inline-flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
body.sync-modal-open {
    overflow: hidden;
}
.sync-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(7, 18, 38, 0.60);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(2px);
}
.sync-modal-overlay.is-open {
    display: flex;
}
.sync-modal {
    width: min(520px, calc(100vw - 24px));
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 22px 56px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}
.sync-modal__head {
    padding: 18px 20px 12px;
    border-bottom: 1px solid #e5e7eb;
}
.sync-modal__title {
    margin: 0;
    font-size: 18px;
    color: #10294b;
}
.sync-modal__body {
    padding: 18px 20px 14px;
}
.sync-modal__message {
    margin: 0 0 12px;
    color: #334155;
    font-weight: 700;
}
.sync-modal__status {
    margin-top: 10px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.45;
    min-height: 20px;
}
.sync-modal__note {
    margin-top: 10px;
    color: #64748b;
    font-size: 12px;
}
.sync-progress {
    height: 10px;
    border-radius: 999px;
    background: #e5eef8;
    overflow: hidden;
    margin-top: 14px;
}
.sync-progress__bar {
    height: 100%;
    width: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, #27ae60, #57d67d);
    transition: width .18s ease;
}
.sync-progress__bar.is-indeterminate {
    width: 42%;
    animation: sync-progress-move 1.1s ease-in-out infinite;
}
@keyframes sync-progress-move {
    0% { transform: translateX(-20%); }
    50% { transform: translateX(110%); }
    100% { transform: translateX(-20%); }
}
.sync-modal__actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 14px 20px 18px;
    border-top: 1px solid #e5e7eb;
}
.sync-btn {
    border: 0;
    border-radius: 8px;
    padding: 10px 14px;
    font-weight: 800;
    cursor: pointer;
}
.sync-btn--secondary {
    background: #e5e7eb;
    color: #111827;
}
.sync-btn--primary {
    background: #27ae60;
    color: #fff;
}
.sync-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
.legend-top { display: flex; gap: 22px; align-items: center; justify-content: flex-end; padding: 0 22px 14px; font-size: 13px; font-weight: 700; }
        .legend-item { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
        .swatch { width: 34px; height: 18px; border-radius: 4px; display: inline-block; box-shadow: inset 0 0 0 1px rgba(0,0,0,.08); }
        .gantt-scroll { overflow: auto; border-top: 1px solid #d9e2ec; }
        .gantt { display: grid; grid-template-columns: var(--left-width) var(--timeline-width); min-width: calc(var(--left-width) + var(--timeline-width)); }
        .left-head, .timeline-head { position: sticky; top: 0; z-index: 20; }
        .left-head { left: 0; z-index: 30; display: grid; grid-template-columns: 1fr 88px 88px; background: var(--header); color: white; border-right: 1px solid #91a7be; }
        .left-head > div { height: 96px; display: flex; align-items: center; justify-content: center; text-align: center; padding: 8px; font-size: 14px; font-weight: 900; border-right: 1px solid rgba(255,255,255,.35); }
        .timeline-head { background: white; }
        .week-row { display: flex; height: 52px; background: var(--header); color: white; }
        .week-cell { display: flex; align-items: center; justify-content: center; text-align: center; font-size: 16px; font-weight: 900; border-right: 1px solid rgba(255,255,255,.18); line-height: 1.35; }
        .week-cell small { display: block; color: #d8e7f7; font-size: 12px; font-weight: 800; margin-top: 2px; }
        .day-row { display: flex; height: 44px; background: #f5f7fa; color: #162233; border-bottom: 1px solid var(--grid-strong); }
        .day-cell { display: flex; align-items: center; justify-content: center; text-align: center; font-weight: 900; font-size: 15px; border-right: 1px solid var(--grid-strong); line-height: 1.15; }
        .day-cell small { font-size: 12px; color: #2d3f52; }
        .hours-row { position: relative; height: var(--hour-header-height); background: #ffdf4a; border-bottom: 2px solid #e7bd1f; }
        .hour-label { position: absolute; top: 6px; transform: translateX(-50%); font-size: 11px; font-weight: 900; color: #253142; }
        .left-row { position: sticky; left: 0; z-index: 10; display: grid; grid-template-columns: 1fr 88px 88px; min-height: var(--row-height); background: #fff; border-right: 1px solid #bdcad7; border-bottom: 1px solid var(--grid); }
        .left-row .activity { padding: 16px 16px 10px 20px; border-left: 6px solid var(--prod); border-right: 1px solid var(--grid); }
        .left-row.setup .activity { border-left-color: var(--setup); }
        .left-row.done .activity { border-left-color: var(--ok); }
        .op { font-size: 15px; font-weight: 900; margin-bottom: 7px; color: #162233; }
        .desc { font-size: 13px; line-height: 1.32; color: #1e2d3d; }
        .qty { margin-top: 7px; color: var(--muted); font-size: 12px; font-weight: 700; }
        .time-cell { display: flex; align-items: center; justify-content: center; text-align: center; padding: 8px; font-size: 13px; line-height: 1.35; border-right: 1px solid var(--grid); }
        .timeline-row { position: relative; min-height: var(--row-height); background: #fbfdff; border-bottom: 1px solid var(--grid); overflow: hidden; }
        .timeline-row::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(to right, rgba(122,151,181,.20) 1px, transparent 1px); background-size: <?= max(1, (int) round(60 * $pxPerMinute)) ?>px 100%; pointer-events: none; }
        .day-line { position: absolute; top: 0; bottom: 0; width: 1px; background: var(--grid-strong); z-index: 1; }
        .day-shade { position: absolute; top: 0; bottom: 0; background: rgba(47,120,196,.045); z-index: 0; }
        .bar { position: absolute; height: 28px; border-radius: 5px; color: #fff; font-size: 12px; font-weight: 900; display: flex; align-items: center; justify-content: center; padding: 0 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-shadow: 0 2px 6px rgba(12,32,55,.18), inset 0 0 0 1px rgba(255,255,255,.20); z-index: 4; }
        .bar.prod { top: 28px; background: linear-gradient(180deg, #3d86d5, var(--prod)); }
        .bar.setup { top: 32px; background: linear-gradient(180deg, #ff941f, var(--setup)); }
        .bar.setup-real { top: 62px; height: 23px; background: linear-gradient(180deg, #9f7aea, var(--setup-real)); }
        .left-row.setup.has-setup-real .activity { border-left-color: var(--setup-real); }
        .left-row.setup.no-setup-real .activity { border-left-color: var(--setup-missing); }
        .setup-status { margin-top: 7px; color: var(--muted); font-size: 12px; font-weight: 700; }
        .setup-status b.ok { color: var(--setup-real); }
        .setup-status b.missing { color: #b45309; }
        .bar.real { top: 62px; height: 23px; background: linear-gradient(180deg, #e9252c, var(--real)); }
        .bar.ok { top: 36px; background: linear-gradient(180deg, #2bbd59, var(--ok)); }
        .bottom { padding: 12px 22px 18px; display: flex; justify-content: space-between; gap: 20px; align-items: flex-end; border-top: 1px solid #e4ebf2; background: #fbfcfe; }
        .legend-bottom { display: flex; gap: 34px; flex-wrap: wrap; }
        .legend-card { display: grid; grid-template-columns: 62px auto; gap: 10px; align-items: center; font-size: 13px; color: #16314f; }
        .legend-card b { display: block; margin-bottom: 2px; }
        .note { font-size: 12px; line-height: 1.45; color: #26384b; }
        .stamp { background: #eef2f6; padding: 12px 16px; border-radius: 8px; color: #34475b; font-size: 12px; min-width: 230px; }
        .empty { padding: 28px; color: #526273; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .gantt-scroll { overflow: visible; }
            .page { box-shadow: none; border: 0; }
        }
    
/* ===== Cabeçalho fixo do Gantt ===== */
.gantt-shell,
.gantt-scroll,
.gantt-wrapper,
.timeline-wrapper {
    height: calc(100vh - 118px);
    overflow: auto;
    background: #fff;
}

/* Mantém o cabeçalho da tabela visível ao rolar verticalmente */
thead th,
.timeline-week-row,
.timeline-day-row,
.timeline-hour-row {
    position: sticky;
    z-index: 30;
}

/* Ajuste para cabeçalho em 3 níveis: semana / dia / hora */
.timeline-week-row,
tr.week-row th,
.week-header {
    top: 0;
    z-index: 42;
}

.timeline-day-row,
tr.day-row th,
.day-header {
    top: 52px;
    z-index: 41;
}

.timeline-hour-row,
tr.hour-row th,
.hour-header {
    top: 104px;
    z-index: 40;
}

/* Colunas fixas à esquerda */
.left-head,
.activity-head,
.col-atividade,
th:first-child {
    position: sticky;
    left: 0;
    z-index: 55;
    background: #10294b;
}

.inicio-head,
.col-inicio,
th:nth-child(2) {
    position: sticky;
    left: 320px;
    z-index: 54;
    background: #10294b;
}

.termino-head,
.col-termino,
th:nth-child(3) {
    position: sticky;
    left: 415px;
    z-index: 54;
    background: #10294b;
}

.left-col,
.activity-cell,
.cell-atividade,
td:first-child {
    position: sticky;
    left: 0;
    z-index: 25;
    background: #fff;
}

.inicio-col,
.inicio-cell,
.cell-inicio,
td:nth-child(2) {
    position: sticky;
    left: 320px;
    z-index: 24;
    background: #fff;
}

.termino-col,
.termino-cell,
.cell-termino,
td:nth-child(3) {
    position: sticky;
    left: 415px;
    z-index: 24;
    background: #fff;
}

tbody tr:nth-child(even) td:first-child,
tbody tr:nth-child(even) td:nth-child(2),
tbody tr:nth-child(even) td:nth-child(3) {
    background: #fbfdff;
}

/* Evita que barras/timeline passem visualmente por cima do cabeçalho fixo */
.timeline-cell,
.task-cell,
.bars-cell {
    z-index: 1;
}


/* Realizado é avanço percentual sobre a barra planejada, não janela min/max de apontamento. */
.bar.real {
    height: 22px;
    top: 55px;
    min-width: 28px;
}
.bar {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}


/* ===== Zoom horizontal fiel à escala de datas/horários ===== */
.gantt-scroll {
    --zoom-factor: 1;
    scroll-behavior: auto;
}

.gantt-zoom-help {
    font-size: 12px;
    color: #506278;
    margin-left: auto;
    white-space: nowrap;
}

.zoom-indicator {
    position: fixed;
    right: 18px;
    bottom: 18px;
    background: rgba(8, 35, 68, .92);
    color: #fff;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    z-index: 9999;
    opacity: 0;
    transform: translateY(8px);
    transition: opacity .14s ease, transform .14s ease;
    pointer-events: none;
}

.zoom-indicator.is-visible {
    opacity: 1;
    transform: translateY(0);
}

@media (max-width: 1200px) {
    .gantt-zoom-help { display: none; }
}


/* ===== Zoom sincronizado: timeline, dias, horários, barras e grade ===== */
.gantt {
    grid-template-columns: var(--left-width) var(--timeline-width) !important;
    min-width: calc(var(--left-width) + var(--timeline-width)) !important;
}

.timeline-head,
.timeline-row,
.hours-row {
    width: var(--timeline-width) !important;
}

.timeline-row::before {
    background-size: var(--hour-grid-width, 40px) 100% !important;
}


/* Botões de navegação/zoom */
.gantt-zoom-controls {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: 8px;
}

.zoom-btn {
    border: 1px solid #c6d2df;
    background: #fff;
    color: #082344;
    border-radius: 7px;
    min-width: 34px;
    height: 30px;
    padding: 0 10px;
    font-weight: 900;
    font-size: 15px;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 35, 60, .08);
}

.zoom-btn:hover {
    background: #eef5ff;
    border-color: #7ea6d6;
}

.zoom-btn-reset {
    min-width: 54px;
    font-size: 12px;
}


        .bar.setup, .bar.setup-real {
            min-width: 22px;
            padding: 0 6px;
        }
        .bar.setup.is-short-label,
        .bar.setup-real.is-short-label {
            font-size: 0;
        }


/* ===== Arrastar timeline com mouse ===== */
.gantt-scroll {
    cursor: grab;
    user-select: none;
}

.gantt-scroll.is-dragging {
    cursor: grabbing;
}

.gantt-scroll.is-dragging * {
    user-select: none !important;
}


/* ===== Labels inteligentes das barras =====
   Evita texto cortado em barras curtas. */
.bar {
    overflow: visible !important;
}

.bar-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    pointer-events: none;
}

.bar.is-small {
    justify-content: flex-start;
}

.bar.is-small .bar-label {
    position: absolute;
    left: calc(100% + 6px);
    top: 50%;
    transform: translateY(-50%);
    max-width: 130px;
    min-width: max-content;
    height: 20px;
    padding: 2px 7px;
    border-radius: 999px;
    color: #102033;
    background: rgba(255, 255, 255, .96);
    border: 1px solid #d8e0ea;
    box-shadow: 0 2px 8px rgba(15, 35, 60, .12);
    font-size: 11px;
    font-weight: 900;
    z-index: 9;
}

.bar.is-tiny .bar-label {
    display: none;
}

.bar.is-tiny::after {
    content: attr(data-short-label);
    position: absolute;
    left: calc(100% + 6px);
    top: 50%;
    transform: translateY(-50%);
    height: 18px;
    min-width: max-content;
    max-width: 110px;
    padding: 2px 7px;
    border-radius: 999px;
    color: #102033;
    background: rgba(255, 255, 255, .96);
    border: 1px solid #d8e0ea;
    box-shadow: 0 2px 8px rgba(15, 35, 60, .12);
    font-size: 10px;
    font-weight: 900;
    line-height: 14px;
    z-index: 10;
}

.timeline-row {
    overflow: visible !important;
}


/* ===== Setup realizado com leitura visual de desvio =====
   Verde: realizado menor/igual ao previsto
   Azul: praticamente igual
   Vermelho: realizado acima do previsto */
.bar.setup-real {
    min-width: 4px !important;
}

.bar.setup {
    min-width: 4px !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.bar.setup-real-good {
    background: linear-gradient(180deg, #22c55e, var(--setup-real-good)) !important;
}

.bar.setup-real-equal {
    background: linear-gradient(180deg, #3b82f6, var(--setup-real-equal)) !important;
}

.bar.setup-real-bad {
    background: linear-gradient(180deg, #ef4444, var(--setup-real-bad)) !important;
}

.setup-status b.ok {
    color: var(--setup-real-good) !important;
}

.setup-status b.bad {
    color: var(--setup-real-bad) !important;
}

.setup-status b.equal {
    color: var(--setup-real-equal) !important;
}


/* ===== Largura real de setup realizado =====
   A barra roxa/vermelha/verde respeita os minutos reais.
   A etiqueta pode sair para fora, mas a cor não é esticada artificialmente. */
.bar.setup-real {
    min-width: 4px !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.bar.setup-real .bar-label {
    pointer-events: none;
}

.bar.setup-real.is-small .bar-label,
.bar.setup-real.is-tiny::after {
    left: calc(100% + 8px);
}


/* Setup previsto/realizado: largura proporcional entre os dois tempos na mesma linha. */
.bar.setup,
.bar.setup-real {
    transform-origin: left center;
}


/* Separação mais clara entre programado e realizado na produção. */
.bar.prod,
.bar.ok {
    top: 22px !important;
    height: 26px !important;
}
.bar.real {
    top: 62px !important;
    height: 24px !important;
    background: linear-gradient(180deg, #ef2f36, var(--real)) !important;
}


</style>
</head>
<body>
<div class="page">
    <div class="top">
        <div class="title">
            <h1>GRÁFICO DE GANTT - SEMANAS / DIAS / HORÁRIOS</h1>
            <div class="sub">PROGRAMA <?= e((string) $selectedProgramId) ?> - <?= e($lineLabel) ?> · FINS DE SEMANA REMOVIDOS <?= render_app_build_badge() ?></div>
        </div>
        <form class="actions" method="get">
            <label>Programação
                <select name="programacao_id" onchange="this.form.submit()">
                    <?php foreach ($programacoes as $prg): ?>
                        <option value="<?= (int) $prg['prg_id'] ?>" <?= $selectedProgramId === (int) $prg['prg_id'] ? 'selected' : '' ?>>
                            Programa <?= (int) $prg['prg_id'] ?> · <?= e(normalizeLineLabel((string) ($prg['linha_excel_dominante'] ?? $prg['lin_codigo'] ?? ''))) ?> · <?= e(!empty($prg['inicio_base_cronograma']) ? date('d/m/Y H:i', strtotime((string) $prg['inicio_base_cronograma'])) : 'S/data') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Hora início <input type="number" name="hora_inicio" min="0" max="23" value="<?= (int) $visibleStartHour ?>" style="width:70px"></label>
            <label>Hora fim <input type="number" name="hora_fim" min="1" max="24" value="<?= (int) $visibleEndHour ?>" style="width:70px"></label>
            <button class="btn" type="submit">Aplicar</button>
            <a class="btn" href="gantt.php?programacao_id=<?= (int) $selectedProgramId ?>">Gantt atual</a>
            <div class="header-nav-buttons">
                <button type="button" class="btn btn-sync" id="syncCodiBtn">Sincronizar CODI</button>
                <a class="btn btn-analitico" href="relgantt.php<?= $selectedProgramId > 0 ? '?programacao_id=' . (int) $selectedProgramId : '' ?>">Analítico</a>
                <a class="btn btn-voltar" href="index.php">Voltar ao Sistema</a>
            </div>
        </form>
    </div>

    <div class="legend-top">
        <span class="legend-item"><i class="swatch" style="background:var(--prod)"></i>Produção</span>
        <span class="legend-item"><i class="swatch" style="background:var(--setup)"></i>Setup Previsto</span>
        <span class="legend-item"><i class="swatch" style="background:var(--setup-real-good)"></i>Setup Realizado ≤ Previsto</span>
        <span class="legend-item"><i class="swatch" style="background:var(--setup-real-bad)"></i>Setup Realizado > Previsto</span>
        <span class="legend-item"><i class="swatch" style="background:var(--real)"></i>Realizado</span>
    </div>

    <div id="codiSyncOverlay" class="sync-modal-overlay" aria-hidden="true">
        <div class="sync-modal" role="dialog" aria-modal="true" aria-labelledby="codiSyncTitle">
            <div class="sync-modal__head">
                <h2 id="codiSyncTitle" class="sync-modal__title">Sincronização CODI</h2>
            </div>
            <div class="sync-modal__body">
                <p id="codiSyncMessage" class="sync-modal__message">Sincronizar os dados do CODI agora?</p>
                <div class="sync-progress" aria-hidden="true">
                    <div id="codiSyncProgressBar" class="sync-progress__bar"></div>
                </div>
                <div id="codiSyncStatus" class="sync-modal__status"></div>
                <div class="sync-modal__note">A ação usa o endpoint existente api/sync_codi.php.</div>
            </div>
            <div class="sync-modal__actions">
                <button type="button" id="codiSyncNoBtn" class="sync-btn sync-btn--secondary">Não</button>
                <button type="button" id="codiSyncYesBtn" class="sync-btn sync-btn--primary">Sim</button>
            </div>
        </div>
    </div>

    <?php if (empty($rows) || empty($days)): ?>
        <div class="empty">Nenhum item encontrado para a programação selecionada.</div>
    <?php else: ?>
        <div id="ganttScroll" class="gantt-scroll" gantt-shell>
            <div class="gantt">
                <div class="left-head">
                    <div>ATIVIDADE / OP</div><div>INÍCIO</div><div>TÉRMINO</div>
                </div>
                <div class="timeline-head">
                    <div class="week-row">
                        <?php foreach ($weekGroups as $week): ?>
                            <div class="week-cell" style="width: <?= (100 * (int) $week['count'] / max(1, count($days))) ?>%">
                                <div><?= e($week['label']) ?><small><?= e(brDate($week['start_date'])) ?> - <?= e(brDate($week['end_date'])) ?></small></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="day-row">
                        <?php foreach ($days as $day): ?>
                            <div class="day-cell" style="width: <?= (100 / max(1, count($days))) ?>%"><?= dayLabel($day) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="hours-row">
                        <?php foreach ($days as $idx => $day): ?>
                            <?php for ($h = $visibleStartHour; $h <= $visibleEndHour; $h += 4): ?>
                                <?php $left = (($idx * $minutesPerDay) + (($h - $visibleStartHour) * 60)) * $pxPerMinute; ?>
                                <span class="hour-label" style="left: <?= (int) round($left) ?>px"><?= str_pad((string) $h, 2, '0', STR_PAD_LEFT) ?>h</span>
                            <?php endfor; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php foreach ($rows as $idx => $row): ?>
                    <?php
                        $setupPrevMin = (float) ($row['setup_prev_min'] ?? 0);
                        $setupRealMin = (float) ($row['setup_real_min'] ?? 0);
                        $setupRealEvents = (int) ($row['setup_real_events'] ?? 0);
                        $hasSetupReal = $row['is_setup'] && $setupRealEvents > 0 && $setupRealMin > 0.0001;
                        $setupDiffMin = $setupRealMin - $setupPrevMin;
                        $setupRealClass = 'setup-real';
                        $setupStatusClass = 'ok';
                        $setupStatusText = 'abaixo/ok';
                        if ($hasSetupReal) {
                            if (abs($setupDiffMin) <= 1.0) {
                                $setupRealClass = 'setup-real setup-real-equal';
                                $setupStatusClass = 'equal';
                                $setupStatusText = 'no previsto';
                            } elseif ($setupDiffMin > 1.0) {
                                $setupRealClass = 'setup-real setup-real-bad';
                                $setupStatusClass = 'bad';
                                $setupStatusText = '+' . number_format($setupDiffMin, 0, ',', '.') . ' min';
                            } else {
                                $setupRealClass = 'setup-real setup-real-good';
                                $setupStatusClass = 'ok';
                                $setupStatusText = number_format($setupDiffMin, 0, ',', '.') . ' min';
                            }
                        }

                        // Para SETUP, não usar sch_fim_producao como fim visual quando ele vem como envelope
                        // longo de calendário. A barra do setup deve representar a duração real prevista:
                        // início do setup + sch_duracao_minutos.
                        $visualStart = $row['start'];
                        $visualEnd = $row['end'];
                        if ($row['is_setup'] && $setupPrevMin > 0 && strtotime($row['start']) !== false) {
                            $visualEnd = date('Y-m-d H:i:s', strtotime($row['start']) + ((int) round($setupPrevMin) * 60));

                            // Se a OP vinculada já tem início real de PRODUÇÃO, não deixar o setup previsto "passar"
                            // do início de produção. Isso evita a inversão visual (setup terminando depois da produção iniciar).
                            $linkedOp = trim((string) ($row['setup_next_op'] ?? ''));
                            if ($linkedOp !== '' && $linkedOp !== 'S/OP' && isset($realizadoByOp[$linkedOp]['inicio'])) {
                                $linkedProdStart = (string) ($realizadoByOp[$linkedOp]['inicio'] ?? '');
                                $setupStartTs = strtotime((string) $row['start']);
                                $prodStartTs = $linkedProdStart !== '' ? strtotime($linkedProdStart) : false;
                                $visualEndTs = strtotime($visualEnd);
                                if ($setupStartTs !== false && $prodStartTs !== false && $visualEndTs !== false) {
                                    // Só "encurta" o setup quando o início de produção vem DEPOIS do início do setup.
                                    // Se o CODI devolver timestamps iguais (ou produção antes), não zera a duração.
                                    if ($prodStartTs > $setupStartTs && $visualEndTs > $prodStartTs) {
                                        $visualEnd = $linkedProdStart;
                                    }
                                }
                            }
                        }

                        $plannedSegments = buildVisibleSegments($visualStart, $visualEnd, $dayOffsets, $visibleStartHour, $visibleEndHour);
                        $pct = $row['qtd_prev'] > 0 ? ($row['qtd_real'] / $row['qtd_prev']) * 100 : 0;

                        // IMPORTANTE:
                        // A barra vermelha representa avanço realizado sobre o previsto.
                        // Ela NÃO deve usar min/max de apontamento como escala de tempo,
                        // porque isso passa a impressão errada de baixa produção quando a quantidade já chegou perto de 100%.
                        $realSegments = [];
                        if (!$row['is_setup'] && $row['qtd_real'] > 0 && !empty($plannedSegments)) {
                            // Corrige a proporção visual do realizado considerando a largura TOTAL
                            // da barra programada, e não cada segmento isoladamente.
                            // Isso mantém o vermelho fiel ao percentual mesmo com fins de semana removidos
                            // ou com timeline comprimida em vários dias.
                            $realRatio = max(0, min(1, $pct / 100));

                            $totalPlannedWidth = 0.0;
                            foreach ($plannedSegments as $plannedSeg) {
                                $totalPlannedWidth += (float) ($plannedSeg['width'] ?? 0);
                            }

                            $remainingRealWidth = $totalPlannedWidth * $realRatio;

                            foreach ($plannedSegments as $plannedSeg) {
                                if ($remainingRealWidth <= 0) {
                                    break;
                                }

                                $plannedWidth = (float) ($plannedSeg['width'] ?? 0);
                                if ($plannedWidth <= 0) {
                                    continue;
                                }

                                $realWidth = min($plannedWidth, $remainingRealWidth);
                                if ($realWidth > 0) {
                                    $realSegments[] = [
                                        'left' => $plannedSeg['left'],
                                        'width' => $realWidth,
                                    ];
                                }

                                $remainingRealWidth -= $realWidth;
                            }
                        }

                        $setupRealSegments = [];
                        if ($hasSetupReal) {
                            $setupRealVisualStart = (!empty($row['setup_real_start']) && strtotime((string) $row['setup_real_start']) !== false)
                                ? (string) $row['setup_real_start']
                                : (string) $row['start'];
                            if (strtotime($setupRealVisualStart) !== false) {
                                $setupRealVisualEnd = date('Y-m-d H:i:s', strtotime($setupRealVisualStart) + ((int) round($setupRealMin) * 60));
                                $setupRealSegments = buildVisibleSegments($setupRealVisualStart, $setupRealVisualEnd, $dayOffsets, $visibleStartHour, $visibleEndHour);
                            }
                        }

                        $setupComparePxPerMinute = null;

                        $leftClass = $row['is_setup'] ? ('setup ' . ($hasSetupReal ? 'has-setup-real' : 'no-setup-real')) : ($pct >= 100 ? 'done' : '');
                        // Produção sempre deve mostrar previsto x realizado.
                        // Mesmo quando passou de 100%, a barra prevista continua azul "Programado";
                        // o status de concluído fica implícito pelo percentual/quantidade realizado.
                        $barClass = $row['is_setup'] ? 'setup' : 'prod';
                        $barLabel = $row['is_setup'] ? 'Setup Previsto' : 'Programado';
                    ?>
                    <div class="left-row <?= e($leftClass) ?>">
                        <div class="activity">
                            <div class="op"><?= $row['is_setup'] ? 'SETUP' : e($row['op']) ?></div>
                            <div class="desc"><?= e($row['is_setup'] ? 'Preparação / troca de linha' : (($row['sku'] ? $row['sku'] . ' - ' : '') . $row['descricao'])) ?></div>
                            <?php if ($row['is_setup']): ?>
                                <div class="setup-status">
                                    Previsto: <?= number_format($setupPrevMin, 0, ',', '.') ?> min ·
                                    Realizado: <?= $hasSetupReal ? number_format($setupRealMin, 0, ',', '.') . ' min' : 'S/evento' ?> ·
                                    <b class="<?= $hasSetupReal ? e($setupStatusClass) : 'missing' ?>"><?= $hasSetupReal ? (e($setupStatusText) . ' · ' . (int) $setupRealEvents . ' evento(s)') : 'sem evento alvo' ?></b>
                                </div>
                                <?php if (!empty($row['setup_next_op']) && $row['setup_next_op'] !== 'S/OP'): ?>
                                    <div class="setup-status">OP vinculada: <?= e((string) $row['setup_next_op']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="qty">Linha: Previsto <?= number_format($row['qtd_prev'], 0, ',', '.') ?> · Realizado <?= number_format($row['qtd_real'], 0, ',', '.') ?><?= $row['qtd_real'] > 0 ? ' (' . number_format($pct, 0, ',', '.') . '%)' : '' ?></div>
                                <?php if (!empty($row['qtd_prev_op']) && abs((float) $row['qtd_prev_op'] - (float) $row['qtd_prev']) > 0.0001): ?>
                                    <div class="qty">OP total: Previsto <?= number_format((float) $row['qtd_prev_op'], 0, ',', '.') ?> · Realizado <?= number_format((float) $row['qtd_real_op'], 0, ',', '.') ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php [$startLabel, $endLabel] = brDateTimeSmart((string) $row['start'], (string) ($row['is_setup'] ? $visualEnd : $row['end'])); ?>
                        <div class="time-cell"><?= e($startLabel) ?></div>
                        <div class="time-cell"><?= e($endLabel) ?></div>
                    </div>
                    <div class="timeline-row">
                        <?php foreach ($days as $dIdx => $day): ?>
                            <?php $x = $dIdx * $minutesPerDay * $pxPerMinute; ?>
                            <?php if ($dIdx % 2 === 0): ?><span class="day-shade" style="left: <?= (int) round($x) ?>px; width: <?= (int) round($minutesPerDay * $pxPerMinute) ?>px"></span><?php endif; ?>
                            <span class="day-line" style="left: <?= (int) round($x) ?>px"></span>
                        <?php endforeach; ?>
                        <span class="day-line" style="left: <?= (int) round($timelineWidth) ?>px"></span>

                        <?php foreach ($plannedSegments as $segIdx => $seg): ?>
                            <div class="bar <?= e($barClass) ?>" data-short-label="<?= e($barLabel) ?>" style="left: <?= (int) round($seg['left'] * $pxPerMinute) ?>px; width: <?= $row['is_setup'] ? max(1, (int) round($seg['width'] * $pxPerMinute)) : max(18, (int) round($seg['width'] * $pxPerMinute)) ?>px" title="<?= e($barLabel . ' · ' . brDateTime($visualStart) . ' - ' . brDateTime($visualEnd) . ($row['is_setup'] ? ' · ' . number_format($setupPrevMin, 0, ',', '.') . ' min' : (!empty($row['real_start']) ? ' · janela CODI/relgantt' : ' · janela planejada'))) ?>"><span class="bar-label"><?= e($barLabel) ?></span></div>
                        <?php endforeach; ?>

                        <?php foreach ($realSegments as $seg): ?>
                            <div class="bar real" data-short-label="Realizado" style="left: <?= (int) round($seg['left'] * $pxPerMinute) ?>px; width: <?= max(18, (int) round($seg['width'] * $pxPerMinute)) ?>px" title="<?= e('Realizado · ' . number_format($row['qtd_real'], 0, ',', '.') . ' de ' . number_format($row['qtd_prev'], 0, ',', '.') . ' (' . number_format($pct, 0, ',', '.') . '%)') ?>"><span class="bar-label">Realizado</span></div>
                        <?php endforeach; ?>

                        <?php foreach ($setupRealSegments as $seg): ?>
                            <div class="bar <?= e($setupRealClass) ?>" data-short-label="Setup Realizado" style="left: <?= (int) round($seg['left'] * $pxPerMinute) ?>px; width: <?= max(1, (int) round($seg['width'] * $pxPerMinute)) ?>px" title="<?= e('Setup realizado CODI · ' . number_format($setupRealMin, 0, ',', '.') . ' min · previsto ' . number_format($setupPrevMin, 0, ',', '.') . ' min · desvio ' . number_format($setupDiffMin, 0, ',', '.') . ' min · ' . (int) $setupRealEvents . ' evento(s) · OP ' . (string) ($row['setup_next_op'] ?? 'S/OP')) ?>"><span class="bar-label">Setup Realizado</span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="bottom">
        <div>
            <div class="legend-bottom">
                <div class="legend-card"><span class="swatch" style="background:var(--prod); width:62px; height:34px"></span><span><b>Produção Programada</b>Período previsto da OP</span></div>
                <div class="legend-card"><span class="swatch" style="background:var(--setup); width:62px; height:34px"></span><span><b>Setup Previsto</b>sch_linhas.sch_duracao_minutos</span></div>
                <div class="legend-card"><span class="swatch" style="background:var(--setup-real-good); width:62px; height:34px"></span><span><b>Setup Realizado ≤ Previsto</b>verde quando menor ou dentro do previsto</span></div>
                <div class="legend-card"><span class="swatch" style="background:var(--setup-real-bad); width:62px; height:34px"></span><span><b>Setup Realizado > Previsto</b>vermelho quando acima do previsto</span></div>
                <div class="legend-card"><span class="swatch" style="background:var(--real); width:62px; height:34px"></span><span><b>Produção Realizada</b>Período executado</span></div>
            </div>
            <div class="note" style="margin-top:14px"><b>OBSERVAÇÕES:</b><br>• Timeline exibida de <?= (int) $visibleStartHour ?>h até <?= (int) $visibleEndHour ?>h.<br>• Fins de semana removidos automaticamente da escala.<br>• Previsto x realizado usa a mesma origem do relgantt.php; datas das OPs e setups usam eventos CODI quando disponíveis.<br>• Sábados e domingos removidos automaticamente da escala.</div>
        </div>
        <div class="stamp">GERADO EM: <?= e(date('d/m/Y H:i')) ?><br>FONTE: Sistema Controle PCP</div>
    </div>
</div>


<div id="zoomIndicator" class="zoom-indicator">Zoom 100%</div>
<script>
(function() {
    const scrollEl = document.getElementById('ganttScroll');
    const ganttEl = scrollEl ? scrollEl.querySelector('.gantt') : null;
    const indicator = document.getElementById('zoomIndicator');
    if (!scrollEl || !ganttEl) return;

    const root = document.documentElement;
    const computedRoot = getComputedStyle(root);
    const leftWidth = parseFloat(computedRoot.getPropertyValue('--left-width')) || 500;
    const baseTimelineWidth = parseFloat(computedRoot.getPropertyValue('--timeline-width')) || 2400;

    const zoomLevels = [0.55, 0.7, 0.85, 1, 1.2, 1.45, 1.75, 2.1, 2.55, 3.1, 3.75, 4.5];
    let zoomIndex = 6;
    let zoomTimer = null;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function pxNumber(value) {
        const n = parseFloat(String(value || '').replace('px', ''));
        return Number.isFinite(n) ? n : null;
    }

    function rememberBaseGeometry() {
        const selectors = [
            '.hour-label',
            '.day-line',
            '.day-shade',
            '.bar'
        ];

        scrollEl.querySelectorAll(selectors.join(',')).forEach(function(el) {
            if (!el.dataset.baseLeft) {
                const left = pxNumber(el.style.left);
                if (left !== null) el.dataset.baseLeft = String(left);
            }
            if (!el.dataset.baseWidth) {
                const width = pxNumber(el.style.width);
                if (width !== null) el.dataset.baseWidth = String(width);
            }
        });
    }

    function showZoom() {
        if (!indicator) return;
        indicator.textContent = 'Zoom ' + Math.round(zoomLevels[zoomIndex] * 100) + '%';
        indicator.classList.add('is-visible');
        clearTimeout(zoomTimer);
        zoomTimer = setTimeout(() => indicator.classList.remove('is-visible'), 900);
    }

    function scaleGeometry(zoom) {
        const newTimelineWidth = baseTimelineWidth * zoom;
        root.style.setProperty('--timeline-width', newTimelineWidth + 'px');

        // Mantém grade de hora, labels, divisões de dia e barras usando a mesma escala.
        root.style.setProperty('--hour-grid-width', (60 * <?= json_encode($pxPerMinute) ?> * zoom) + 'px');

        scrollEl.querySelectorAll('.hour-label, .day-line, .day-shade, .bar').forEach(function(el) {
            if (el.dataset.baseLeft) {
                el.style.left = (parseFloat(el.dataset.baseLeft) * zoom) + 'px';
            }
            if (el.dataset.baseWidth) {
                let minWidth = 0;
                if (el.classList.contains('bar')) {
                    if (el.classList.contains('setup-real')) {
                        minWidth = 4;
                    } else if (el.classList.contains('setup')) {
                        minWidth = 12;
                    } else {
                        minWidth = 18;
                    }
                }
                el.style.width = Math.max(minWidth, parseFloat(el.dataset.baseWidth) * zoom) + 'px';
            }
        });
    }

    function getAnchorRatio(anchorClientX) {
        const rect = scrollEl.getBoundingClientRect();
        const anchorX = typeof anchorClientX === 'number' ? anchorClientX - rect.left : rect.width / 2;

        // Desconsidera as colunas fixas; calcula a posição real dentro da timeline.
        const visibleTimelineX = Math.max(0, anchorX - leftWidth);
        const timelineScrollX = Math.max(0, scrollEl.scrollLeft - leftWidth);
        const currentTimelineWidth = baseTimelineWidth * zoomLevels[zoomIndex];
        const timelineX = timelineScrollX + visibleTimelineX;

        return {
            ratio: timelineX / Math.max(1, currentTimelineWidth),
            visibleTimelineX: visibleTimelineX
        };
    }

    function applyZoom(nextIndex, anchorClientX) {
        nextIndex = clamp(nextIndex, 0, zoomLevels.length - 1);
        if (nextIndex === zoomIndex) return;

        const anchor = getAnchorRatio(anchorClientX);

        zoomIndex = nextIndex;
        const newZoom = zoomLevels[zoomIndex];
        scaleGeometry(newZoom);

        const newTimelineWidth = baseTimelineWidth * newZoom;
        const newTimelineX = anchor.ratio * newTimelineWidth;
        scrollEl.scrollLeft = Math.max(0, leftWidth + newTimelineX - anchor.visibleTimelineX);

        showZoom();
    }

    rememberBaseGeometry();
    scaleGeometry(zoomLevels[zoomIndex]);
    showZoom();

    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomResetBtn = document.getElementById('zoomResetBtn');

    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function() {
            applyZoom(zoomIndex - 1, null);
        });
    }

    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function() {
            applyZoom(zoomIndex + 1, null);
        });
    }

    if (zoomResetBtn) {
        zoomResetBtn.addEventListener('click', function() {
            applyZoom(6, null);
        });
    }

    scrollEl.addEventListener('wheel', function(event) {
        if (event.ctrlKey || event.metaKey) {
            event.preventDefault();
            const direction = event.deltaY > 0 ? -1 : 1;
            applyZoom(zoomIndex + direction, event.clientX);
            return;
        }

        if (event.shiftKey && Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
            event.preventDefault();
            scrollEl.scrollLeft += event.deltaY;
        }
    }, { passive: false });

    scrollEl.addEventListener('mouseenter', () => scrollEl.dataset.hover = '1');
    scrollEl.addEventListener('mouseleave', () => scrollEl.dataset.hover = '0');

    document.addEventListener('keydown', function(event) {
        if (scrollEl.dataset.hover !== '1') return;
        if ((event.ctrlKey || event.metaKey) && (event.key === '+' || event.key === '=')) {
            event.preventDefault();
            applyZoom(zoomIndex + 1, null);
        }
        if ((event.ctrlKey || event.metaKey) && event.key === '-') {
            event.preventDefault();
            applyZoom(zoomIndex - 1, null);
        }
        if ((event.ctrlKey || event.metaKey) && event.key === '0') {
            event.preventDefault();
            applyZoom(6, null);
        }
    });
})();

(function() {
    const syncBtn = document.getElementById('syncCodiBtn');
    const overlay = document.getElementById('codiSyncOverlay');
    const modalTitle = document.getElementById('codiSyncTitle');
    const modalMessage = document.getElementById('codiSyncMessage');
    const modalStatus = document.getElementById('codiSyncStatus');
    const progressBar = document.getElementById('codiSyncProgressBar');
    const yesBtn = document.getElementById('codiSyncYesBtn');
    const noBtn = document.getElementById('codiSyncNoBtn');
    if (!syncBtn || !overlay || !modalTitle || !modalMessage || !modalStatus || !progressBar || !yesBtn || !noBtn) {
        return;
    }

    let busy = false;
    let requestController = null;
    const originalText = syncBtn.textContent;

    function openModal() {
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sync-modal-open');
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sync-modal-open');
    }

    function setConfirmState(message, status) {
        modalTitle.textContent = 'Sincronização CODI';
        modalMessage.textContent = message || 'Sincronizar os dados do CODI agora?';
        modalStatus.textContent = status || '';
        progressBar.classList.remove('is-indeterminate');
        progressBar.style.width = '0%';
        yesBtn.disabled = false;
        noBtn.disabled = false;
        yesBtn.textContent = 'Sim';
        noBtn.textContent = 'Não';
    }

    function setProgressState(message) {
        modalTitle.textContent = 'Sincronizando CODI';
        modalMessage.textContent = message || 'Sincronizando...';
        modalStatus.textContent = 'Aguarde a conclusão da sincronização.';
        progressBar.classList.add('is-indeterminate');
        progressBar.style.width = '';
        yesBtn.disabled = true;
        noBtn.disabled = true;
    }

    function setResultState(title, message, status, isError) {
        modalTitle.textContent = title || 'Sincronização CODI';
        modalMessage.textContent = message || '';
        modalStatus.textContent = status || '';
        progressBar.classList.remove('is-indeterminate');
        progressBar.style.width = '100%';
        progressBar.style.background = isError
            ? 'linear-gradient(90deg, #ef4444, #f97316)'
            : 'linear-gradient(90deg, #27ae60, #57d67d)';
        yesBtn.disabled = false;
        noBtn.disabled = false;
        yesBtn.textContent = isError ? 'Tentar novamente' : 'Recarregar';
        noBtn.textContent = 'Fechar';
    }

    async function fetchSyncStatus() {
        const response = await fetch('api/sync_codi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'status' })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data || data.success === false) {
            throw new Error((data && data.message) ? data.message : 'Não foi possível verificar o status do CODI.');
        }
        return data;
    }

    async function runSync() {
        if (busy) return;
        busy = true;
        syncBtn.disabled = true;
        syncBtn.textContent = 'Sincronizando...';
        setProgressState('Sincronizando os dados do CODI...');

        requestController = new AbortController();

        try {
            const response = await fetch('api/sync_codi.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'sync_today',
                    force: true
                }),
                signal: requestController.signal
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                throw new Error((data && data.message) ? data.message : 'Falha ao sincronizar o CODI.');
            }

            setResultState(
                'Sincronização concluída',
                'Sincronização concluída!',
                data.message || 'Sincronização finalizada com sucesso.',
                false
            );
            setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                setResultState('Sincronização cancelada', 'Sincronização cancelada.', 'A requisição foi interrompida.', true);
            } else {
                setResultState('Sincronização com erro', 'Não foi possível concluir a sincronização.', error && error.message ? error.message : 'Falha sem mensagem detalhada.', true);
            }
            syncBtn.disabled = false;
            syncBtn.textContent = originalText;
            busy = false;
        }
    }

    syncBtn.addEventListener('click', async function() {
        if (busy) return;
        syncBtn.disabled = true;
        syncBtn.textContent = 'Verificando...';
        openModal();
        setConfirmState('Sincronizar os dados do CODI agora?', 'Carregando status...');

        try {
            const status = await fetchSyncStatus();
            const lastSyncAt = status.lastSyncAt ? String(status.lastSyncAt) : '';
            const recordsToday = status.recordsToday ? String(status.recordsToday) : '';
            const statusText = status.alreadySynced
                ? ('Já sincronizado hoje' + (lastSyncAt ? ' em ' + lastSyncAt : '') + (recordsToday ? ' (' + recordsToday + ' registros)' : '') + '. Deseja sincronizar novamente?')
                : (status.isRunning ? 'Uma sincronização já está em andamento. Deseja iniciar outra mesmo assim?' : 'Sincronizar os dados do CODI agora?');
            setConfirmState('Sincronização CODI', statusText);
            syncBtn.disabled = false;
            syncBtn.textContent = originalText;
        } catch (error) {
            setConfirmState('Sincronização CODI', 'Não foi possível verificar o status agora. Deseja sincronizar mesmo assim?');
            modalStatus.textContent = (error && error.message) ? error.message : '';
            syncBtn.disabled = false;
            syncBtn.textContent = originalText;
        }
    });

    yesBtn.addEventListener('click', function() {
        if (busy) {
            return;
        }
        runSync();
    });

    noBtn.addEventListener('click', function() {
        if (busy) {
            if (requestController) {
                requestController.abort();
            }
            return;
        }
        closeModal();
        syncBtn.disabled = false;
        syncBtn.textContent = originalText;
    });

    overlay.addEventListener('click', function(event) {
        if (event.target === overlay && !busy) {
            closeModal();
            syncBtn.disabled = false;
            syncBtn.textContent = originalText;
        }
    });
})();
</script>



<script>
(function() {
    const scrollEl = document.getElementById('ganttScroll');
    if (!scrollEl) return;

    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let startScrollLeft = 0;
    let startScrollTop = 0;
    let moved = false;

    function isInteractiveTarget(target) {
        return !!(target && target.closest && target.closest('button, a, input, select, textarea, label, .sync-modal-overlay'));
    }

    scrollEl.addEventListener('mousedown', function(event) {
        if (event.button !== 0 || isInteractiveTarget(event.target)) return;

        isDragging = true;
        moved = false;
        startX = event.clientX;
        startY = event.clientY;
        startScrollLeft = scrollEl.scrollLeft;
        startScrollTop = scrollEl.scrollTop;
        scrollEl.classList.add('is-dragging');
        event.preventDefault();
    });

    window.addEventListener('mousemove', function(event) {
        if (!isDragging) return;

        const dx = event.clientX - startX;
        const dy = event.clientY - startY;

        if (Math.abs(dx) > 2 || Math.abs(dy) > 2) {
            moved = true;
        }

        scrollEl.scrollLeft = startScrollLeft - dx;
        scrollEl.scrollTop = startScrollTop - dy;
        event.preventDefault();
    }, { passive: false });

    window.addEventListener('mouseup', function() {
        if (!isDragging) return;

        isDragging = false;
        scrollEl.classList.remove('is-dragging');

        // Evita abrir links/acões acidentalmente logo após arrastar.
        if (moved) {
            const blockClick = function(event) {
                event.preventDefault();
                event.stopPropagation();
                window.removeEventListener('click', blockClick, true);
            };
            window.addEventListener('click', blockClick, true);
            setTimeout(() => window.removeEventListener('click', blockClick, true), 0);
        }
    });

    scrollEl.addEventListener('mouseleave', function() {
        if (!isDragging) return;
        scrollEl.classList.remove('is-dragging');
    });
})();
</script>


<script>
(function() {
    function updateBarLabels() {
        document.querySelectorAll('.bar').forEach(function(bar) {
            const width = bar.getBoundingClientRect().width;
            bar.classList.toggle('is-small', width > 0 && width < 88);
            bar.classList.toggle('is-tiny', width > 0 && width < 28);
        });
    }

    window.addEventListener('load', updateBarLabels);
    window.addEventListener('resize', updateBarLabels);

    // Recalcula também depois dos botões/scroll de zoom mexerem na escala.
    document.addEventListener('click', function(event) {
        if (event.target && event.target.closest && event.target.closest('#zoomInBtn, #zoomOutBtn, #zoomResetBtn')) {
            setTimeout(updateBarLabels, 60);
            setTimeout(updateBarLabels, 180);
        }
    });

    const ganttScroll = document.getElementById('ganttScroll');
    if (ganttScroll) {
        ganttScroll.addEventListener('wheel', function() {
            setTimeout(updateBarLabels, 80);
        }, { passive: true });
    }

    const observerTarget = document.querySelector('.gantt');
    if (observerTarget && 'MutationObserver' in window) {
        const observer = new MutationObserver(function() {
            updateBarLabels();
        });
        observer.observe(observerTarget, { attributes: true, subtree: true, attributeFilter: ['style', 'class'] });
    }
})();
</script>

</body>
</html>
