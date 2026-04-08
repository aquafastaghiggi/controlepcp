<?php
/**
 * Gráfico de Gantt PCP - Versão Final (DHTMLX Gantt com Scroll Persistente)
 * Foco em: Cabeçalho Hierárquico, Scroll Vertical/Horizontal Sempre Visíveis.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Repository\ProgramacaoRepository;
use App\Database\Connection;

Auth::startSession();
$repo = new ProgramacaoRepository();
$pdo = Connection::get();

// Buscar lista de programações para o seletor
$programacoes = $repo->getAllProgramacoes(100, 0);

// Se um ID específico foi selecionado
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$schedule = [];
$programacaoInfo = null;

if ($selectedId) {
    $programacaoInfo = $repo->getProgramacaoById($selectedId);
    if ($programacaoInfo) {
        $schedule = $repo->getProgramacaoSchedule($selectedId);
    }
} elseif (!empty($programacoes)) {
    $selectedId = (int)$programacoes[0]['prg_id'];
    $programacaoInfo = $programacoes[0];
    $schedule = $repo->getProgramacaoSchedule($selectedId);
}

// Helper para pegar OP de prg_itens baseado no SKU
function getOpForSku(PDO $pdo, int $programId, string $sku): string {
    $stmt = $pdo->prepare(
        'SELECT prg_itens_op FROM prg_itens WHERE prg_programa_id = :programId AND prg_sku = :sku LIMIT 1'
    );
    $stmt->execute(['programId' => $programId, 'sku' => $sku]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['prg_itens_op'] ?? 'S/OP';
}

// Preparar dados para o DHTMLX Gantt
$tasks = [];

if (!empty($schedule)) {
    foreach ($schedule as $row) {
        $start = $row['sch_inicio_producao'];
        $end = $row['sch_fim_producao'];
        
        if ($start && $end) {
            $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
            
            // Pegar a OP do item correspondente em prg_itens
            $op = 'S/OP';
            if (!$isSetup && $row['sch_sku'] && $selectedId) {
                $op = getOpForSku($pdo, $selectedId, $row['sch_sku']);
            }
            
            $tasks[] = [
                'id' => (int)$row['sch_id'],
                'text' => ($isSetup ? "⚙️ SETUP" : "📦 OP " . $op),
                'start_date' => date('d-m-Y H:i', strtotime($start)),
                'end_date' => date('d-m-Y H:i', strtotime($end)),
                'color' => $isSetup ? '#e67e22' : '#3498db',
                'progress' => 1,
                'open' => true,
                'sku' => $row['sch_sku'] ?: '-',
                'tipo' => $row['sch_tipo']
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gantt PCP - Visualização de Cronograma</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <!-- DHTMLX Gantt CSS -->
    <link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --setup-color: #e67e22;
            --prod-color: #3498db;
        }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f4f7f6; 
            margin: 0; 
            padding: 15px; 
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
        }
        .header-card { 
            background: white; 
            padding: 15px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            margin-bottom: 15px; 
        }
        .top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        
        /* Container do Gantt com altura fixa para forçar scroll vertical */
        #gantt_here { 
            flex: 1; 
            border-radius: 8px; 
            border: 1px solid #ddd; 
            background: #fff; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            position: relative;
            min-height: 500px;
            overflow: hidden;
        }

        .controls { display: flex; gap: 15px; align-items: center; font-size: 14px; flex-wrap: wrap; }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; min-width: 350px; }
        .btn-home { background: var(--primary-dark); color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: bold; }
        
        /* Estilização interna do DHTMLX Gantt */
        .gantt_task_line { border-radius: 4px; border: none; }
        .gantt_task_content { font-size: 11px; font-weight: bold; }
        .gantt_grid_head_cell { font-weight: bold; color: #555; }
        .gantt_scale_cell { font-weight: bold; color: #2c3e50; border-right: 1px solid #ebebeb; }
        
        /* Forçar visibilidade das barras de scroll - APARECER SEMPRE QUE NECESSÁRIO */
        .gantt_hor_scroll { 
            background-color: #f8f9fa !important;
            display: block !important;
            visibility: visible !important;
            overflow-x: auto !important;
        }
        
        .gantt_ver_scroll { 
            background-color: #f8f9fa !important;
            display: block !important;
            visibility: visible !important;
            overflow-y: auto !important;
        }
        
        .gantt_scrollbar {
            display: block !important;
            visibility: visible !important;
        }

        .legend { 
            display: flex; 
            gap: 20px; 
            padding: 10px; 
            background: white; 
            border-radius: 0 0 8px 8px; 
            font-size: 12px; 
            border-top: 1px solid #eee; 
            margin-top: 10px;
        }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .box { width: 14px; height: 14px; border-radius: 3px; }
    </style>
</head>
<body>

<div class="header-card">
    <div class="top-row">
        <h1 style="margin:0; font-size: 20px; color: var(--primary-dark);">📊 Gráfico de Sequenciamento do PCP</h1>
        <a href="index.php" class="btn-home">← Voltar ao Sistema</a>
    </div>
    <div class="controls">
        <form method="GET" id="filterForm">
            <strong>Programação:</strong>
            <select name="id" onchange="this.form.submit()">
                <?php foreach ($programacoes as $prg): ?>
                    <option value="<?= $prg['prg_id'] ?>" <?= $selectedId === (int)$prg['prg_id'] ? 'selected' : '' ?>>
                        <?php 
                            $linha = $prg['linha_excel_dominante'] ?: $prg['lin_codigo'] ?: 'S/Linha';
                            $inicio = $prg['inicio_base_cronograma'] ? date('d/m/Y H:i', strtotime($prg['inicio_base_cronograma'])) : 'S/data';
                            $prog = $prg['programacao_criada_em'] ? date('d/m/Y H:i', strtotime($prg['programacao_criada_em'])) : 'S/data';
                            $eff = $prg['prg_eficiencia'] ?? 0;
                            echo "Linha $linha | Início: $inicio | Prog: $prog | Ef: " . $eff . '%';
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($programacaoInfo): ?>
            <span style="color: #666;">Eficiência: <b><?= $programacaoInfo['prg_eficiencia'] ?>%</b> | Início: <b><?= date('d/m/Y H:i', strtotime($programacaoInfo['prg_base_inicio'])) ?></b></span>
        <?php endif; ?>
    </div>
</div>

<div id="gantt_here"></div>

<div class="legend">
    <div class="legend-item"><div class="box" style="background:var(--prod-color)"></div> Produção (SKU)</div>
    <div class="legend-item"><div class="box" style="background:var(--setup-color)"></div> Setup (Troca)</div>
    <div style="margin-left: auto; color: #888; font-style: italic;">* Use as barras de rolagem para navegar no tempo e nos itens.</div>
</div>

<!-- DHTMLX Gantt JS -->
<script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script>
<script>
    // Localização para Português (Deve vir antes da config)
    gantt.i18n.setLocale("pt");

    // Configurações Básicas
    gantt.config.date_format = "%d-%m-%Y %H:%i";
    gantt.config.readonly = true;

    // FORÇAR SCROLLS PERSISTENTES
    gantt.config.autosize = false; 
    gantt.config.scroll_size = 24; // Barra de scroll mais larga e fácil de clicar
    gantt.config.enable_scroll = true;
    
    // Configuração de Layout para garantir scrolls
    gantt.config.layout = {
        css: "gantt_container",
        rows: [
            {
                cols: [
                    {view: "grid", id: "grid", scrollY: "scrollVer"},
                    {resizer: true, width: 1},
                    {view: "timeline", id: "timeline", scrollX: "scrollHor", scrollY: "scrollVer"},
                    {view: "scrollbar", id: "scrollVer"}
                ]
            },
            {view: "scrollbar", id: "scrollHor"}
        ]
    };

    gantt.config.columns = [
        {
            name: "text", 
            label: "Produto / Recurso", 
            width: 220, 
            tree: true,
            template: function(task) {
                if(task.text && task.text.indexOf("SETUP") !== -1) {
                    return '<span style="float: right; margin-right: 8px;">' + task.text + '</span>';
                }
                return task.text;
            }
        }
    ];

    // CABEÇALHO HIERÁRQUICO (Semanas e Dias)
    gantt.config.scales = [
        {unit: "week", step: 1, format: function(date){
            var dateToStr = gantt.date.date_to_str("Semana %W");
            return dateToStr(date);
        }},
        {unit: "day", step: 1, format: "%D, %d %M"}
    ];

    // Ajustes de Dimensões para forçar o scroll horizontal
    gantt.config.scale_height = 50;
    gantt.config.row_height = 32;
    gantt.config.min_column_width = 100; // Largura mínima das colunas

    // Inicialização com os dados
    var tasksData = {
        data: <?= json_encode($tasks) ?>
    };

    gantt.init("gantt_here");
    gantt.parse(tasksData);

    // Centralizar na primeira tarefa ao carregar
    if(tasksData.data.length > 0){
        var firstTask = tasksData.data[0];
        gantt.showDate(gantt.date.parseDate(firstTask.start_date, "%d-%m-%Y %H:%i"));
    }
</script>
</body>
</html>
