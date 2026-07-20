<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>TV Dashboard Sopro — Aquafast</title>
    <style>html,body{margin:0;padding:0;overflow:hidden;background:#0d1117;}</style>
</head>
<body>
<div id="tv-wrapper" style="width:100vw;height:100vh;overflow:hidden;position:fixed;top:0;left:0;background:#0d1117;">
<div id="tv-conteudo" style="padding:3px 12px;display:flex;flex-direction:column;gap:4px;background:#0d1117;color:#e6edf3;font-family:'Segoe UI',Arial,sans-serif;width:1920px;height:1080px;overflow:hidden;transform-origin:top left;">

<style>
*{box-sizing:border-box}
.header{display:flex;justify-content:space-between;align-items:center}
.logo{font-size:22px;font-weight:600;letter-spacing:-0.3px;color:#e6edf3}
.logo span{color:#58a6ff}
.header-right{display:flex;align-items:center;gap:20px}
.clock{font-size:28px;font-weight:500;font-variant-numeric:tabular-nums;text-align:right;color:#e6edf3}
.date{font-size:14px;color:#8b949e;text-align:right}
.refresh{display:flex;align-items:center;gap:6px;font-size:10px;color:#8b949e}
.pulse{width:10px;height:10px;border-radius:50%;background:#39d353;animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.kpi-grid{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:14px;width:100%}
.kpi{background:#252d3d;border-radius:12px;padding:5px 12px;border-top:1px solid rgba(255,255,255,0.15);border-right:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08);border-left:1px solid rgba(255,255,255,0.08);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.1)}
.kpi-label{font-size:13px;color:#8b949e;text-transform:uppercase;letter-spacing:.6px;margin-bottom:3px}
.kpi-value{font-size:46px;font-weight:600;line-height:1}
.kpi-sub{font-size:14px;color:#8b949e;margin-top:3px}
.kpi-oee{background:#1e2a1e;border-top:1px solid rgba(255,255,255,0.15);border-right:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08);border-left:1px solid rgba(255,255,255,0.08);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.1)}
.kpi-oee-mini{background:#1a2518;border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:5px 8px;text-align:center;flex:1;box-shadow:0 2px 8px rgba(0,0,0,0.4)}
.kpi-oee-mini .kpi-label-mini{color:#8b949e;font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;}
.kpi-oee-mini .kpi-val-mini{font-size:18px;font-weight:600}

.c-green{color:#39d353}.c-amber{color:#e3b341}.c-red{color:#f85149}.c-blue{color:#58a6ff}.c-muted{color:#8b949e}

.pagina-maquinas{display:flex;flex-direction:column;flex:1;min-height:0;}

.linha-card {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 18px;
    border-radius: 22px;
    overflow: hidden;
    background:radial-gradient(circle at 72% 38%,rgba(92,255,93,.18),transparent 28%),
        linear-gradient(145deg,#182131 0%,#101827 48%,#0c1420 100%);
    border:1px solid rgba(148,163,184,.18);
    box-shadow:0 24px 60px rgba(0,0,0,.42),inset 0 0 0 1px rgba(255,255,255,.04);
    color:#f8fafc;font-family:Inter,"Segoe UI",Arial,sans-serif;
}
.linha-card.verde{border-left:4px solid #39d353;border-top:1px solid rgba(57,211,83,0.25);border-right:1px solid rgba(57,211,83,0.12);border-bottom:1px solid rgba(57,211,83,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(57,211,83,0.12),inset 0 1px 0 rgba(255,255,255,0.1)}
.linha-card.vermelho{border-left:4px solid #f85149;border-top:1px solid rgba(248,81,73,0.25);border-right:1px solid rgba(248,81,73,0.12);border-bottom:1px solid rgba(248,81,73,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(248,81,73,0.15),inset 0 1px 0 rgba(255,255,255,0.1);background:radial-gradient(circle at 72% 38%,rgba(248,81,73,.12),transparent 28%),linear-gradient(145deg,#200e0e 0%,#180808 48%,#120606 100%);}
.linha-card.laranja{border-left:4px solid #f97316;border-top:1px solid rgba(249,115,22,0.25);border-right:1px solid rgba(249,115,22,0.12);border-bottom:1px solid rgba(249,115,22,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(249,115,22,0.15),inset 0 1px 0 rgba(255,255,255,0.1)}
.linha-card.amarelo{border-left:4px solid #e3b341;border-top:1px solid rgba(227,179,65,0.25);border-right:1px solid rgba(227,179,65,0.12);border-bottom:1px solid rgba(227,179,65,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(227,179,65,0.15),inset 0 1px 0 rgba(255,255,255,0.1);background:radial-gradient(circle at 72% 38%,rgba(227,179,65,.12),transparent 28%),linear-gradient(145deg,#1e1a0e 0%,#151205 48%,#111004 100%);}
.linha-card.cinza{border-left:4px solid #475569;border-top:1px solid rgba(255,255,255,0.13);border-right:1px solid rgba(255,255,255,0.07);border-bottom:1px solid rgba(255,255,255,0.07);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.08)}
.linha-card.azul{border-left:4px solid #58a6ff;border-top:1px solid rgba(88,166,255,0.25);border-right:1px solid rgba(88,166,255,0.12);border-bottom:1px solid rgba(88,166,255,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(88,166,255,0.15),inset 0 1px 0 rgba(255,255,255,0.1);background:radial-gradient(circle at 72% 38%,rgba(58,130,246,.12),transparent 28%),linear-gradient(145deg,#0e1520 0%,#071020 48%,#040c18 100%);}
.linha-card::after {
    content:"";position:absolute;right:65px;top:130px;width:360px;height:260px;opacity:.24;
    background-image:radial-gradient(rgba(109,255,94,.55) 1px,transparent 1px);
    background-size:14px 14px;pointer-events:none;
}
/* ==========================================================
   STATUS PILL
   ========================================================== */

.status-pill{
    position:absolute;

    top:8px;
    right:6px;

    z-index:50;

    display:flex;
    align-items:center;
    justify-content:center;
    gap:4px;

    min-width:96px;
    width:auto;
    height:34px;

    padding:0 14px;

    border-radius:999px;

    font-size:14px;
    font-weight:800;

    white-space:nowrap;

    transition:.25s;
}

@keyframes blink-text {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.2; }
}
.status-pill.atrasada .pill-label {
    animation: blink-text 1.2s ease-in-out infinite;
}

.divergencia-icon {
    font-size: 15px;
    line-height: 1;
    color: #ffc800;
}

@keyframes blink-border {
    0%, 100% { box-shadow: 0 0 0 2px rgba(255, 200, 0, 0); }
    50%       { box-shadow: 0 0 0 3px rgba(255, 200, 0, 0.9), 0 0 18px rgba(255, 200, 0, 0.4); }
}
.linha-card.tem-divergencia {
    animation: blink-border 1.2s ease-in-out infinite;
}

@keyframes blink-triangulo {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}
.triangulo-apontamento {
    color: #f5c518 !important;
    font-size: 22px;
    animation: blink-triangulo 1s ease-in-out infinite;
    display: inline-block;
    margin-left: 6px;
    filter: none !important;
}

@keyframes blink-erro {
    0%, 100% { color: inherit; }
    50% { color: #ef4444; }
}
.valor-erro-apontamento {
    animation: blink-erro 1s ease-in-out infinite;
    font-weight: 800;
}

/* ==========================================================
   STATUS ESPECÍFICOS
   ========================================================== */

.status-pill.em-dia{
    background:rgba(40,167,69,.18);
    border:1px solid rgba(90,255,120,.28);
    color:#71ff75;
}

.status-pill.atrasada{
    background:rgba(220,53,69,.16);
    border:1px solid rgba(255,90,90,.28);
    color:#ff5e5e;
}

.status-pill.intervalo{
    background:rgba(13,110,253,.18);
    border:1px solid rgba(90,170,255,.28);
    color:#6db6ff;
}

.status-pill.parada{
    background:rgba(255,193,7,.16);
    border:1px solid rgba(255,210,80,.30);
    color:#f7c948;
}

.status-pill.troca-kit { background:rgba(255,140,0,.18); color:#ff8c00; }
.status-pill.desconexao { background:rgba(30,30,30,.5); color:#c0c0c0; border:1px solid rgba(150,150,150,.3); }
.status-pill.manutencao-pill {
    background: rgba(234, 88, 12, 0.18);
    color: #ea580c;
}
.status-pill.falta-silos-pill {
    background: rgba(234, 179, 8, 0.18);
    color: #ca8a04;
}

.linha-topo {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 105px;
    gap: 6px;
    height: 135px;
    position: relative;
    z-index: 3;
}
.linha-info { min-width: 0; }
/* Fora do .card-content (que é borrado nos estados de parada) — assim o nome
   da máquina continua nítido e visível por cima do overlay, sem duplicar. */
.linha-nome {
    position: relative;
    z-index: 25;
    font-size: clamp(32px, 4.2vw, 48px);
    line-height: .92;
    font-weight: 850;
    white-space: nowrap;
    margin-bottom: 5px;
    color:#f8fafc;text-transform:uppercase;text-shadow:0 6px 22px rgba(0,0,0,.38);
}
.op-info { font-size: 18.75px; margin-bottom: 5px; color:#8b949e; }
.produto {
    font-size: 18px;
    line-height: 1.15;
    font-weight: 800;
    max-width: 195px;
    color:#ffffff;letter-spacing:-0.3px;
}
.produto-coluna {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    justify-self: end;
}
.atraso {
    width: 100%;
    text-align: center;
    font-size: 15px;
    font-weight: 800;
    color: #ff5e5e;
    line-height: 1;
    margin-top: 2px;
    white-space: nowrap;
}
.produto-img-wrap {
    position: relative;
    width: 100px;
    height: 105px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    overflow: visible;
    z-index: 3;
    margin-right: 3px;
}
.produto-img-wrap::before {
    content: "";
    position: absolute;
    left: 50%;
    bottom: 20px;
    transform: translateX(-50%);
    width: 105px;
    height: 105px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(95,255,120,.30) 0%, rgba(95,255,120,.18) 35%, rgba(95,255,120,.08) 55%, transparent 78%);
    filter: blur(8px);
    z-index: 0;
    pointer-events: none;
}
.produto-img-wrap::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 84px;
    height: 17px;
    border-radius: 50%;
    background: rgba(0,0,0,.65);
    border-bottom: 3px solid #55ef6f;
    box-shadow: 0 0 16px rgba(85,239,111,.55);
    z-index: 1;
}
.produto-img {
    position: relative;
    z-index: 2;
    display: block;
    max-height: 97px;
    max-width: 84px;
    width: auto;
    height: auto;
    object-fit: contain;
    object-position: bottom center;
    filter: drop-shadow(0 12px 12px rgba(0,0,0,.35));
}
.produto-img.produto-img-placeholder {
    max-height:35px;max-width:63px;opacity:.18;filter:brightness(0) invert(1);margin-bottom:16px;
}
/* ==========================================================
   EMBALAGENS ALTAS (1,5L)
   ========================================================== */
.produto-img.produto-15l {
    max-height: 89px;
    max-width: 76px;
    object-fit: contain;
    object-position: bottom center;
}
.bloco-inferior {
    position: relative;
    display: flex;
    flex-direction: column;
    margin-top: -30px;
}
.indicadores,
.indicadores-meio {
    order: 99;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 7px;
    margin-top: 7px;
    position: relative;
    z-index: 3;
}
.indicador {
    min-height: 50px;
    padding: 7px 8px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:rgba(31,42,58,.84);border:1px solid rgba(148,163,184,.12);text-align:center;
}
.indicador-label{font-size:14.25px;line-height:1;font-weight:700;color:#a8b3c7;letter-spacing:.7px;}
.indicador-valor { margin-top:4px;font-size:33px;line-height:1;font-weight:850; }
.indicador-valor.verde{color:#39d353;}
.indicador-valor.amarelo{color:#ffc83d;}
.indicador-valor.vermelho{color:#ff514f;}
.indicador-valor.neutro{color:#8b949e;}
.oee-card{background:rgba(16,31,19,.72);border-color:rgba(109,255,93,.18);}
.producao-area {
    margin-top: 4px;
    position: relative;
    z-index: 3;
}
.producao-main {
    display: flex;
    align-items: flex-end;
    gap: 6px;
}
.cx-valor {
    font-size: clamp(28.5px, 3.45vw, 42px);
    line-height: .9;
    font-weight: 400;
    color:#ffffff;letter-spacing:-2px;text-shadow:0 8px 28px rgba(0,0,0,.45);
}
.cx-meta {
    font-size: 21px;
    opacity: .85;
    padding-bottom: 0;
    align-self: flex-end;
    line-height: 1;
    color:#a9b3c5;
}
.cx-meta span{color:#6ee75f;padding:0 4px;}
.barra-wrap {
    margin-top: 5px;
    width: 100%;
    height: 8px;
    border-radius: 999px;
    overflow: hidden;
    background:rgba(148,163,184,.18);
}
.barra { height: 100%; border-radius: inherit; }
.totais-inferiores {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 7px;
    margin-top: 7px;
    position: relative;
    z-index: 3;
}
.total-box {
    min-height: 50px;
    border-radius: 12px;
    padding: 7px 9px 6px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:rgba(15,23,35,.72);border:1px solid rgba(109,255,93,.26);text-align:center;
}
.total-label {
    font-size: 14.25px;
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: .04em;
    text-align: center;
    white-space: normal;
    text-transform: none;
    margin-bottom: 3px;
    color:#8fe874;
}
.total-valor { font-size: 37.5px; font-weight: 850; line-height: .95; margin-bottom: 2px; color:#fff; }
.total-valor.verde{color:#39d353;}
.total-valor.vermelho{color:#f85149;}
.total-valor.neutro{color:#8b949e;}
.total-meta { font-size: 18px; line-height: 1; opacity: .72; color:#a8b3c7; }
.card-content { position:relative; z-index:1; flex:1; display:flex; flex-direction:column; }
.em-pausa .card-content, .em-intervalo .card-content,
.em-troca-kit .card-content, .em-troca-liquido .card-content, .em-desconexao .card-content,
.em-manutencao .card-content, .em-falta-silos .card-content, .em-micro-parada .card-content {
    filter:blur(4px);opacity:.35;pointer-events:none;
}
.status-overlay {
    position:absolute;inset:0;z-index:20;display:flex;align-items:center;
    justify-content:center;flex-direction:column;pointer-events:none;border-radius:22px;
}
.status-overlay::before {
    content:"";position:absolute;inset:0;background:rgba(3,8,18,.45);z-index:-1;border-radius:22px;
}

@keyframes border-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(249,115,22,0.4); }
    50%       { box-shadow: 0 0 0 6px rgba(249,115,22,0); }
}
@keyframes border-pulse-red {
    0%, 100% { box-shadow: 0 0 0 0 rgba(248,81,73,0.4); }
    50%       { box-shadow: 0 0 0 6px rgba(248,81,73,0); }
}
.linha-card.pulsing-orange { animation: border-pulse 2s ease-in-out infinite; }
.linha-card.pulsing-red    { animation: border-pulse-red 2s ease-in-out infinite; }

@keyframes blink-manutencao {
    0%, 100% { box-shadow: 0 0 0 2px rgba(234, 88, 12, 0); }
    50% { box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.9), 0 0 20px rgba(234, 88, 12, 0.35); }
}
.linha-card.em-manutencao {
    animation: blink-manutencao 1.5s ease-in-out infinite;
}

@keyframes blink-falta-silos {
    0%, 100% { box-shadow: 0 0 0 2px rgba(234, 179, 8, 0); }
    50% { box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.9), 0 0 20px rgba(234, 179, 8, 0.35); }
}
.linha-card.em-falta-silos {
    animation: blink-falta-silos 1.5s ease-in-out infinite;
}

</style>

{{-- Header --}}
<div class="header">
    <img src="{{ asset('images/aquafast-logo.svg') }}"
         alt="Aquafast"
         style="height:46px;width:auto;filter:brightness(0) invert(1);">
    <div class="header-right">
        <div class="refresh">
            <div class="pulse"></div>
            <span>Atualiza a cada 60s</span>
        </div>
        <div>
            <div class="clock" id="tv-clock">{{ now()->format('H:i:s') }}</div>
            <div class="date">{{ now()->isoFormat('dddd, D [de] MMM [de] Y') }}</div>
        </div>
    </div>
</div>

@php
    // Contadores do topo (Situação) usando a MESMA lógica do badge de cada card —
    // não a cor do AcompanharProducaoSopro/CODI, que pode divergir do card.
    // Janela do dia Sopro: 06:30 → 06:30 do dia seguinte (24h corridas).
    $totalEmDia     = 0;
    $totalAtrasadas = 0;
    $totalAlertas   = 0;

    foreach ($maquinas as $maquinaContagem) {
        $codigoRecursoContagem = $maquinaContagem['codigo_recurso'] ?? null;

        $inicioDia6h30Contagem = \Carbon\Carbon::today()->setHour(6)->setMinute(30)->setSecond(0);
        if (\Carbon\Carbon::now()->lt($inicioDia6h30Contagem)) {
            $inicioDia6h30Contagem = $inicioDia6h30Contagem->copy()->subDay();
        }

        $prodMaquinaContagem = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecursoContagem)
            ->where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioDia6h30Contagem)
            ->sum('quantidade');

        $calendarioIdContagem = \Illuminate\Support\Facades\DB::table('calendarios_sopro')
            ->where('maquina_id', $maquinaContagem['id'])
            ->value('id');

        $progMaquinaContagem = \Illuminate\Support\Facades\DB::table('programacoes_sopro')
            ->where('maquina_id', $maquinaContagem['id'])
            ->where('status', 'confirmada')
            ->first(['dias_selecionados']);

        $atrasadoContagem = false;

        if ($prodMaquinaContagem > 0 && $calendarioIdContagem && $progMaquinaContagem) {
            try {
                $minTrabalhadosContagem = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                    ->where('codigo_recurso', $codigoRecursoContagem)
                    ->where('inicio_evento', '>=', $inicioDia6h30Contagem)
                    ->sum('duracao_minutos');

                $horasTrabalhadasContagem = max(0.1, $minTrabalhadosContagem / 60);
                $ritmoMaquinaContagem     = $prodMaquinaContagem / $horasTrabalhadasContagem;

                $diasSelecionadosContagem = json_decode($progMaquinaContagem->dias_selecionados ?? '[]', true);

                $hoje630Contagem = new \DateTimeImmutable($inicioDia6h30Contagem->format('Y-m-d H:i:s'));
                $fimJanContagem  = new \DateTimeImmutable($inicioDia6h30Contagem->copy()->addDay()->format('Y-m-d H:i:s'));

                // Turnos overnight (ex.: T3 21:30→06:30, cruza a meia-noite) precisam de
                // override explícito pra amanhã, senão o CalendarioSoproService pode
                // truncar a parte 00:00→06:30 do turno. Turnos ADM nunca entram aqui —
                // são configuração residual copiada do Envase.
                $diasSelComOvernightContagem = $diasSelecionadosContagem;
                $turnosHojeContagem          = $diasSelecionadosContagem[$hoje630Contagem->format('Y-m-d')]['turnos'] ?? [];

                if (! empty($turnosHojeContagem)) {
                    $turnosOvernightHojeContagem = \Illuminate\Support\Facades\DB::table('intervalos_sopro')
                        ->whereIn('id', $turnosHojeContagem)
                        ->where('nome', 'not like', '%ADM%')
                        ->whereColumn('hora_fim', '<=', 'hora_inicio')
                        ->pluck('id')
                        ->toArray();

                    $amanhaStrContagem = $fimJanContagem->format('Y-m-d');

                    if (! empty($turnosOvernightHojeContagem) && ! isset($diasSelComOvernightContagem[$amanhaStrContagem])) {
                        $diasSelComOvernightContagem[$amanhaStrContagem] = [
                            'dia_semana' => (int) $fimJanContagem->format('N'),
                            'turnos'     => $turnosOvernightHojeContagem,
                        ];
                    }
                }

                $calendarioServiceContagem = app(\App\Services\CalendarioSoproService::class);

                $minJornadaContagem        = $calendarioServiceContagem->minutosUteisEntre($hoje630Contagem, $fimJanContagem, $calendarioIdContagem, $diasSelComOvernightContagem);
                $capacidadeTeoricaContagem = $ritmoMaquinaContagem * ($minJornadaContagem / 60);

                $opsMaquinaHojeContagem = \Illuminate\Support\Facades\DB::table('itens_programacao_sopro as ip')
                    ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
                    ->leftJoin('codi_eficiencia_sopro as ce', function ($j) {
                        $j->on('ce.numero_op', '=', 'ip.numero_op')
                          ->on('ce.programacao_sopro_id', '=', 'p.id');
                    })
                    ->leftJoin('frascos as frs', \Illuminate\Support\Facades\DB::raw('frs.sku COLLATE utf8mb4_unicode_ci'), '=', 'ip.sku')
                    ->where('p.maquina_id', $maquinaContagem['id'])
                    ->where('p.status', 'confirmada')
                    ->whereNotNull('ce.inicio_previsto')
                    ->whereNotNull('ce.fim_previsto')
                    ->where('ce.fim_previsto', '>', $hoje630Contagem->format('Y-m-d H:i:s'))
                    ->where('ce.inicio_previsto', '<', $fimJanContagem->format('Y-m-d H:i:s'))
                    ->get(['ip.numero_op', 'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto', 'p.eficiencia', 'frs.taxa_por_hora']);

                $somaOpsHojeContagem = 0.0;
                foreach ($opsMaquinaHojeContagem as $opRowContagem) {
                    $inicioOp      = new \DateTimeImmutable($opRowContagem->inicio_previsto);
                    $fimOp         = new \DateTimeImmutable($opRowContagem->fim_previsto);
                    $inicioOverlap = $inicioOp < $hoje630Contagem ? $hoje630Contagem : $inicioOp;
                    $fimOverlap    = $fimOp > $fimJanContagem ? $fimJanContagem : $fimOp;

                    if ($fimOverlap <= $inicioOverlap) continue;

                    $minTotal   = $calendarioServiceContagem->minutosUteisEntre($inicioOp, $fimOp, $calendarioIdContagem, $diasSelecionadosContagem);
                    $minOverlap = $calendarioServiceContagem->minutosUteisEntre($inicioOverlap, $fimOverlap, $calendarioIdContagem, $diasSelecionadosContagem);

                    if ($minTotal <= 0) continue;

                    // itens_programacao_sopro.quantidade vem em milheiros — ×1000.
                    $taxaPorHoraContagem = (float) ($opRowContagem->taxa_por_hora ?? 0);
                    $eficienciaContagem  = (float) ($opRowContagem->eficiencia ?? 100) / 100;
                    $quantidadeRealContagem = (float) $opRowContagem->quantidade * 1000;
                    if ($taxaPorHoraContagem > 0) {
                        $prevCxOpContagem = min((int) $quantidadeRealContagem, (int) round($taxaPorHoraContagem * $eficienciaContagem * $minOverlap / 60));
                    } else {
                        $prevCxOpContagem = (int) round($quantidadeRealContagem * ($minOverlap / $minTotal));
                    }
                    $somaOpsHojeContagem += $prevCxOpContagem;
                }

                // Máquina reprogramada hoje: soma o que já foi produzido das OPs da
                // programação arquivada (ver mesma lógica em TvStaticSoproController).
                $numeroOpsConfirmadosContagem = $opsMaquinaHojeContagem->pluck('numero_op')->all();
                $itensArquivadosContagem = \Illuminate\Support\Facades\DB::table('itens_programacao_sopro as ip')
                    ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
                    ->where('p.maquina_id', $maquinaContagem['id'])
                    ->where('p.status', 'arquivada')
                    ->where('p.arquivada_em', '>=', $inicioDia6h30Contagem)
                    ->pluck('ip.numero_op')
                    ->unique();

                foreach ($itensArquivadosContagem as $numeroOpArquivadaContagem) {
                    if (in_array($numeroOpArquivadaContagem, $numeroOpsConfirmadosContagem, true)) continue;

                    $somaOpsHojeContagem += (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                        ->where('codigo_recurso', $codigoRecursoContagem)
                        ->where('ordem_producao', $numeroOpArquivadaContagem)
                        ->where('tipo_evento', 'PRODUCAO')
                        ->where('inicio_evento', '>=', $inicioDia6h30Contagem)
                        ->sum('quantidade');
                }

                if ($somaOpsHojeContagem > 0) {
                    $prevXRealValContagem = (int) round($capacidadeTeoricaContagem - $somaOpsHojeContagem);
                    $atrasadoContagem     = $prevXRealValContagem < 0;
                }
            } catch (\Throwable $e) {
                $atrasadoContagem = false;
            }
        }

        // Parada Programada / Intervalo têm badge próprio no card — não contam
        // como Atrasada nem como Em dia no resumo do topo
        $ultimoEventoContagem = \Illuminate\Support\Facades\DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecursoContagem)
            ->orderByDesc('inicio_evento')
            ->first(['tipo_evento', 'dados_raw', 'fim_evento', 'inicio_evento']);

        $ehParadaProgramadaContagem = false;
        $ehIntervaloContagem        = false;
        $ehTrocaCorContagem         = false;
        $ehTrocaMoldeContagem       = false;
        $ehManutencaoContagem       = false;
        $ehFaltaSilosContagem       = false;
        $ehMicroParadaContagem      = false;

        // Desconexão automática: sem evento (qualquer tipo) nos últimos 15 min —
        // mesma lógica do card (_sopro-maquina-card.blade.php), senão máquinas
        // desconectadas ficavam contando erroneamente como Em dia/Atrasada aqui.
        $ultimoEventoTsContagem = $ultimoEventoContagem
            ? \Carbon\Carbon::parse($ultimoEventoContagem->fim_evento ?? $ultimoEventoContagem->inicio_evento)
            : null;
        $semSinalHa15minContagem = $ultimoEventoTsContagem === null
            || $ultimoEventoTsContagem->diffInMinutes(now()) >= 15;
        $ultimoEventoAbertoContagem = $ultimoEventoContagem
            && $ultimoEventoContagem->tipo_evento === 'PRODUCAO'
            && $ultimoEventoContagem->fim_evento === null;
        $ehDesconexaoContagem = $semSinalHa15minContagem && !$ultimoEventoAbertoContagem;

        if ($ultimoEventoContagem && $ultimoEventoContagem->tipo_evento === 'PARADA') {
            $rawContagem = is_array($ultimoEventoContagem->dados_raw)
                ? $ultimoEventoContagem->dados_raw
                : json_decode($ultimoEventoContagem->dados_raw, true);
            $nomeParadaContagem = $rawContagem['parada']['nomeParada'] ?? '';
            $tipoParadaContagem = $rawContagem['parada']['tipoParada']['nomeTipoParada'] ?? '';
            $ehParadaProgramadaContagem = stripos($nomeParadaContagem, 'PARADA PROGRAMADA') !== false;
            $ehIntervaloContagem        = stripos($tipoParadaContagem, 'Intervalo') !== false;
            $ehTrocaCorContagem   = stripos($nomeParadaContagem ?? '', 'TROCA DE COR') !== false;
            $ehTrocaMoldeContagem = stripos($nomeParadaContagem ?? '', 'TROCA DE MOLDE') !== false;
            $ehDesconexaoContagem = $ehDesconexaoContagem || stripos($nomeParadaContagem ?? '', 'DESCONEX') !== false;

            // Paradas longas (Manutenção/Falta de Silos > 10 min, Micro Parada
            // > 15 min) — mesma lógica do card, exclui do resumo Em dia/Atrasada.
            if (!$ehParadaProgramadaContagem && !$ehIntervaloContagem && !$ehTrocaCorContagem && !$ehTrocaMoldeContagem && !$ehDesconexaoContagem) {
                $nomeParadaUpperContagem = strtoupper($nomeParadaContagem ?? '');
                $duracaoMinContagem = \Carbon\Carbon::parse($ultimoEventoContagem->inicio_evento)->diffInMinutes(now());

                if ($duracaoMinContagem >= 10) {
                    $ehManutencaoContagem = str_contains($nomeParadaUpperContagem, 'MANUTENCAO') || str_contains($nomeParadaUpperContagem, 'MANUTENÇÃO');
                    $ehFaltaSilosContagem = str_contains($nomeParadaUpperContagem, 'FALTA DE SILOS') || str_contains($nomeParadaUpperContagem, 'SILO');
                }

                if ($duracaoMinContagem >= 15) {
                    $ehMicroParadaContagem = str_contains($nomeParadaUpperContagem, 'MICRO_PARADA') || str_contains($nomeParadaUpperContagem, 'MICRO PARADA');
                }
            }
        }

        // Mesma cadeia de prioridade do badge do card: Parada/Intervalo/Troca de
        // Cor/Troca de Molde/Desconexão/Manutenção/Falta de Silos/Micro Parada não
        // contam; Atrasada = ritmo (Prev. x Real. < 0) OU cor do
        // AcompanharProducaoSopro = 'red'; qualquer outro caso conta como Em dia.
        if (!$ehParadaProgramadaContagem && !$ehIntervaloContagem && !$ehTrocaCorContagem && !$ehTrocaMoldeContagem && !$ehDesconexaoContagem && !$ehManutencaoContagem && !$ehFaltaSilosContagem && !$ehMicroParadaContagem) {
            if ($atrasadoContagem || ($maquinaContagem['cor'] ?? null) === 'red') {
                $totalAtrasadas++;
                $totalAlertas++;
            } else {
                $totalEmDia++;
            }
        }
    }
@endphp

{{-- KPIs --}}
<div class="kpi-grid">
    {{-- 1. Situação --}}
    <div class="kpi">
        <div class="kpi-label">Situação</div>
        <div style="display:flex;gap:16px;margin-top:4px">
            <div style="display:flex;flex-direction:column;align-items:flex-start">
                <span class="kpi-value c-green">{{ $totalEmDia }}</span>
                <span class="kpi-sub">Em dia</span>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-start">
                <span class="kpi-value c-red">{{ $totalAtrasadas }}</span>
                <span class="kpi-sub">Atrasadas</span>
            </div>
        </div>
    </div>
    {{-- 3. Produção do dia --}}
    <div class="kpi">
        <div class="kpi-label">Produção do dia</div>
        <div class="kpi-value c-green">{{ number_format($kpis['produzido_hoje'] ?? 0, 0, ',', '.') }}</div>
        <div class="kpi-sub">de {{ number_format($kpis['previsto_hoje'] ?? 0, 0, ',', '.') }} FR · {{ number_format($kpis['pct_hoje'] ?? 0, 1, ',', '.') }}%</div>
    </div>
    {{-- 4. Previsão de hoje --}}
    <div class="kpi">
        <div class="kpi-label">Previsão de hoje</div>
        @if(($kpis['previsto_hoje'] ?? 0) == 0)
            <div class="kpi-value">-</div>
        @else
            <div class="kpi-value {{ ($kpis['pct_proj'] ?? 0) >= 90 ? 'c-green' : (($kpis['pct_proj'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
                {{ number_format($kpis['projecao'] ?? 0, 0, ',', '.') }}
            </div>
            <div class="kpi-sub">
                {{ number_format($kpis['pct_proj'] ?? 0, 1, ',', '.') }}% de {{ number_format($kpis['previsto_hoje'] ?? 0, 0, ',', '.') }} FR
            </div>
        @endif
    </div>
    {{-- Total programado --}}
    <div class="kpi">
        <div class="kpi-label">Total programado</div>
        <div class="kpi-value c-blue">
            @if(($totalProgramado ?? 0) == 0)
                -
            @else
                {{ number_format($totalProgramado, 0, ',', '.') }}
            @endif
        </div>
        <div class="kpi-sub">Programação PCP</div>
    </div>
    {{-- 5. Diferença --}}
    <div class="kpi">
        <div class="kpi-label">Diferença Tot. Prev.</div>
        @php $dif = $kpis['diferenca'] ?? 0; @endphp
        @if($dif == 0)
            <div class="kpi-value">-</div>
        @else
            <div class="kpi-value {{ $dif >= 0 ? 'c-green' : 'c-red' }}">
                {{ $dif >= 0 ? '+' : '' }}{{ number_format($dif, 0, ',', '.') }}
            </div>
            <div class="kpi-sub">FR hoje</div>
        @endif
    </div>
    {{-- 6. Disponibilidade --}}
    <div class="kpi">
        <div class="kpi-label">Disponibilidade</div>
        <div class="kpi-value {{ ($kpis['disp_media'] ?? 0) >= 85 ? 'c-green' : (($kpis['disp_media'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
            {{ isset($kpis['disp_media']) ? number_format($kpis['disp_media'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
    {{-- 7. Performance --}}
    <div class="kpi">
        <div class="kpi-label">Performance</div>
        <div class="kpi-value {{ ($kpis['perf_media'] ?? 0) >= 85 ? 'c-green' : (($kpis['perf_media'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
            {{ isset($kpis['perf_media']) ? number_format($kpis['perf_media'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
    {{-- 8. OEE Médio --}}
    <div class="kpi kpi-oee">
        <div class="kpi-label">OEE Médio</div>
        <div class="kpi-value {{ ($kpis['oee_medio'] ?? 0) >= 75 ? 'c-green' : (($kpis['oee_medio'] ?? 0) >= 60 ? 'c-amber' : 'c-red') }}">
            {{ isset($kpis['oee_medio']) ? number_format($kpis['oee_medio'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
</div>

@php
    $maquinasPag1 = array_slice($maquinas, 0, 6);
    $maquinasPag2 = array_slice($maquinas, 6, 4); // MAQ07-MAQ10
@endphp

{{-- Indicador de página --}}
<div style="display:flex; justify-content:center; gap:8px; margin-bottom:8px;">
    <div id="dot-1" style="width:10px;height:10px;border-radius:50%;background:#4ade80;transition:background 0.3s;"></div>
    <div id="dot-2" style="width:10px;height:10px;border-radius:50%;background:#374151;transition:background 0.3s;"></div>
</div>

{{-- Página 1: MAQ01 a MAQ06 --}}
<div id="pagina-1" class="pagina-maquinas">
    <div style="display:grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(2, 1fr); gap: 12px; flex:1;">
        @forelse($maquinasPag1 as $maquina)
            @include('tv._sopro-maquina-card')
        @empty
            <div style="color:#8b949e;grid-column:1/-1;text-align:center;padding:40px;">Nenhuma máquina ativa</div>
        @endforelse
    </div>
</div>

{{-- Página 2: MAQ07 a MAQ10 + placeholder MAQ11 --}}
<div id="pagina-2" class="pagina-maquinas" style="display:none;">
    <div style="display:grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(2, 1fr); gap: 12px; flex:1;">
        @foreach($maquinasPag2 as $maquina)
            @include('tv._sopro-maquina-card')
        @endforeach
        <div class="maquina-card" style="opacity:0.3; border: 2px dashed #555; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:8px;">
            <div style="font-size:18px; color:#888;">MAQ11</div>
            <div style="font-size:13px; color:#666;">Em breve</div>
        </div>
    </div>
</div>

<script>
function pad(n){return String(n).padStart(2,'0');}
function tick(){
    var now=new Date();
    var el=document.getElementById('tv-clock');
    if(el) el.textContent=pad(now.getHours())+':'+pad(now.getMinutes())+':'+pad(now.getSeconds());
}
tick();setInterval(tick,1000);

function ajustarEscala(){
    var wrapper = document.getElementById('tv-wrapper');
    var conteudo = document.getElementById('tv-conteudo');
    if (!wrapper || !conteudo) return;

    conteudo.style.transform = 'scale(1)';

    var scaleX = window.innerWidth / conteudo.scrollWidth;
    var scaleY = window.innerHeight / conteudo.scrollHeight;
    var scale = Math.min(scaleX, scaleY);

    conteudo.style.transform = 'scale(' + scale + ')';

    // Se a proporção da tela não bater exatamente com o conteúdo, distribui a
    // folga igualmente nas duas bordas em vez de deixar tudo sobrando de um lado só.
    var folgaX = Math.max(0, window.innerWidth - (conteudo.scrollWidth * scale));
    var folgaY = Math.max(0, window.innerHeight - (conteudo.scrollHeight * scale));
    conteudo.style.marginLeft = (folgaX / 2) + 'px';
    conteudo.style.marginTop = (folgaY / 2) + 'px';
}

window.addEventListener('resize', ajustarEscala);
window.addEventListener('load', ajustarEscala);
document.addEventListener('fullscreenchange', ajustarEscala);
ajustarEscala();

// Cronômetros de troca de cor/molde
(function() {
    function atualizarCronometros() {
        document.querySelectorAll('.cronometro-troca').forEach(function(el) {
            var min = parseInt(el.getAttribute('data-minutos')) + 1;
            el.setAttribute('data-minutos', min);
            var hh = String(Math.floor(min / 60)).padStart(2, '0');
            var mm = String(min % 60).padStart(2, '0');
            el.textContent = hh + ':' + mm;
        });
    }
    setInterval(atualizarCronometros, 60000);
})();

// Alternância automática entre as duas páginas de máquinas (MAQ01-06 / MAQ07-11)
const INTERVALO_PAGINA = 30000;
let paginaAtual = 1;

function alternarPagina() {
    const p1 = document.getElementById('pagina-1');
    const p2 = document.getElementById('pagina-2');
    const d1 = document.getElementById('dot-1');
    const d2 = document.getElementById('dot-2');

    if (paginaAtual === 1) {
        p1.style.display = 'none';
        p2.style.display = 'flex';
        d1.style.background = '#374151';
        d2.style.background = '#4ade80';
        paginaAtual = 2;
    } else {
        p2.style.display = 'none';
        p1.style.display = 'flex';
        d1.style.background = '#4ade80';
        d2.style.background = '#374151';
        paginaAtual = 1;
    }

    // O conteúdo visível muda de altura entre as páginas (ex.: card placeholder
    // MAQ11 na página 2) — reajusta a escala pra não sobrar/faltar espaço.
    ajustarEscala();
}

setInterval(alternarPagina, INTERVALO_PAGINA);
</script>
</div>
</div>
</body>
</html>
