<?php declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Data\DatabaseData;

$databaseData = new DatabaseData();
$datasets = $databaseData->all();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle PCP</title>
    <link rel="stylesheet" href="/controlepcp/assets/css/app.css">
    <link rel="stylesheet" href="/controlepcp/assets/css/theme.css">
    <style>
        .history-list { display: grid; gap: 12px; margin-top: 10px; }
        .history-card { border: 1px solid var(--line, #ddd); border-radius: 10px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.05); cursor: pointer; }
        .history-card:hover { box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .history-meta { display: flex; gap: 14px; font-size: 14px; color: #555; }
        .history-title { font-weight: 600; font-size: 16px; color: #111; }
        .history-empty { text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="app-shell">
        <header class="hero">
            <div class="hero-copy">
                <img src="/controlepcp/logo.jpg" alt="Aqua Fast" class="hero-logo">
                <nav class="top-nav" aria-label="Navegacao principal">
                    <button type="button" class="nav-shortcut" data-target="section-home">Painel Inicial</button>
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
                            <p>Escolha um modulo para comecar. O sistema abre apenas a area selecionada.</p>
                        </div>
                    </div>

                    <div class="home-grid">
                        <button type="button" class="home-card" data-target="section-calendar">
                            <strong>Hor&aacute;rios de Trabalho</strong>
                            <span>Configure dias uteis, turnos e feriados.</span>
                        </button>
                        <button type="button" class="home-card" data-target="section-products">
                            <strong>SKU (Produtos)</strong>
                            <span>Cadastre descricao, linha, taxa e unidade.</span>
                        </button>
                        <button type="button" class="home-card" data-target="section-matrix">
                            <strong>Matrizes</strong>
                            <span>Defina o setup entre origem e destino.</span>
                        </button>
                        <button type="button" class="home-card" data-target="section-program">
                            <strong>Programação de PCP</strong>
                            <span>Monte a sequÃªncia e calcule a produÃ§Ã£o.</span>
                        </button>
                        <button type="button" class="home-card" data-target="section-programacoes">
                            <strong>Histórico de Programações</strong>
                            <span>Consulte, crie, edite ou delete programações.</span>
                        </button>
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
                            <label class="field">
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
            </section>
        </main>
    </div>

    <script>
        window.PCP_BOOTSTRAP = <?= json_encode([
            'datasets' => $datasets,
            'sampleProgram' => $datasets['sample_program'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <div id="app-toast" class="app-toast" aria-live="polite" aria-atomic="true"></div>
    <script src="/controlepcp/assets/js/xlsx-import.js?v=3"></script>
    <script src="/controlepcp/assets/js/app.js?v=10"></script>
</body>
</html>









