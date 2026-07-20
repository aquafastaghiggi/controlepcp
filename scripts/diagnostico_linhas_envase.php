<?php

declare(strict_types=1);

/**
 * Diagnóstico read-only: compara o estado (cor/status) de cada linha ativa do
 * Envase entre AcompanharProducao (Livewire, fonte de $linha['cor']) e o que a
 * TV efetivamente RENDERIZA por card (que pode sobrescrever essa cor com
 * Parada Programada/Intervalo/Troca de Kit/Troca de Líquido/Desconexão/ritmo
 * calculados no próprio template) — pra localizar exatamente onde cada linha
 * diverge entre as duas telas.
 *
 * Não persiste nada — só leitura e print.
 *
 * Uso: php scripts/diagnostico_linhas_envase.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$acompanhar = new \App\Livewire\Dashboard\AcompanharProducao();
$acompanhar->carregarDados();

$dashboardPorCodigo = [];
foreach ($acompanhar->linhas as $l) {
    $dashboardPorCodigo[$l['codigo']] = $l;
}

echo str_repeat('=', 110) . PHP_EOL;
echo 'ACOMPANHAR PRODUCAO (Livewire) — fonte $linha[cor]' . PHP_EOL;
echo str_repeat('=', 110) . PHP_EOL;
foreach ($dashboardPorCodigo as $codigo => $l) {
    printf(
        "%-6s | cor=%-8s | estado=%-14s | op_atual=%-10s | realizado=%s | programado=%s\n",
        $codigo,
        $l['cor'],
        $l['estado'],
        $l['op_atual']['numero_op'] ?? '-',
        $l['op_atual']['realizado'] ?? '-',
        $l['op_atual']['programado'] ?? '-'
    );
}

echo PHP_EOL . str_repeat('=', 110) . PHP_EOL;
echo 'TV — HTML RENDERIZADO DE VERDADE (o que aparece pro operador)' . PHP_EOL;
echo str_repeat('=', 110) . PHP_EOL;

$controller = app(\App\Http\Controllers\TvStaticController::class);
$view = $controller->index();
$html = $view->render();

// Cada card: <div class="linha-card {classes...}"> ... <span class="pill-label">TEXTO</span> ...
// <div class="linha-nome">LINHA N</div>
preg_match_all(
    '/<div class="linha-card ([^"]*)">.*?<span class="pill-label">([^<]*)<\/span>.*?<div class="linha-nome">LINHA (\d+)<\/div>/s',
    $html,
    $matches,
    PREG_SET_ORDER
);

$tvPorNumero = [];
foreach ($matches as $m) {
    $classes = trim($m[1]);
    $pill    = trim($m[2]);
    $numero  = $m[3];
    $tvPorNumero[$numero] = ['classes' => $classes, 'pill' => $pill];
    printf("LINHA %-4s | classes=%-60s | pill=%s\n", $numero, $classes, $pill);
}

echo PHP_EOL . str_repeat('=', 110) . PHP_EOL;
echo 'COMPARATIVO' . PHP_EOL;
echo str_repeat('=', 110) . PHP_EOL;

foreach ($dashboardPorCodigo as $codigo => $l) {
    $numero = ltrim(str_replace('LN', '', strtoupper($codigo)), '0');
    if ($numero === '') $numero = '0';

    $tv = $tvPorNumero[$numero] ?? null;

    $corDash = $l['cor'];
    $pillTv  = $tv['pill'] ?? 'AUSENTE NA TV';
    $classesTv = $tv['classes'] ?? '-';

    // Mapear cor do dashboard pro texto esperado no pill, pra facilitar leitura
    $estadoDash = $l['estado'];

    printf(
        "%-6s | dashboard: cor=%-8s estado=%-14s | TV: pill=%-14s classes=%s\n",
        $codigo,
        $corDash,
        $estadoDash,
        $pillTv,
        $classesTv
    );
}
