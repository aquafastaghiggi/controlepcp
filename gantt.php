<?php
// encoding: UTF-8
/**
 * Gráfico de Gantt PCP - Versão Otimizada por Manus
 * Foco em: Clareza de dados, Timeline precisa, Scroll nativo e Unificação Planejado/Realizado.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Repository\ProgramacaoRepository;
use App\Database\Connection;

Auth::startSession();
$repo = new ProgramacaoRepository();
$pdo = Connection::get();

$pcpPalette = [
    'prod' => '#3498db',
    'setup' => '#e67e22',
    'realizado' => '#2ecc71',
    'realizado_neutral' => '#95a5a6',
    'status_ok' => '#27ae60',
    'status_warn' => '#f1c40f',
    'status_bad' => '#e74c3c',
    'status_none' => '#bdc3c7',
    'neutral_light' => '#ecf0f1',
    'border' => '#dcdde1'
];

$programacoes = $repo->getAllProgramacoes(100, 0);

$ganttNormalizeLineLabel = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') return 'S/Linha';
    if (preg_match('/^(?:linha|ln)\s*0*(\d+)$/iu', $raw, $match) === 1) {
        return 'Linha ' . str_pad((string) (int) $match[1], 2, '0', STR_PAD_LEFT);
    }
    if (preg_match('/^0*(\d+)$/u', $raw, $match) === 1) {
        return 'Linha ' . str_pad((string) (int) $match[1], 2, '0', STR_PAD_LEFT);
    }
    return $raw;
};

$selectedProgramId = (int) ($_GET['programacao_id'] ?? $_GET['id'] ?? 0);
if ($selectedProgramId <= 0 && !empty($programacoes)) {
    $selectedProgramId = (int) $programacoes[0]['prg_id'];
}

$schedule = [];
$programacaoInfo = null;
if ($selectedProgramId > 0) {
    $programacaoInfo = $repo->getProgramacaoById($selectedProgramId);
    // Usar o repositório para garantir a veracidade dos dados (apenas última execução)
    $schedule = $repo->getProgramacaoSchedule($selectedProgramId);
}

// Mapeamento de OPs
$opBuckets = [];
if (!empty($schedule)) {
    $stmtOp = $pdo->prepare("SELECT prg_programa_id, prg_sku, prg_quantidade, prg_itens_op FROM prg_itens WHERE prg_programa_id = ?");
    $stmtOp->execute([$selectedProgramId]);
    foreach ($stmtOp->fetchAll(PDO::FETCH_ASSOC) as $opRow) {
        $bucketKey = $opRow['prg_sku'];
        $opBuckets[$bucketKey][] = [
            'op' => (string)($opRow['prg_itens_op'] ?? 'S/OP'),
            'quantidade' => (float)($opRow['prg_quantidade'] ?? 0),
            'used' => false,
        ];
    }
}

$assignedOps = [];
foreach ($schedule as $schRow) {
    $schId = (int)$schRow['sch_id'];
    $isSetup = strtolower(trim($schRow['sch_tipo'] ?? '')) === 'setup';
    if ($isSetup) {
        $assignedOps[$schId] = 'SETUP';
        continue;
    }
    $sku = $schRow['sch_sku'];
    if (isset($opBuckets[$sku])) {
        foreach ($opBuckets[$sku] as &$item) {
            if (!$item['used']) {
                $assignedOps[$schId] = $item['op'];
                $item['used'] = true;
                break;
            }
        }
    }
    if (!isset($assignedOps[$schId])) $assignedOps[$schId] = 'S/OP';
}

// Buscar Realizado
$realizadoMap = [];
$realTable = 'realizado_2026_excel';
try {
    $realCols = $pdo->query("SHOW COLUMNS FROM `{$realTable}`")->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasReal = !empty($realCols);
} catch (Throwable $e) { $hasReal = false; }

if ($hasReal && !empty($assignedOps)) {
    $ops = array_unique(array_values($assignedOps));
    $ops = array_filter($ops, fn($o) => $o !== 'SETUP' && $o !== 'S/OP');
    if (!empty($ops)) {
        $placeholders = implode(',', array_fill(0, count($ops), '?'));
        $stmtReal = $pdo->prepare("SELECT ordem_op, SUM(quantidade) as total, MIN(data_evento) as inicio, MAX(data_evento) as fim FROM `{$realTable}` WHERE ordem_op IN ($placeholders) GROUP BY ordem_op");
        $stmtReal->execute(array_values($ops));
        foreach ($stmtReal->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $realizadoMap[$r['ordem_op']] = $r;
        }
    }
}

$tasks = [];
foreach ($schedule as $row) {
    $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
    $op = $assignedOps[(int)$row['sch_id']] ?? 'S/OP';
    $real = $realizadoMap[$op] ?? ['total' => 0, 'inicio' => null, 'fim' => null];
    
    $prevQty = (float)$row['sch_quantidade'];
    $realQty = (float)$real['total'];
    $pct = $prevQty > 0 ? ($realQty / $prevQty) * 100 : 0;

    $tasks[] = [
        'id' => (int)$row['sch_id'],
        'text' => $isSetup ? "SETUP" : "OP $op - " . ($row['sch_descricao'] ?: '-'),
        'start_date' => date('d-m-Y H:i', strtotime($row['sch_inicio_producao'])),
        'end_date' => date('d-m-Y H:i', strtotime($row['sch_fim_producao'])),
        'type' => $isSetup ? 'setup' : 'task',
        'color' => $isSetup ? $pcpPalette['setup'] : $pcpPalette['prod'],
        'op' => $op,
        'sku' => $row['sch_sku'],
        'qtd_prev' => $prevQty,
        'qtd_real' => $realQty,
        'pct' => $pct,
        'real_start' => $real['inicio'],
        'real_end' => $real['fim']
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gantt PCP Profissional</title>
    <link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/9.0/dhtmlxgantt.css">
    <style>
        :root {
            --pcp-blue: #3498db;
            --pcp-orange: #e67e22;
            --pcp-green: #2ecc71;
            --pcp-bg: #f8f9fa;
        }
        body, html { height: 100%; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow: hidden; background: var(--pcp-bg); }
        .main-container { display: flex; flex-direction: column; height: 100vh; padding: 10px; box-sizing: border-box; }
        .header { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        #gantt_here { flex: 1; border-radius: 8px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        /* Customização Gantt */
        .gantt_task_line { border: none; border-radius: 4px; }
        .gantt_task_content { font-size: 11px; font-weight: 600; color: white; text-shadow: 0 1px 1px rgba(0,0,0,0.2); }
        
        /* Sub-barra de realizado */
        .realized-bar {
            position: absolute;
            bottom: 2px;
            left: 0;
            height: 4px;
            background: rgba(255,255,255,0.4);
            border-radius: 2px;
            width: 100%;
        }
        .realized-fill {
            height: 100%;
            background: #fff;
            border-radius: 2px;
        }
        
        /* Grid Styles */
        .pcp-grid-op { font-weight: bold; color: #2c3e50; }
        .pcp-grid-desc { font-size: 10px; color: #7f8c8d; }
        .pcp-badge { padding: 2px 6px; border-radius: 10px; font-size: 10px; color: white; font-weight: bold; }
        
        /* Scrollbars */
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 5px; }
        ::-webkit-scrollbar-thumb:hover { background: #999; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header">
            <div>
                <h2 style="margin:0; color:#2c3e50;">Monitoramento PCP - Gantt Senior</h2>
                <form method="GET" style="margin-top:10px;">
                    <select name="programacao_id" onchange="this.form.submit()" style="padding:5px; border-radius:4px; border:1px solid #ccc; width:400px;">
                        <?php foreach ($programacoes as $prg): ?>
                            <option value="<?= $prg['prg_id'] ?>" <?= $selectedProgramId === (int)$prg['prg_id'] ? 'selected' : '' ?>>
                                <?= $ganttNormalizeLineLabel($prg['linha_excel_dominante'] ?: $prg['lin_codigo']) ?> | Criado em: <?= date('d/m/Y H:i', strtotime($prg['programacao_criada_em'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div style="text-align:right;">
                <a href="index.php" style="text-decoration:none; color:#3498db; font-weight:bold;">← Voltar ao Sistema</a>
            </div>
        </div>
        <div id="gantt_here"></div>
    </div>

    <script src="https://cdn.dhtmlx.com/gantt/9.0/dhtmlxgantt.js"></script>
    <script>
        gantt.i18n.setLocale("pt");
        gantt.config.date_format = "%d-%m-%Y %H:%i";
        gantt.config.readonly = true;
        
        // CORREÇÃO DE SCROLL: Usar layout nativo e desativar hacks de wheel
        gantt.config.layout = {
            css: "gantt_container",
            rows: [
                {
                    cols: [
                        {view: "grid", id: "grid", scrollY: "scrollVer", width: 350},
                        {resizer: true, width: 1},
                        {view: "timeline", id: "timeline", scrollX: "scrollHor", scrollY: "scrollVer"},
                        {view: "scrollbar", id: "scrollVer"}
                    ]
                },
                {view: "scrollbar", id: "scrollHor"}
            ]
        };

        // TIMELINE: Escalas claras para PCP
        gantt.config.scales = [
            {unit: "day", step: 1, format: "%d %M (%D)"},
            {unit: "hour", step: 6, format: "%H:00"}
        ];
        gantt.config.scale_height = 50;

        // COLUNAS DO GRID
        gantt.config.columns = [
            {name: "text", label: "OP / Descrição", width: "*", template: function(task){
                return "<div class='pcp-grid-op'>" + task.op + "</div><div class='pcp-grid-desc'>" + task.text.split(' - ')[1] + "</div>";
            }},
            {name: "qtd", label: "Prev/Real", width: 100, align: "center", template: function(task){
                if(task.type == 'setup') return "-";
                var color = task.pct >= 100 ? "#27ae60" : (task.pct >= 80 ? "#f1c40f" : "#e74c3c");
                return "<span class='pcp-badge' style='background:"+color+"'>" + Math.round(task.qtd_real) + "/" + Math.round(task.qtd_prev) + "</span>";
            }}
        ];

        // CUSTOMIZAÇÃO DAS BARRAS (Unificação Planejado/Realizado)
        gantt.templates.task_text = function(start, end, task){
            if(task.type == 'setup') return "<b>SETUP</b>";
            return "<b>" + Math.round(task.pct) + "%</b>";
        };

        gantt.addTaskLayer(function(task){
            if(task.type == 'setup' || task.pct <= 0) return null;
            
            var el = document.createElement('div');
            el.className = 'realized-bar';
            var fill = document.createElement('div');
            fill.className = 'realized-fill';
            fill.style.width = Math.min(task.pct, 100) + "%";
            el.appendChild(fill);
            return el;
        });

        // TOOLTIP DETALHADO
        gantt.plugins({ tooltip: true });
        gantt.templates.tooltip_text = function(start, end, task){
            var html = "<b>OP:</b> " + task.op + "<br/>";
            html += "<b>SKU:</b> " + task.sku + "<br/>";
            html += "<b>Início:</b> " + gantt.templates.tooltip_date_format(start) + "<br/>";
            html += "<b>Fim:</b> " + gantt.templates.tooltip_date_format(end) + "<br/>";
            if(task.type != 'setup'){
                html += "<b>Previsto:</b> " + task.qtd_prev + "<br/>";
                html += "<b>Realizado:</b> " + task.qtd_real + " (" + Math.round(task.pct) + "%)<br/>";
                if(task.real_start) html += "<b>Real Início:</b> " + task.real_start + "<br/>";
            }
            return html;
        };

        gantt.init("gantt_here");
        gantt.parse({ data: <?= json_encode($tasks) ?> });
        
        // Auto-ajuste da timeline para os dados carregados
        if(gantt.getTaskByTime().length > 0){
            var range = gantt.getSubtaskDates();
            if(range.start && range.end){
                gantt.config.start_date = gantt.date.add(range.start, -1, "day");
                gantt.config.end_date = gantt.date.add(range.end, 1, "day");
                gantt.render();
            }
        }
    </script>
</body>
</html>
