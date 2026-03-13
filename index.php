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
                <nav class="top-nav" aria-label="Navegação principal">
                    <details class="nav-group">
                        <summary>Cadastros</summary>
                        <div class="nav-menu">
                            <button type="button" class="nav-link" data-target="section-calendar">Horários de Trabalho</button>
                            <button type="button" class="nav-link" data-target="section-products">SKU (Produtos)</button>
                            <button type="button" class="nav-link" data-target="section-matrix">Matrizes</button>
                            <button type="button" class="nav-link" data-target="section-program">Programação de PCP</button>
                        </div>
                    </details>
                    <button type="button" class="nav-shortcut" data-target="section-program">Programação de PCP</button>
                </nav>
            </div>
            <div class="hero-note is-hidden">
                <span class="note-label">Motor ativo</span>
                <strong>Linha 2</strong>
                <span>Calendario, SKU e setup mockados</span>
            </div>
        </header>

        <main class="layout">
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

                <section class="panel">
                    <h2>Produtos da linha 2</h2>
                    <div class="product-list">
                        <?php foreach ($mockData['products'] as $sku => $product): ?>
                            <div class="product-pill">
                                <span><?= htmlspecialchars($sku) ?></span>
                                <strong><?= (int) $product['rate_per_hour'] ?> cx/h</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="panel">
                    <h2>Regras deste MVP</h2>
                    <ul class="info-list">
                        <li>Setup e producao usam o mesmo calendario util.</li>
                        <li>O primeiro item parte da data/hora base informada.</li>
                        <li>Os proximos itens dependem do fim anterior + setup.</li>
                        <li>Se o setup terminar fora da janela, a producao inicia no proximo horario valido.</li>
                    </ul>
                </section>
            </aside>

            <section class="workspace">
                <section class="panel app-section" id="section-calendar">
                    <div class="panel-heading">
                        <div>
                            <h2>Horários de Trabalho</h2>
                            <p>Cadastre os intervalos válidos, os dias úteis e os feriados usados no cálculo.</p>
                        </div>
                        <button type="button" id="add-interval" class="ghost-button">Adicionar intervalo</button>
                    </div>

                    <div class="field-grid calendar-grid">
                        <label class="field">
                            <span>Dias úteis</span>
                            <div class="weekday-group" id="weekday-group"></div>
                        </label>

                        <label class="field">
                            <span>Feriados</span>
                            <textarea id="holiday-input" rows="4" placeholder="Um por linha, no formato AAAA-MM-DD"></textarea>
                        </label>
                    </div>

                    <div class="table-wrap compact-wrap">
                        <table class="entry-table">
                            <thead>
                                <tr>
                                    <th>Ordem</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="calendar-body"></tbody>
                        </table>
                    </div>
                </section>

                <section class="panel app-section" id="section-products">
                    <div class="panel-heading">
                        <div>
                            <h2>SKU (Produtos)</h2>
                            <p>Use a base da L2 para manter descrição, linha, produção por hora e unidade.</p>
                        </div>
                        <button type="button" id="add-product" class="ghost-button">Adicionar SKU</button>
                    </div>

                    <div class="table-wrap compact-wrap">
                        <table class="entry-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Descrição</th>
                                    <th>Linha</th>
                                    <th>Produção/h</th>
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
                        <button type="button" id="add-matrix-row" class="ghost-button">Adicionar setup</button>
                    </div>

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

                <section class="panel app-section is-active" id="section-program">
                    <div class="panel-heading">
                        <div>
                            <h2>Programação de PCP</h2>
                            <p>Informe o início base, preencha os itens e deixe as próximas datas por conta do cálculo.</p>
                        </div>
                        <button type="button" id="add-row" class="ghost-button">Adicionar item</button>
                    </div>

                    <form id="simulation-form">
                        <div class="field-grid">
                            <label class="field">
                                <span>Data/hora base</span>
                                <input type="datetime-local" name="base_start" value="2026-04-14T13:35" required>
                            </label>

                            <label class="field">
                                <span>Consulta para produzido estimado</span>
                                <input type="datetime-local" name="query_datetime" value="2026-04-15T00:00">
                            </label>
                        </div>

                        <div class="table-wrap entry-table-wrap">
                            <table class="entry-table">
                                <thead>
                                    <tr>
                                        <th>Seq.</th>
                                        <th>SKU</th>
                                        <th>Quantidade (cx)</th>
                                        <th>Início informado (1º item)</th>
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
                </section>

                <section class="panel result-panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Resultado</h2>
                            <p>A tabela abaixo mostra produção e setup na ordem real de execução.</p>
                        </div>
                        <span class="status-badge" id="result-status">Aguardando cálculo</span>
                    </div>

                    <div id="result-summary" class="summary-grid"></div>

                    <div class="table-wrap">
                        <table class="result-table">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Seq.</th>
                                    <th>Produto</th>
                                    <th>Produção/h</th>
                                    <th>Programado</th>
                                    <th>Tempo</th>
                                    <th>Data início</th>
                                    <th>Início</th>
                                    <th class="is-hidden-column">Memória do cálculo</th>
                                    <th>Fim</th>
                                </tr>
                            </thead>
                            <tbody id="result-body">
                                <tr class="empty-state-row">
                                    <td colspan="10">Nenhuma simulação calculada ainda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
    <script src="/controlepcp/assets/js/app.js"></script>
</body>
</html>
