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

// ===== BUSCAR DADOS REALIZADO =====
$realizadoMap = [];
if (!empty($schedule) && $selectedId) {
    // Obter período do schedule
    $startDate = null;
    $endDate = null;
    foreach ($schedule as $row) {
        $dataRow = strtotime($row['sch_inicio_producao']);
        if ($dataRow) {
            if (!$startDate || $dataRow < $startDate) $startDate = $dataRow;
            if (!$endDate || $dataRow > $endDate) $endDate = $dataRow;
        }
    }
    
    // Converter para formato SQL
    if ($startDate && $endDate) {
        $sqlStart = date('Y-m-d', $startDate);
        $sqlEnd = date('Y-m-d', $endDate);
        
        // Query para buscar realizado agrupado por OP
        $stmt = $pdo->prepare("
            SELECT ordem_op, SUM(quantidade) as total_realizado
            FROM realizado_2026_excel
            WHERE data_evento >= ? AND data_evento <= ?
            GROUP BY ordem_op
        ");
        $stmt->execute([$sqlStart, $sqlEnd]);
        $realizadoRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapear por OP
        foreach ($realizadoRows as $row) {
            $realizadoMap[(string)$row['ordem_op']] = (float)$row['total_realizado'];
        }
    }
}

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
            
            // Buscar realizado para esta OP
            $quantidadeRealizada = $realizadoMap[$op] ?? 0.0;
            $quantidadePrevista = (float)($row['sch_quantidade'] ?? 0);
            $percentualCumprimento = $quantidadePrevista > 0 ? ($quantidadeRealizada / $quantidadePrevista) * 100 : 0;
            
            // Cor baseada em cumprimento
            $cor = '#3498db'; // Azul padrão para previsto
            if (!$isSetup) {
                if ($quantidadeRealizada > 0) {
                    if ($percentualCumprimento >= 100) {
                        $cor = '#10b981'; // Verde - cumprimento >= 100%
                    } elseif ($percentualCumprimento >= 80) {
                        $cor = '#f59e0b'; // Laranja - cumprimento 80-99%
                    } else {
                        $cor = '#ef4444'; // Vermelho - cumprimento < 80%
                    }
                }
            }
            
            $tasks[] = [
                'id' => (int)$row['sch_id'],
                'text' => ($isSetup ? "⚙️ SETUP" : "📦 OP " . $op . "\n" . trim($row['sch_descricao'] ?? '-')),
                'descricao_produto' => trim($row['sch_descricao'] ?? '-'),
                'start_date' => date('d-m-Y H:i', strtotime($start)),
                'end_date' => date('d-m-Y H:i', strtotime($end)),
                'color' => $cor,
                'progress' => 1,
                'open' => true,
                'sku' => $row['sch_sku'] ?: '-',
                'tipo' => $row['sch_tipo'],
                'op' => $op,
                'quantidade_prevista' => $quantidadePrevista,
                'quantidade_realizada' => $quantidadeRealizada,
                'percentual_cumprimento' => $percentualCumprimento
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
        <div style="display: flex; gap: 10px;">
            <button type="button" id="syncCodiBtn" class="btn-home" style="background: #27ae60; cursor: pointer;">🔄 Sincronizar CODI</button>
            <a href="index.php" class="btn-home">← Voltar ao Sistema</a>
        </div>
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
                // Simplesmente retornar o text - ele já contém a quebra de linha e descrição
                return task.text;
            }
        },
        {
            name: "realizado",
            label: "<span style='display: inline-block; width: 60px; text-align: center;'>Previsto</span><span style='display: inline-block; margin: 0 8px;'>|</span><span style='display: inline-block; width: 80px; text-align: center;'>Realizado</span>",
            width: 200,
            template: function(task) {
                // Se for SETUP, não mostrar
                if(task.text && task.text.indexOf("SETUP") !== -1) {
                    return '<span style="color: #999;">-</span>';
                }
                
                var prev = task.quantidade_prevista || 0;
                var real = task.quantidade_realizada || 0;
                var pct = task.percentual_cumprimento || 0;
                
                // Cor para o REALIZADO (baseado em porcentagem)
                var bgColorRealizado = '#d1d5db'; // Cinza padrão
                if (real > 0) {
                    bgColorRealizado = pct >= 100 ? '#10b981' : (pct >= 80 ? '#f59e0b' : '#ef4444');
                }
                
                // PREVISTO sempre em verde
                var bgColorPrevisto = '#10b981';
                
                return '<span style="background: ' + bgColorPrevisto + '; color: white; padding: 2px 4px; border-radius: 3px; font-size: 11px; font-weight: bold; display: inline-block; width: 60px; text-align: center;">' +
                       prev.toFixed(0) +
                       '</span>' +
                       '<span style="color: #999; margin: 0 8px; display: inline-block;">|</span>' +
                       '<span style="background: ' + bgColorRealizado + '; color: white; padding: 2px 4px; border-radius: 3px; font-size: 11px; font-weight: bold; display: inline-block; width: 80px;">' +
                       real.toFixed(0) + ' (' + pct.toFixed(0) + '%)' +
                       '</span>';
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
    gantt.config.min_column_width = 100;

    // Esconder coluna "realizado" (informação agora está nas barras)
    setTimeout(function() {
        var col = document.querySelector('[data-column-name="realizado"]');
        if(col) col.style.display = 'none';
    }, 100);

    // Inicialização com os dados
    var tasksData = {
        data: <?= json_encode($tasks) ?>
    };

    gantt.init("gantt_here");
    gantt.parse(tasksData);

    // ===== ADICIONAR INFORMAÇÃO REALIZADO SOBRE AS BARRAS =====
    gantt.attachEvent("onAfterTaskRender", function(id, task, div){
        // Pular se for SETUP ou se não houver espaço
        if(task.text && task.text.indexOf("SETUP") !== -1) {
            return;
        }
        
        var prev = task.quantidade_prevista || 0;
        var real = task.quantidade_realizada || 0;
        var pct = task.percentual_cumprimento || 0;
        
        // Procurar a barra (elemento com classe gantt_task_bar)
        var bars = div.getElementsByClassName("gantt_task_bar");
        if(bars.length === 0) return;
        
        var bar = bars[0];
        
        // Criar overlay com informação - REALIZADO | PREVISTO
        var overlay = document.createElement('div');
        overlay.style.position = 'absolute';
        overlay.style.top = '2px';
        overlay.style.left = '3px';
        overlay.style.fontSize = '9px';
        overlay.style.fontWeight = 'bold';
        overlay.style.color = '#fff';
        overlay.style.padding = '1px 4px';
        overlay.style.borderRadius = '2px';
        overlay.style.whiteSpace = 'nowrap';
        overlay.style.pointerEvents = 'none';
        overlay.style.zIndex = '10';
        
        // Criar spans para cada número com cores diferentes
        var realSpan = document.createElement('span');
        realSpan.style.backgroundColor = '#ef4444';  // Vermelho para realizado
        realSpan.style.padding = '0 2px';
        realSpan.textContent = real.toFixed(0);
        
        var divider = document.createElement('span');
        divider.style.color = '#999';
        divider.style.margin = '0 2px';
        divider.textContent = '|';
        
        var prevSpan = document.createElement('span');
        prevSpan.style.backgroundColor = '#10b981';  // Verde para previsto
        prevSpan.style.padding = '0 2px';
        prevSpan.textContent = prev.toFixed(0);
        
        var pctSpan = document.createElement('span');
        pctSpan.style.color = '#fff';
        pctSpan.style.margin = '0 2px 0 0';
        pctSpan.textContent = '(' + pct.toFixed(0) + '%)';
        
        overlay.appendChild(realSpan);
        overlay.appendChild(divider);
        overlay.appendChild(prevSpan);
        overlay.appendChild(pctSpan);
        
        // Adicionar ao bar
        bar.style.position = 'relative';
        bar.appendChild(overlay);
    });

    // Mostrar coluna "realizado" novamente
    setTimeout(function() {
        var col = document.querySelector('[data-column-name="realizado"]');
        if(col) col.style.display = '';  // Mostrar
    }, 100);

    // ========== SINCRONIZAÇÃO CODI AUTOMÁTICA ==========
    // Verifica se já sincronizou hoje, se não sincroniza automaticamente
    function autoSyncCODI() {
        const today = new Date().toISOString().split('T')[0];
        const lastSyncKey = 'codi_last_sync_date';
        const lastSyncDate = localStorage.getItem(lastSyncKey);
        
        // Se já sincronizou hoje, não faz nada
        if (lastSyncDate === today) {
            console.log('[CODI] Ja sincronizado hoje:', today);
            return;
        }
        
        console.log('[CODI] Iniciando sincronizacao automática...');
        
        // Sincronizar silenciosamente
        fetch('api/sync_codi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sync_yesterday' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('[CODI OK] Sincronizado:', data.message);
                localStorage.setItem(lastSyncKey, today);
            } else {
                console.warn('[CODI] Ja sincronizado hoje:', data.message);
                localStorage.setItem(lastSyncKey, today);
            }
        })
        .catch(error => {
            console.error('[CODI ERRO]', error);
        });
    }
    
    // Executar auto-sync quando página carrega
    autoSyncCODI();

    // ========== SINCRONIZAÇÃO CODI (BOTÃO MANUAL) ==========
    document.getElementById('syncCodiBtn').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.textContent = '⏳ Sincronizando...';
        
        fetch('api/sync_codi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'sync_yesterday',
                force: true
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const today = new Date().toISOString().split('T')[0];
                localStorage.setItem('codi_last_sync_date', today);
                alert('✅ Sincronização concluída!\n\n' + data.message);
                btn.textContent = '🔄 Sincronizar CODI';
                btn.disabled = false;
            } else {
                alert('⚠️ ' + data.message);
                btn.textContent = '🔄 Sincronizar CODI';
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('❌ Erro ao sincronizar: ' + error.message);
            btn.textContent = '🔄 Sincronizar CODI';
            btn.disabled = false;
        });
    });
</script>
</body>
</html>
