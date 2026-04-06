<?php declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Data\DatabaseData;

Auth::startSession();
Auth::requireLogin();

$databaseData = new DatabaseData();
$datasets = $databaseData->all();

$isSandbox = (getenv('APP_ENV') ?: '') === 'sandbox';
$showReleaseCenter = $isSandbox && Auth::isAdmin();
$releaseCenterState = null;

if ($showReleaseCenter) {
    $releaseCenterPath = __DIR__ . '/.tmp/release-center.json';
    $rawReleaseCenter = @file_get_contents($releaseCenterPath) ?: '';
    // `release-center.json` pode ser gravado via PowerShell (UTF-8 com BOM). Remover BOM para o json_decode funcionar.
    if (strncmp($rawReleaseCenter, "\xEF\xBB\xBF", 3) === 0) {
        $rawReleaseCenter = substr($rawReleaseCenter, 3);
    }
    $decodedReleaseCenter = json_decode($rawReleaseCenter, true);

    $releaseCenterState = is_array($decodedReleaseCenter)
        ? $decodedReleaseCenter
        : ['schema' => 1, 'env' => 'sandbox', 'items' => [], 'releases' => []];

    if (!isset($releaseCenterState['publish_checklist']) || !is_array($releaseCenterState['publish_checklist'])) {
        $releaseCenterState['publish_checklist'] = [
            'login_ok' => false,
            'import_excel_ok' => false,
            'calcular_ok' => false,
            'historico_ok' => false,
            'impressao_ok' => false,
        ];
    }
}

// Feature flags: usado para mostrar/ocultar mÃ³dulos no Painel Inicial (prod/sandbox).
$features = [
    'performance' => false,
];
$featureFlagsPath = __DIR__ . '/.tmp/feature-flags.json';
$rawFeatureFlags = @file_get_contents($featureFlagsPath) ?: '';
if ($rawFeatureFlags !== '') {
    // Segurança extra: se algum editor gravar com BOM, evita falha no decode.
    if (strncmp($rawFeatureFlags, "\xEF\xBB\xBF", 3) === 0) {
        $rawFeatureFlags = substr($rawFeatureFlags, 3);
    }
    $decodedFeatureFlags = json_decode($rawFeatureFlags, true);
    if (is_array($decodedFeatureFlags)) {
        $featurePayload = is_array($decodedFeatureFlags['features'] ?? null)
            ? $decodedFeatureFlags['features']
            : $decodedFeatureFlags;
        if (is_array($featurePayload)) {
            $features['performance'] = (bool) ($featurePayload['performance'] ?? $features['performance']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle PCP</title>
    <?php $appCssVersion = @filemtime(__DIR__ . '/assets/css/app.css') ?: 'dev'; ?>
    <?php $appJsVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: 'dev'; ?>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= urlencode((string) $appCssVersion) ?>">
    <link rel="stylesheet" href="assets/css/theme.css?v=<?= urlencode((string) (@filemtime(__DIR__ . '/assets/css/theme.css') ?: 'dev')) ?>">
    <style>
        .history-list { display: grid; gap: 12px; margin-top: 10px; }
        .history-card { border: 1px solid var(--line, #ddd); border-radius: 10px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.05); cursor: pointer; }
        .history-card:hover { box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .history-meta { display: flex; gap: 14px; font-size: 14px; color: #555; }
        .history-title { font-weight: 600; font-size: 16px; color: #111; }
        .history-empty { text-align: center; padding: 20px; }
    </style>
</head>
<body<?= $isSandbox ? ' data-app-env="sandbox"' : '' ?>>
    <div class="app-shell">
        <header class="hero">
            <div class="hero-copy">
                <img src="/controlepcp/logo.jpg" alt="Aqua Fast" class="hero-logo">
                <nav class="top-nav" aria-label="Navegacao principal">
                    <button type="button" class="nav-shortcut" data-target="section-home">Painel Inicial</button>
                    <?php if ($showReleaseCenter): ?>
                        <button type="button" class="nav-shortcut" data-target="section-release-center">Publicação</button>
                    <?php endif; ?>
                    <?php if (Auth::isAdmin()): ?>
                        <a class="nav-link" href="users.php">Usuários</a>
                    <?php endif; ?>
                    <a class="nav-link" href="logout.php">Sair</a>
                    <!-- <button type="button" class="nav-shortcut" id="reset-data">Resetar dados</button> -->
                </nav>
            </div>
            <div class="hero-note is-hidden">
                <span class="note-label">Motor ativo</span>
                <strong>Linha <?= htmlspecialchars($datasets['calendar']['line'] ?? '') ?></strong>
                <span>Dados persistidos no banco de dados</span>
            </div>
        </header>

        <main class="layout layout-single">
            <aside class="sidebar is-hidden">
                <section class="panel">
                    <h2>Calendario produtivo</h2>
                    <ul class="info-list">
                        <?php foreach ($datasets['calendar']['intervals'] as $interval): ?>
                            <li><?= htmlspecialchars($interval['start']) ?> - <?= htmlspecialchars($interval['end']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="muted">Aplicado em todos os dias para este MVP.</p>
                </section>
            </aside>

            <section class="workspace">
                <section class="panel app-section is-active" id="section-home">
                    <div class="panel-heading panel-heading-stack">
                        <div>
                            <h1>Painel inicial</h1>
                            <p>Escolha uma acao para programar e calcular a producao.</p>
                        </div>
                        <div class="home-actions-bar">
                            <button type="button" class="ghost-button home-quick" data-target="section-program">Nova programacao</button>
                            <button type="button" class="ghost-button home-quick" data-home-action="import">Importar Excel</button>
                            <button type="button" class="ghost-button home-quick" data-target="section-programacoes">Ver historico</button>
                            <?php if ($showReleaseCenter): ?>
                                <button type="button" class="ghost-button home-quick" data-target="section-release-center">Central de publicação</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="home-dashboard">
                        <div class="home-group">
                        <div class="home-group-title">Opera&ccedil;&atilde;o</div>
                            <div class="home-grid home-grid--operation">
                                <button type="button" class="home-card home-card--primary" data-target="section-program">
                                    <div class="home-card-top">
                                        <strong>Programação de PCP</strong>
                                        <span class="home-card-badge" data-home-badge="program"></span>
                                    </div>
                                    <span>Importe a planilha, ajuste dias/turnos e calcule.</span>
                                    <div class="home-card-meta" data-home-meta="program"></div>
                                    <div class="home-card-cta">Abrir programa&ccedil;&atilde;o</div>
                                </button>
                                <button type="button" class="home-card" data-target="section-programacoes">
                                    <div class="home-card-top">
                                        <strong>Hist&oacute;rico de Programa&ccedil;&otilde;es</strong>
                                        <span class="home-card-badge" data-home-badge="history"></span>
                                    </div>
                                    <span>Reimprima e compare programa&ccedil;&otilde;es por efici&ecirc;ncia.</span>
                                    <div class="home-card-meta" data-home-meta="history"></div>
                                    <div class="home-card-cta">Abrir hist&oacute;rico</div>
                                </button>
                                <button type="button" class="home-card" data-target="section-performance">
                                    <div class="home-card-top">
                                        <strong>Desempenho</strong>
                                        <span class="home-card-badge" data-home-badge="performance"></span>
                                    </div>
                                    <span>Previsto vs realizado (gr&aacute;ficos e Gantt).</span>
                                    <div class="home-card-meta" data-home-meta="performance"></div>
                                    <div class="home-card-cta">Abrir desempenho</div>
                                </button>
                                <?php if ($showReleaseCenter): ?>
                                    <button type="button" class="home-card home-card--sandbox" data-target="section-release-center">
                                        <div class="home-card-top">
                                            <strong>Central de Publicação</strong>
                                            <span class="home-card-badge" data-home-badge="release"></span>
                                        </div>
                                        <span>Backlog, aprovações e publicação para produção.</span>
                                        <div class="home-card-meta" data-home-meta="release"></div>
                                        <div class="home-card-cta">Abrir central</div>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="home-group">
                        <div class="home-group-title">Par&acirc;metros do c&aacute;lculo</div>
                            <div class="home-grid home-grid--params">
                                <button type="button" class="home-card" data-target="section-calendar">
                                    <div class="home-card-top">
                                        <strong>Hor&aacute;rios de Trabalho</strong>
                                        <span class="home-card-badge" data-home-badge="calendar"></span>
                                    </div>
                                    <span>Turnos, dias &uacute;teis e feriados.</span>
                                    <div class="home-card-meta" data-home-meta="calendar"></div>
                                    <div class="home-card-cta">Configurar</div>
                                </button>
                                <button type="button" class="home-card" data-target="section-products">
                                    <div class="home-card-top">
                                        <strong>SKU (Produtos)</strong>
                                        <span class="home-card-badge" data-home-badge="products"></span>
                                    </div>
                                    <span>Cadastre SKUs, taxas e unidade.</span>
                                    <div class="home-card-meta" data-home-meta="products"></div>
                                    <div class="home-card-cta">Gerenciar</div>
                                </button>
                                <button type="button" class="home-card" data-target="section-matrix">
                                    <div class="home-card-top">
                                        <strong>Matrizes</strong>
                                        <span class="home-card-badge" data-home-badge="matrix"></span>
                                    </div>
                                    <span>Defina setups entre origem e destino.</span>
                                    <div class="home-card-meta" data-home-meta="matrix"></div>
                                    <div class="home-card-cta">Gerenciar</div>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel app-section" id="section-calendar">
                    <div class="panel-heading">
                        <div>
                            <h2>Hor&aacute;rios de Trabalho</h2>
                            <p>Cadastre os intervalos validos, os dias uteis e os feriados usados no calculo.</p>
                        </div>
                        <button type="button" id="add-interval" class="ghost-button">Adicionar intervalo</button>
                    </div>

                    <div class="table-wrap compact-wrap">
                        <table class="entry-table">
                            <thead>
                                <tr>
                                    <th>Ordem</th>
                                    <th>Dias</th>
                                    <th>Inicio</th>
                                    <th>Fim</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="calendar-body"></tbody>
                        </table>
                    </div>

                    <div class="holiday-panel holiday-panel-bottom">
                        <div class="holiday-panel-heading">
                            <h3>Feriados / Pausas</h3>
                            <p>Cadastre a data e o nome do feriado ou da pausa para bloquear o dia no calendario produtivo e controlar paradas gerais.</p>
                        </div>
                        <div class="holiday-form-row">
                            <input type="date" id="holiday-date" class="holiday-date-input">
                            <input type="text" id="holiday-name" class="holiday-name-input" placeholder="Nome do feriado ou pausa">
                            <button type="button" id="add-holiday" class="ghost-button">Adicionar feriado / pausa</button>
                        </div>
                        <div id="holiday-preview" class="holiday-grid">
                            <div class="holiday-empty">Nenhum feriado lancado.</div>
                        </div>
                    </div>
                </section>

                <section class="panel app-section" id="section-products">
                    <div class="panel-heading">
                        <div>
                            <h2>SKU (Produtos)</h2>
                            <p>Quantidade de SKU importados: <strong id="products-count">0</strong></p>
                        </div>
                        <div class="panel-actions">
                            <input type="file" id="products-import-file" class="is-hidden" accept=".xlsx">
                            <button type="button" id="import-products" class="ghost-button">Importar Excel</button>
                            <button type="button" id="clear-products" class="ghost-button">Limpar produtos</button>
                            <button type="button" id="add-product" class="ghost-button">Adicionar SKU</button>
                            <button type="button" id="sync-products" class="ghost-button">Sincronizar produtos</button>
                        </div>
                    </div>

                    <div class="table-wrap compact-wrap">
                        <table class="entry-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Descricao</th>
                                    <th>Ref. setup</th>
                                    <th>Linha</th>
                                    <th>Producao/h</th>
                                    <th>Unidade</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="products-body"></tbody>
                        </table>
                    </div>
                </section>

                <section class="panel app-section" id="section-matrix">
                    <div class="panel-heading">
                        <div>
                            <h2>Matrizes</h2>
                            <p>Edite os tempos de setup entre produto de origem e produto de destino.</p>
                        </div>
                        <div class="panel-actions">
                            <input type="file" id="matrix-import-file" class="is-hidden" accept=".xlsx">
                            <button type="button" id="import-matrix" class="ghost-button">Importar Excel</button>
                            <button type="button" id="clear-matrix" class="ghost-button">Limpar matrizes</button>
                            <button type="button" id="add-matrix-row" class="ghost-button">Adicionar setup</button>
                        </div>
                    </div>

                    <div class="matrix-import-summary" id="matrix-import-summary">
                        <p class="matrix-import-summary-empty">Nenhuma planilha importada recentemente.</p>
                    </div>

                    <div class="matrix-toolbar">
                        <div id="matrix-line-nav" class="matrix-line-nav"></div>
                        <div class="matrix-toolbar-actions">
                            <button type="button" id="matrix-valid-toggle" class="matrix-valid-button">Registros validados (0)</button>
                            <button type="button" id="matrix-issues-toggle" class="matrix-issues-button">Inconsistencias (0)</button>
                        </div>
                    </div>

                    <div id="matrix-valid-panel" class="matrix-valid-panel is-hidden">
                        <div id="matrix-valid-body" class="matrix-valid-body"></div>
                    </div>

                    <div id="matrix-issues-panel" class="matrix-issues-panel is-hidden">
                        <div id="matrix-issues-body" class="matrix-issues-body"></div>
                    </div>

                    <div id="matrix-pagination" class="matrix-pagination"></div>

                    <div class="table-wrap matrix-wrap">
                        <table class="entry-table">
                            <thead>
                                <tr>
                                    <th>Produto origem</th>
                                    <th>Produto destino</th>
                                    <th>Tempo</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="matrix-body"></tbody>
                        </table>
                    </div>
                </section>

                <section class="panel app-section" id="section-program">
                    <div class="panel-heading">
                        <div>
                            <h2>Programação de PCP</h2>
                            <p>Informe o inÃ­cio base, preencha os itens e deixe as prÃ³ximas datas por conta do cÃ¡lculo.</p>
                        </div>
                        <div class="panel-actions">
                            <button type="button" id="new-programacao-btn" class="ghost-button">+ Nova programação</button>
                            <button type="button" id="add-row" class="ghost-button">Adicionar item</button>
                            <input type="file" id="programacao-import-file" class="is-hidden" accept=".xlsx">
                            <button type="button" id="import-programacao" class="ghost-button">Importar Excel</button>
                        </div>
                    </div>

                    <div class="programacao-import-wrapper">
                        <div class="programacao-import-note">
                            <p class="muted">Importe um arquivo Excel para exibir as abas por linha e carregar os itens na programação.</p>
                        </div>
                        <div id="programacao-import-sheets" class="programacao-import-sheets">
                            <p class="muted">Nenhuma aba importada ainda.</p>
                        </div>
                    </div>

                    <form id="simulation-form">
                        <div class="field-grid">
                            <label class="field is-hidden">
                                <span>Número da OP</span>
                                <input type="text" name="numero_op" placeholder="Ex: OP-2024-001">
                            </label>
                            <label class="field">
                                <span>Eficiência de produção (%)</span>
                                <input type="text" name="production_efficiency" value="100" inputmode="decimal" placeholder="100">
                            </label>
                        </div>

                        <input type="hidden" name="base_start" value="<?= date('Y-m-d\TH:i') ?>" required>
                        <input type="hidden" name="query_datetime" value="<?= date('Y-m-d\TH:i') ?>">

                        <div class="table-wrap entry-table-wrap">
                            <table class="entry-table">
                                <thead>
                                    <tr>
                                        <th>Seq.</th>
                                        <th>OP</th>
                                        <th>SKU</th>
                                        <th>Quantidade (cx)</th>
                                        <th>Inicio informado (1&ordm; item)</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="program-body"></tbody>
                            </table>
                        </div>

                        <div class="form-actions">
                            <button type="button" id="clear-simulation" class="ghost-button">Limpar programação</button>
                            <button type="submit" class="primary-button">Calcular programação</button>
                        </div>
                    </form>

                    <section class="panel result-panel is-hidden" id="result-panel">
                        <div class="panel-heading">
                            <div>
                                <h2>Resultado</h2>
                                <p>A tabela abaixo mostra producao e setup na ordem real de execucao.</p>
                            </div>
                            <span class="status-badge" id="result-status">Aguardando calculo</span>
                        </div>

                        <div id="result-summary" class="summary-grid"></div>

                        <div class="table-wrap">
                            <table class="result-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Seq.</th>
                                        <th>Produto</th>
                                        <th>Producao/h</th>
                                        <th>Programado</th>
                                        <th>Tempo</th>
                                        <th>Data inicio</th>
                                        <th>Inicio</th>
                                        <th class="is-hidden-column">Memoria do calculo</th>
                                        <th>Fim</th>
                                    </tr>
                                </thead>
                                <tbody id="result-body">
                                    <tr class="empty-state-row">
                                        <td colspan="10">Nenhuma simulaÃ§Ã£o calculada ainda.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>

                <section class="panel app-section" id="section-programacoes">
                    <div class="panel-heading">
                        <div>
                            <h2>Histórico de Programações</h2>
                            <p>Visualize e imprima programações calculadas.</p>
                        </div>
                        <div class="panel-actions">
                            <button type="button" id="history-refresh" class="ghost-button">Atualizar</button>
                        </div>
                    </div>

                    <div id="history-list" class="history-list">
                        <div id="history-empty" class="muted">Nenhuma programação encontrada.</div>
                    </div>
                </section>

                <section class="panel app-section" id="section-performance">
                    <div class="panel-heading panel-heading-stack">
                        <div>
                            <h2>Desempenho</h2>
                            <p>Vis&atilde;o gerencial de previsto vs realizado.</p>
                        </div>
                    </div>
                    <div class="performance-controls">
                        <label class="performance-field">
                            <span>Linha</span>
                            <select id="performance-line"></select>
                        </label>
                        <label class="performance-field">
                            <span>Programa&ccedil;&atilde;o (previsto)</span>
                            <select id="performance-program"></select>
                        </label>
                        <label class="performance-field">
                            <span>Comparar com (opcional)</span>
                            <select id="performance-compare"></select>
                        </label>
                        <button type="button" class="ghost-button" id="performance-open">Abrir detalhes</button>
                    </div>
                    <div class="performance-options">
                        <label class="performance-option">
                            <input type="checkbox" id="performance-show-prod" checked>
                            <span>Produ&ccedil;&atilde;o</span>
                        </label>
                        <label class="performance-option">
                            <input type="checkbox" id="performance-show-setup" checked>
                            <span>Setup</span>
                        </label>
                    </div>
                    <div class="performance-zoom" id="performance-zoom-controls">
                        <span class="performance-zoom-title">Zoom</span>
                        <button type="button" class="ghost-button performance-zoom-btn" id="performance-zoom-out" aria-label="Diminuir zoom">-</button>
                        <input type="range" id="performance-zoom" min="60" max="240" step="10" value="100" aria-label="Zoom do Gantt">
                        <button type="button" class="ghost-button performance-zoom-btn" id="performance-zoom-in" aria-label="Aumentar zoom">+</button>
                        <button type="button" class="ghost-button performance-zoom-btn" id="performance-zoom-reset">100%</button>
                        <span class="performance-zoom-value" id="performance-zoom-value">100%</span>
                    </div>
                    <div id="performance-summary" class="performance-summary muted"></div>
                    <div id="performance-kpis" class="performance-kpis"></div>
                    <div id="performance-gantt" class="performance-gantt">
                        <div class="performance-gantt-block">
                            <div class="performance-gantt-title">Previsto A</div>
                            <div id="performance-gantt-a">
                                <div class="performance-gantt-empty muted">Selecione uma programa&ccedil;&atilde;o para visualizar o Gantt.</div>
                            </div>
                        </div>
                        <div class="performance-gantt-block is-hidden" id="performance-gantt-block-b">
                            <div class="performance-gantt-title">Previsto B</div>
                            <div id="performance-gantt-b"></div>
                        </div>
                    </div>
                    <!-- Gráfico Original (Dias em Linha) -->
                    <div id="performance-alt" class="performance-alt hidden">
                        <div id="performance-alt-indicator" class="performance-alt-indicator">
                            <label class="performance-alt-filter">
                                <input type="checkbox" id="performance-alt-filter-prod" checked>
                                <span>Produ&ccedil;&atilde;o</span>
                            </label>
                            <label class="performance-alt-filter">
                                <input type="checkbox" id="performance-alt-filter-setup" checked>
                                <span>Setup</span>
                            </label>
                        </div>
                        <div class="performance-alt-title">Layout alternativo em constru&ccedil;&atilde;o...</div>
                        <div class="performance-alt-body" id="performance-alt-body"></div>
                    </div>

                    <!-- ========== ETAPA 4: Tabela de Contexto ========== -->
                    <div class="performance-data-table-wrapper">
                        <table class="performance-data-table">
                            <thead>
                                <tr>
                                    <th>Seq</th>
                                    <th>SKU</th>
                                    <th>Qtd</th>
                                    <th>Duração</th>
                                    <th>Data</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                </tr>
                            </thead>
                            <tbody id="performance-data-table-body">
                                <tr>
                                    <td colspan="7" class="text-muted">Nenhum dado selecionado</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Novo Gráfico Timeline (Horizontal) - PARALELO -->
                    <div id="performance-timeline" class="performance-timeline hidden">
                        <div id="performance-timeline-controls" class="performance-timeline-controls">
                            <label class="performance-timeline-filter">
                                <input type="checkbox" id="performance-timeline-filter-prod" checked>
                                <span>Produ&ccedil;&atilde;o</span>
                            </label>
                            <label class="performance-timeline-filter">
                                <input type="checkbox" id="performance-timeline-filter-setup" checked>
                                <span>Setup</span>
                            </label>
                        </div>
                        <div class="performance-timeline-title">Linha de Tempo - Operações por Data</div>
                        <div class="performance-timeline-body" id="performance-timeline-body"></div>
                    </div>

                    <div id="performance-daily" class="performance-daily"></div>
                </section>

                <?php if ($showReleaseCenter): ?>
                    <section class="panel app-section" id="section-release-center">
                        <div class="panel-heading">
                            <div>
                                <h2>Central de Publicação</h2>
                                <p>Backlog e aprovações (somente sandbox).</p>
                            </div>
                        </div>
                        <div class="release-center" id="release-center-root"></div>
                        <input type="hidden" id="release-center-csrf" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES) ?>">
                        <script id="release-center-data" type="application/json"><?= json_encode(
                            $releaseCenterState,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                        ) ?></script>
                    </section>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        window.PCP_BOOTSTRAP = <?= json_encode([
            'datasets' => $datasets,
            'sampleProgram' => $datasets['sample_program'] ?? [],
            'features' => $features,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <div id="app-toast" class="app-toast" aria-live="polite" aria-atomic="true"></div>
    <script src="assets/js/xlsx-import.js?v=<?= urlencode((string) (@filemtime(__DIR__ . '/assets/js/xlsx-import.js') ?: 'dev')) ?>"></script>
    <script src="assets/js/app.js?v=<?= urlencode((string) $appJsVersion) ?>"></script>
</body>
</html>









