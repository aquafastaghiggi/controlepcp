<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Data\MockData;

$mockData = MockData::all();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle PCP</title>
    <link rel="stylesheet" href="/controlepcp/assets/css/app.css">
    <link rel="stylesheet" href="/controlepcp/assets/css/theme.css">
</head>
<body>
    <div class="app-shell">
        <header class="hero">
            <div class="hero-copy">
                <img src="/controlepcp/logo.jpg" alt="Aqua Fast" class="hero-logo">
                <nav class="top-nav" aria-label="Navegacao principal">
                    <button type="button" class="nav-shortcut" data-target="section-home">Painel Inicial</button>
                </nav>
            </div>
            <div class="hero-note is-hidden">
                <span class="note-label">Motor ativo</span>
                <strong>Linha 2</strong>
                <span>Calendario, SKU e setup mockados</span>
            </div>
        </header>

        <main class="layout layout-single">
            <aside class="sidebar is-hidden">
                <section class="panel">
                    <h2>Calendario produtivo</h2>
                    <ul class="info-list">
                        <?php foreach ($mockData['calendar']['intervals'] as $interval): ?>
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
                            <strong>Programa&ccedil;&atilde;o de PCP</strong>
                            <span>Monte a sequencia e calcule a producao.</span>
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
                            <h2>Programa&ccedil;&atilde;o de PCP</h2>
                            <p>Informe o inicio base, preencha os itens e deixe as proximas datas por conta do calculo.</p>
                        </div>
                        <button type="button" id="add-row" class="ghost-button">Adicionar item</button>
                    </div>

                    <form id="simulation-form">
                        <div class="field-grid">
                            <label class="field">
                                <span>Eficiencia de producao (%)</span>
                                <input type="text" name="production_efficiency" value="100" inputmode="decimal" placeholder="100">
                            </label>
                        </div>

                        <input type="hidden" name="base_start" value="2026-04-14T13:35" required>
                        <input type="hidden" name="query_datetime" value="2026-04-15T00:00">

                        <div class="table-wrap entry-table-wrap">
                            <table class="entry-table">
                                <thead>
                                    <tr>
                                        <th>Seq.</th>
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
                            <button type="button" id="clear-simulation" class="ghost-button">Limpar programacao</button>
                            <button type="submit" class="primary-button">Calcular programacao</button>
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
                                        <td colspan="10">Nenhuma simulacao calculada ainda.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>
            </section>
        </main>
    </div>

    <script>
        window.PCP_BOOTSTRAP = <?= json_encode([
            'datasets' => $mockData,
            'sampleProgram' => $mockData['sample_program'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <div id="app-toast" class="app-toast" aria-live="polite" aria-atomic="true"></div>
    <script src="/controlepcp/assets/js/xlsx-import.js?v=3"></script>
    <script src="/controlepcp/assets/js/app.js?v=8"></script>
</body>
</html>



