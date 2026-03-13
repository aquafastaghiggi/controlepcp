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
    <title>PCP Sequencial</title>
    <link rel="stylesheet" href="/controlepcp/assets/css/app.css">
</head>
<body>
    <div class="app-shell">
        <header class="hero">
            <div class="hero-copy">
                <p class="eyebrow">PCP local sem banco</p>
                <h1>Simulador de programacao com setup e calendario util</h1>
                <p class="hero-text">
                    Protótipo pensado para operadores com pouca familiaridade com TI:
                    poucos campos, linguagem clara e resultado em tabela.
                </p>
            </div>
            <div class="hero-note">
                <span class="note-label">Motor ativo</span>
                <strong>Linha 2</strong>
                <span>Calendário, SKU e setup mockados</span>
            </div>
        </header>

        <main class="layout">
            <aside class="sidebar">
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
                        <li>Setup e produção usam o mesmo calendário útil.</li>
                        <li>O primeiro item parte da data/hora base informada.</li>
                        <li>Os próximos itens dependem do fim anterior + setup.</li>
                        <li>Se o setup terminar fora da janela, a produção inicia no próximo horário válido.</li>
                    </ul>
                </section>
            </aside>

            <section class="workspace">
                <section class="panel form-panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Simular programação</h2>
                            <p>Informe o início base e monte a sequência da linha.</p>
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

                        <div class="table-wrap">
                            <table class="entry-table">
                                <thead>
                                    <tr>
                                        <th>Seq.</th>
                                        <th>SKU</th>
                                        <th>Quantidade (cx)</th>
                                        <th>Inicio informado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="program-body"></tbody>
                            </table>
                        </div>

                        <div class="form-actions">
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
                                    <th>SKU anterior</th>
                                    <th>Data início</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Produzido estimado</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="result-body">
                                <tr class="empty-state-row">
                                    <td colspan="12">Nenhuma simulação calculada ainda.</td>
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
            'products' => array_map(
                static fn (array $product, string $sku): array => [
                    'sku' => $sku,
                    'label' => $product['description'],
                    'rate_per_hour' => $product['rate_per_hour'],
                ],
                $mockData['products'],
                array_keys($mockData['products'])
            ),
            'sampleProgram' => $mockData['sample_program'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="/controlepcp/assets/js/app.js"></script>
</body>
</html>
