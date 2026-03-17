<?php
declare(strict_types=1);

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
                            <strong>Programa&ccedil;&atilde;o de PCP</strong>
                            <span>Monte a sequencia e calcule a producao.</span>
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
                        <div class="panel-actions">
                            <button type="button" id="new-programacao-btn" class="ghost-button">+ Nova Programação</button>
                            <button type="button" id="add-row" class="ghost-button">Adicionar item</button>
                        </div>
                    </div>

                    <form id="simulation-form">
                        <div class="field-grid">
                            <label class="field">
                                <span>Número da OP</span>
                                <input type="text" name="numero_op" placeholder="Ex: OP-2024-001">
                            </label>
                            <label class="field">
                                <span>Eficiencia de producao (%)</span>
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

                <section class="panel app-section" id="section-programacoes">
                    <div class="panel-heading">
                        <div>
                            <h2>Histórico de Programações</h2>
                            <p>Consulte programações já realizadas.</p>
                        </div>
                    </div>

                    <div class="field-grid">
                        <label class="field">
                            <span>Buscar por Número da OP</span>
                            <input type="text" id="search-op" placeholder="Digite o número da OP para buscar">
                        </label>
                    </div>

                    <div class="table-wrap compact-wrap">
                        <table class="entry-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Número da OP</th>
                                    <th>Linha</th>
                                    <th>Data de lançamento</th>
                                    <th>Eficiência</th>
                                    <th>Status</th>
                                    <th>Itens</th>
                                    <th>Criado em</th>
                                </tr>
                            </thead>
                            <tbody id="programacoes-body"></tbody>
                        </table>
                    </div>

                    <!-- Modal para criar/editar programação -->
                    <div class="modal-overlay is-hidden" id="programacao-modal">
                        <div class="modal-dialog">
                            <div class="modal-header">
                                <h3>Programação</h3>
                                <button type="button" class="close-button" data-action="close-modal">&times;</button>
                            </div>
                            <form id="programacao-form">
                                <input type="hidden" name="prg_id" id="prg_id">
                                <div class="field-grid">
                                    <label class="field">
                                        <span>Número da OP</span>
                                        <input type="text" name="prg_numero_op" placeholder="Ex: OP-2024-001">
                                    </label>
                                    <label class="field">
                                        <span>Linha</span>
                                        <input type="text" name="lin_codigo" value="L2" placeholder="L2">
                                    </label>
                                </div>
                                <div class="field-grid">
                                    <label class="field">
                                        <span>Data/Hora Base</span>
                                        <input type="datetime-local" name="prg_base_inicio" required>
                                    </label>
                                    <label class="field">
                                        <span>Data/Hora Consulta</span>
                                        <input type="datetime-local" name="prg_data_consulta">
                                    </label>
                                </div>
                                <div class="field-grid">
                                    <label class="field">
                                        <span>Eficiência (%)</span>
                                        <input type="number" name="prg_eficiencia" value="100" min="1" max="200">
                                    </label>
                                    <label class="field">
                                        <span>Status</span>
                                        <select name="prg_status">
                                            <option value="rascunho">Rascunho</option>
                                            <option value="calculado">Calculado</option>
                                            <option value="executado">Executado</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="ghost-button" data-action="close-modal">Cancelar</button>
                                    <button type="submit" class="primary-button">Salvar</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para visualizar detalhes da programação -->
                    <div class="modal-overlay is-hidden" id="programacao-details-modal">
                        <div class="modal-dialog modal-dialog-large">
                            <div class="modal-header">
                                <h3>Detalhes da Programação - OP: <span id="details-op-number">—</span></h3>
                                <button type="button" class="close-button" data-action="close-details-modal">&times;</button>
                            </div>
                            <div class="modal-content">
                                <div class="details-section">
                                    <h4>Informações da Programação</h4>
                                    <div class="info-grid">
                                        <div><strong>ID:</strong> <span id="modal-prog-id">—</span></div>
                                        <div><strong>Número da OP:</strong> <span id="modal-prog-op">—</span></div>
                                        <div><strong>Linha:</strong> <span id="modal-prog-linha">—</span></div>
                                        <div><strong>Base Início:</strong> <span id="modal-prog-base-inicio">—</span></div>
                                        <div><strong>Eficiência:</strong> <span id="modal-prog-eficiencia">—</span></div>
                                        <div><strong>Status:</strong> <span id="modal-prog-status">—</span></div>
                                    </div>
                                </div>

                                <div class="details-section">
                                    <h4>Itens Programados</h4>
                                    <div class="table-wrap">
                                        <table class="result-table">
                                            <thead>
                                                <tr>
                                                    <th>SKU</th>
                                                    <th>Descrição</th>
                                                    <th>Quantidade</th>
                                                    <th>Unidade</th>
                                                </tr>
                                            </thead>
                                            <tbody id="details-itens-body"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="details-section">
                                    <h4>Resultado do Cálculo</h4>
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
                                            <tbody id="details-schedule-body"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="form-actions" style="justify-content: flex-end; padding-top: 20px; border-top: 1px solid var(--line);">
                                    <button type="button" class="ghost-button" data-action="close-details-modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </section>
        </main>
    </div>

                        </div>
                    </section>
                </section>

    <script>
        window.PCP_BOOTSTRAP = <?= json_encode([
            'datasets' => $datasets,
            'sampleProgram' => $datasets['sample_program'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <div id="app-toast" class="app-toast" aria-live="polite" aria-atomic="true"></div>
    <script src="/controlepcp/assets/js/xlsx-import.js?v=3"></script>
    <script src="/controlepcp/assets/js/app.js?v=8"></script>
</body>
</html>



