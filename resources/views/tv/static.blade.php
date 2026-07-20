<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>TV Dashboard — Aquafast</title>
    <style>html,body{margin:0;padding:0;overflow:hidden;background:#0d1117;}</style>
</head>
<body>
<div id="tv-wrapper" style="width:100vw;height:100vh;overflow:hidden;position:fixed;top:0;left:0;background:#0d1117;">
<div id="tv-conteudo" style="padding:8px 12px;display:flex;flex-direction:column;gap:8px;background:#0d1117;color:#e6edf3;font-family:'Segoe UI',Arial,sans-serif;width:1920px;transform-origin:top left;">

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

.kpi-grid{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:20px;width:100%}
.kpi{background:#252d3d;border-radius:12px;padding:10px 14px;border-top:1px solid rgba(255,255,255,0.15);border-right:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08);border-left:1px solid rgba(255,255,255,0.08);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.1)}
.kpi-label{font-size:16px;color:#8b949e;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px}
.kpi-value{font-size:39px;font-weight:600;line-height:1}
.kpi-sub{font-size:18px;color:#8b949e;margin-top:4px}
.kpi-oee{background:#1e2a1e;border-top:1px solid rgba(255,255,255,0.15);border-right:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08);border-left:1px solid rgba(255,255,255,0.08);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.1)}
.kpi-oee-mini{background:#1a2518;border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:5px 8px;text-align:center;flex:1;box-shadow:0 2px 8px rgba(0,0,0,0.4)}
.kpi-oee-mini .kpi-label-mini{color:#8b949e;font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;}
.kpi-oee-mini .kpi-val-mini{font-size:18px;font-weight:600}

.c-green{color:#39d353}.c-amber{color:#e3b341}.c-red{color:#f85149}.c-blue{color:#58a6ff}.c-muted{color:#8b949e}

.linhas-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;overflow:hidden;align-content:start;}

.linha-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 370px;
    padding: 20px 16px 14px 18px;
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

.linha-topo {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 145px;
    gap: 8px;
    height: 178px;
    position: relative;
    z-index: 3;
}
.linha-info { min-width: 0; }
.linha-nome {
    font-size: clamp(42px, 5vw, 58px);
    line-height: .92;
    font-weight: 850;
    white-space: nowrap;
    margin-bottom: 8px;
    color:#f8fafc;text-transform:uppercase;text-shadow:0 6px 22px rgba(0,0,0,.38);
}
.op-info { font-size: 16px; margin-bottom: 8px; color:#8b949e; }
.produto {
    font-size: 15.5px;
    line-height: 1.18;
    font-weight: 800;
    max-width: 260px;
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
    width: 145px;
    height: 150px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    overflow: visible;
    z-index: 3;
    margin-right: 4px;
}
.produto-img-wrap::before {
    content: "";
    position: absolute;
    left: 50%;
    bottom: 28px;
    transform: translateX(-50%);
    width: 150px;
    height: 150px;
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
    width: 120px;
    height: 24px;
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
    max-height: 140px;
    max-width: 120px;
    width: auto;
    height: auto;
    object-fit: contain;
    object-position: bottom center;
    filter: drop-shadow(0 12px 12px rgba(0,0,0,.35));
}
.produto-img.produto-img-placeholder {
    max-height:50px;max-width:90px;opacity:.18;filter:brightness(0) invert(1);margin-bottom:24px;
}
/* ==========================================================
   EMBALAGENS ALTAS (1,5L)
   ========================================================== */
.produto-img.produto-15l {
    max-height: 128px;
    max-width: 108px;
    object-fit: contain;
    object-position: bottom center;
}
.bloco-inferior {
    position: relative;
    display: flex;
    flex-direction: column;
    margin-top: -27.03px;
}
.indicadores,
.indicadores-meio {
    order: 99;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 9px;
    margin-top: 10px;
    position: relative;
    z-index: 3;
}
.indicador {
    min-height: 66px;
    padding: 10px 11px;
    border-radius: 15px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:rgba(31,42,58,.84);border:1px solid rgba(148,163,184,.12);text-align:center;
}
.indicador-label{font-size:12.5px;line-height:1;font-weight:700;color:#a8b3c7;letter-spacing:1px;}
.indicador-valor { margin-top:6px;font-size:25px;line-height:1;font-weight:850; }
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
    font-size: clamp(22px, 2.6vw, 31px);
    line-height: .9;
    font-weight: 400;
    color:#ffffff;letter-spacing:-2px;text-shadow:0 8px 28px rgba(0,0,0,.45);
}
.cx-meta {
    font-size: 20px;
    opacity: .85;
    padding-bottom: 0;
    align-self: flex-end;
    line-height: 1;
    color:#a9b3c5;
}
.cx-meta span{color:#6ee75f;padding:0 4px;}
.barra-wrap {
    margin-top: 6px;
    width: 100%;
    height: 10px;
    border-radius: 999px;
    overflow: hidden;
    background:rgba(148,163,184,.18);
}
.barra { height: 100%; border-radius: inherit; }
.totais-inferiores {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 9px;
    margin-top: 10px;
    position: relative;
    z-index: 3;
}
.total-box {
    min-height: 66px;
    border-radius: 15px;
    padding: 9px 11px 8px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:rgba(15,23,35,.72);border:1px solid rgba(109,255,93,.26);text-align:center;
}
.total-label {
    font-size: 11px;
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: .04em;
    text-align: center;
    white-space: normal;
    text-transform: none;
    margin-bottom: 4px;
    color:#8fe874;
}
.total-valor { font-size: 28px; font-weight: 850; line-height: .95; margin-bottom: 2px; color:#fff; }
.total-valor.verde{color:#39d353;}
.total-valor.vermelho{color:#f85149;}
.total-valor.neutro{color:#8b949e;}
.total-meta { font-size: 12px; line-height: 1; opacity: .72; color:#a8b3c7; }
.card-content { position:relative; z-index:1; flex:1; display:flex; flex-direction:column; }
.em-pausa .card-content, .em-intervalo .card-content,
.em-troca-kit .card-content, .em-troca-liquido .card-content, .em-desconexao .card-content {
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

/* ==========================================================
   CARD DE RESUMO — PROGRAMAÇÃO DE PRODUÇÃO
   ========================================================== */
.card-programacao-producao {
    width: 100%;
    height: 100%;
    min-height: 360px;
    background:
        radial-gradient(circle at top right, rgba(42, 255, 108, 0.12), transparent 35%),
        linear-gradient(145deg, #0d1522 0%, #101827 48%, #07101b 100%);
    border: 1px solid rgba(63, 255, 113, 0.55);
    border-radius: 18px;
    padding: 12px 14px;
    color: #ffffff;
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.03) inset,
        0 18px 40px rgba(0, 0, 0, 0.45),
        0 0 28px rgba(42, 255, 108, 0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.programacao-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.programacao-title-area {
    display: flex;
    align-items: center;
    gap: 10px;
}
.programacao-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, rgba(50, 255, 106, 0.25), rgba(50, 255, 106, 0.08));
    border: 1px solid rgba(91, 255, 129, 0.28);
    color: #64ff7b;
    font-size: 18px;
    flex-shrink: 0;
}
.programacao-title-area h2 {
    margin: 0;
    font-size: 16px;
    line-height: 1.05;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: -0.3px;
}
.programacao-title-area span {
    display: block;
    margin-top: 2px;
    color: #8fa1b7;
    font-size: 10px;
    font-weight: 600;
}
.programacao-date {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    color: #64ff7b;
    background: rgba(45, 255, 104, 0.10);
    border: 1px solid rgba(91, 255, 129, 0.24);
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}
.programacao-table {
    border: 1px solid rgba(125, 150, 180, 0.12);
    border-radius: 10px;
    overflow: hidden;
    background: rgba(5, 11, 20, 0.34);
    flex: 1;
}
.programacao-row {
    display: grid;
    grid-template-columns: 1.35fr repeat(4, 1fr);
    min-height: 34px;
    border-bottom: 1px solid rgba(125, 150, 180, 0.10);
}
.programacao-row:last-child { border-bottom: none; }
.programacao-row-head {
    min-height: 46px;
    background: linear-gradient(180deg, rgba(42, 255, 108, 0.13), rgba(42, 255, 108, 0.04));
    border-bottom: 1px solid rgba(91, 255, 129, 0.20);
}
.linha-col, .turno-col, .turno-status {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px 6px;
    border-right: 1px solid rgba(125, 150, 180, 0.10);
}
.linha-col { justify-content: flex-start; padding-left: 10px; }
.programacao-row > div:last-child { border-right: none; }
.programacao-row-head .linha-col {
    color: #64ff7b;
    font-size: 13px;
    font-weight: 900;
    text-transform: uppercase;
}
.turno-col { flex-direction: column; gap: 2px; }
.turno-col strong { color: #7dff86; font-size: 14px; font-weight: 900; }
.turno-col span { color: #a9b7c9; font-size: 9px; font-weight: 600; }
.turno-col.turno-alerta strong, .turno-col.turno-alerta span { color: #ffae34; }
.programacao-linha-nome { gap: 6px; color: #f5f7fb; font-size: 12px; font-weight: 900; }
.programacao-linha-nome i { color: #64ff7b; font-size: 14px; }
.status-check {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #79ff86;
    background: radial-gradient(circle at 35% 30%, rgba(119, 255, 134, 0.32), rgba(119, 255, 134, 0.10));
    border: 1px solid rgba(119, 255, 134, 0.22);
    font-size: 13px;
    font-weight: 900;
}
.status-check-alerta {
    color: #ffb138;
    background: radial-gradient(circle at 35% 30%, rgba(255, 177, 56, 0.34), rgba(255, 177, 56, 0.10));
    border-color: rgba(255, 177, 56, 0.24);
}
.status-empty { color: #7f8da1; font-size: 16px; font-weight: 900; }
.programacao-footer {
    min-height: 24px;
    border-radius: 7px;
    padding: 4px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.01));
    border: 1px solid rgba(125, 150, 180, 0.08);
}
.programacao-footer span { color: #8fa1b7; font-size: 11px; font-weight: 600; }
.programacao-footer strong {
    color: #64ff7b;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -0.3px;
}
</style>

{{-- Header --}}
<div class="header">
    <img src="{{ asset('images/aquafast-logo.svg') }}"
         alt="Aquafast"
         style="height:60px;width:auto;filter:brightness(0) invert(1);">
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
    // Contadores do topo (Situação) — conta direto $linha['cor'], já
    // reclassificado em AcompanharProducao::reclassificarComSinaisTempoReal()
    // (mesma fonte usada pelo loop de cards abaixo e pelo dashboard
    // "Acompanhar Produção"). Antes esse bloco re-derivava tudo de novo
    // consultando codi_eventos, e podia divergir do que os cards realmente
    // mostravam (Parada Programada/Intervalo/Troca de Kit/Troca de Líquido/
    // Desconexão não contam nem como Em dia nem como Atrasada, igual antes).
    $totalEmDia     = 0;
    $totalAtrasadas = 0;
    $totalAlertas   = 0;

    foreach ($linhas as $linhaContagem) {
        if (($linhaContagem['cor'] ?? null) === 'red') {
            $totalAtrasadas++;
            $totalAlertas++;
        } elseif (($linhaContagem['cor'] ?? null) === 'green') {
            $totalEmDia++;
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
        <div class="kpi-sub">de {{ number_format($kpis['previsto_hoje'] ?? 0, 0, ',', '.') }} cx · {{ number_format($kpis['pct_hoje'] ?? 0, 1, ',', '.') }}%</div>
    </div>
    {{-- 4. Previsão de hoje --}}
    <div class="kpi">
        <div class="kpi-label">Previsão de hoje</div>
        <div class="kpi-value {{ ($kpis['pct_proj'] ?? 0) >= 90 ? 'c-green' : (($kpis['pct_proj'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
            {{ number_format($kpis['projecao'] ?? 0, 0, ',', '.') }}
        </div>
        <div class="kpi-sub">
            {{ number_format($kpis['pct_proj'] ?? 0, 1, ',', '.') }}% de {{ number_format($kpis['previsto_hoje'] ?? 0, 0, ',', '.') }} cx
        </div>
    </div>
    {{-- Total programado --}}
    <div class="kpi">
        <div class="kpi-label">Total programado</div>
        <div class="kpi-value c-blue">{{ number_format($totalProgramado ?? 0, 0, ',', '.') }} cx</div>
        <div class="kpi-sub">Programação PCP</div>
    </div>
    {{-- 5. Diferença --}}
    <div class="kpi">
        <div class="kpi-label">Diferença Tot. Prev.</div>
        @php $dif = $kpis['diferenca'] ?? 0; @endphp
        <div class="kpi-value {{ $dif >= 0 ? 'c-green' : 'c-red' }}">
            {{ $dif >= 0 ? '+' : '' }}{{ number_format($dif, 0, ',', '.') }}
        </div>
        <div class="kpi-sub">Caixas hoje</div>
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
        <div class="kpi-value {{ ($kpis['oee_medio'] ?? 0) >= 75 ? 'c-green' : (($kpis['oee_medio'] ?? 0) >= 60 ? 'c-amber' : 'c-red') }}" style="font-size:46px;">
            {{ isset($kpis['oee_medio']) ? number_format($kpis['oee_medio'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
</div>

{{-- Grid de linhas --}}
<div class="linhas-grid">
    @forelse($linhas as $linha)
    @php
        $cor    = $linha['cor'] ?? 'gray';
        $barCor = match($cor) {
            'green'  => '#39d353',
            'red'    => '#f85149',
            'orange' => '#f97316',
            'yellow' => '#e3b341',
            default  => '#475569',
        };
        $op  = $linha['op_atual'] ?? null;
        $pct = min(100, $op['pct'] ?? 0);

        // KPIs por LINHA (não por OP)
        // Card 2 "Prev./Dia" e Card 3 "Prev. x Real." só aparecem quando a linha
        // tem programação confirmada + calendário e já produziu algo hoje.
        $projecaoLinha = null;
        $prevDiaStr    = '—';
        $prevXRealStr  = '—';
        $prevXRealCor  = '#8b949e';
        $atrasado      = false;
        $atrasoRitmoMin = 0;

        $codigoRecursoLinha = \Illuminate\Support\Facades\DB::table('linhas')
            ->where('codigo', $linha['codigo'])
            ->value('codigo_recurso');

        // Total produzido pela linha desde 06:00 (todas as OPs)
        $inicioDia6 = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        $prodLinha  = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecursoLinha)
            ->where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioDia6)
            ->sum('quantidade');

        $calendarioId = \Illuminate\Support\Facades\DB::table('calendarios')
            ->where('linha_id', $linha['id'])
            ->value('id');

        $progLinha = \Illuminate\Support\Facades\DB::table('programacoes')
            ->where('linha_id', $linha['id'])
            ->where('status', 'confirmada')
            ->first(['dias_selecionados']);

        if ($prodLinha > 0 && $calendarioId && $progLinha) {
            try {
                // Ritmo atual = total produzido ÷ período total decorrido desde 06:00 (TODOS os
                // eventos — PRODUCAO + PARADA/Intervalo —, não só o tempo produtivo, pra diluir
                // as paradas no ritmo médio e o cálculo fechar redondo com a jornada real).
                $minTrabalhados = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                    ->where('codigo_recurso', $codigoRecursoLinha)
                    ->where('inicio_evento', '>=', $inicioDia6)
                    ->sum('duracao_minutos');

                $horasTrabalhadas = max(0.1, $minTrabalhados / 60);
                $ritmoLinha       = $prodLinha / $horasTrabalhadas;

                $diasSelecionados = json_decode($progLinha->dias_selecionados ?? '[]', true);

                $hoje6  = new \DateTimeImmutable(\Carbon\Carbon::today()->format('Y-m-d') . ' 06:00:00');
                $fimJan = new \DateTimeImmutable(\Carbon\Carbon::today()->addDay()->format('Y-m-d') . ' 03:00:00');

                // O override de dias_selecionados só cobre "hoje" — se algum turno de hoje é
                // overnight (atravessa a meia-noite), amanhã precisa ser explicitamente
                // liberado APENAS para esses turnos overnight (não os diurnos do dia seguinte),
                // senão o CalendarioService pode truncar a parte 00:00→03:00 do turno noturno.
                $diasSelComOvernight = $diasSelecionados;
                $turnosHoje          = $diasSelecionados[$hoje6->format('Y-m-d')]['turnos'] ?? [];

                if (! empty($turnosHoje)) {
                    $turnosOvernightHoje = \Illuminate\Support\Facades\DB::table('intervalos')
                        ->whereIn('id', $turnosHoje)
                        ->where(function ($q) {
                            $q->where('hora_fim', '<=', '06:00:00')
                              ->orWhere('hora_inicio', '>=', '22:00:00');
                        })
                        ->pluck('id')
                        ->toArray();

                    $amanhaStr = $fimJan->format('Y-m-d');

                    if (! empty($turnosOvernightHoje) && ! isset($diasSelComOvernight[$amanhaStr])) {
                        $diasSelComOvernight[$amanhaStr] = [
                            'dia_semana' => (int) $fimJan->format('N'),
                            'turnos'     => $turnosOvernightHoje,
                        ];
                    }
                }

                $calendarioService = app(\App\Services\CalendarioService::class);

                // Capacidade teórica = ritmo atual × horas úteis de turno hoje (06:00→03:00)
                $minJornada        = $calendarioService->minutosUteisEntre($hoje6, $fimJan, $calendarioId, $diasSelComOvernight);
                $horasJornada      = $minJornada / 60;
                $capacidadeTeorica = $ritmoLinha * $horasJornada;

                // Total programado hoje = soma proporcional das OPs confirmadas da linha que
                // cruzam a janela 06:00→03:00 (mesmo rateio usado no TvStaticController)
                $opsLinhaHoje = \Illuminate\Support\Facades\DB::table('itens_programacao as ip')
                    ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                    ->leftJoin('codi_eficiencia as ce', function ($j) {
                        $j->on('ce.numero_op', '=', 'ip.numero_op')
                          ->on('ce.programacao_id', '=', 'p.id');
                    })
                    ->leftJoin('produtos as prod', 'prod.sku', '=', 'ip.sku')
                    ->where('p.linha_id', $linha['id'])
                    ->where('p.status', 'confirmada')
                    ->whereNotNull('ce.inicio_previsto')
                    ->whereNotNull('ce.fim_previsto')
                    ->where('ce.fim_previsto', '>', $hoje6->format('Y-m-d H:i:s'))
                    ->where('ce.inicio_previsto', '<', $fimJan->format('Y-m-d H:i:s'))
                    ->get(['ip.numero_op', 'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto', 'p.eficiencia', 'prod.taxa_por_hora']);

                $somaOpsHoje = 0.0;
                foreach ($opsLinhaHoje as $opRow) {
                    $inicioOp      = new \DateTimeImmutable($opRow->inicio_previsto);
                    $fimOp         = new \DateTimeImmutable($opRow->fim_previsto);
                    $inicioOverlap = $inicioOp < $hoje6 ? $hoje6 : $inicioOp;
                    $fimOverlap    = $fimOp > $fimJan ? $fimJan : $fimOp;

                    if ($fimOverlap <= $inicioOverlap) continue;

                    // NOTA: $diasSelComOvernight (só T4 pra amanhã) é seguro apenas para
                    // $minJornada, cuja janela termina em $fimJan. Aqui $inicioOp/$fimOp
                    // vêm da OP inteira e podem avançar bem além de amanhã (inclusive pelo
                    // turno diurno do dia seguinte) — usar o override restrito suprimiria
                    // T1/T2/T3 de amanhã via override explícito, sem cair no fallback já
                    // corrigido de CalendarioService. Usar o $diasSelecionados original.
                    $minTotal   = $calendarioService->minutosUteisEntre($inicioOp, $fimOp, $calendarioId, $diasSelecionados);
                    $minOverlap = $calendarioService->minutosUteisEntre($inicioOverlap, $fimOverlap, $calendarioId, $diasSelecionados);

                    if ($minTotal <= 0) continue;

                    // Previsto = taxa cadastrada × eficiência da programação × minutos
                    // úteis do overlap, nunca ultrapassando a quantidade da própria OP
                    // (igual ao Histórico/ListaProgramacoes::detalhesLinha()).
                    $taxaPorHora = (float) ($opRow->taxa_por_hora ?? 0);
                    $eficiencia  = (float) ($opRow->eficiencia ?? 100) / 100;
                    if ($taxaPorHora > 0) {
                        $prevCxOp = min((int) $opRow->quantidade, (int) round($taxaPorHora * $eficiencia * $minOverlap / 60));
                    } else {
                        // Fallback para proporção simples se não tiver taxa
                        $prevCxOp = (int) round($opRow->quantidade * ($minOverlap / $minTotal));
                    }
                    $somaOpsHoje += $prevCxOp;
                }

                $numeroOpsConfirmadosLinha = $opsLinhaHoje->pluck('numero_op')->all();

                // Reprogramação durante o dia (ex.: Colemar): a programação antiga foi
                // arquivada e substituída pela confirmada atual — não faz sentido somar
                // o previsto proporcional dela de novo (dobraria o programado do dia).
                // Soma só o que foi REALMENTE produzido no CODI para as OPs dessa
                // programação arquivada que não estão na confirmada atual.
                //
                // Janela desde o último dia útil (pula fim de semana) — não só
                // "hoje" — pra pegar reprogramações feitas na sexta/véspera que
                // ainda afetam o programado de hoje.
                $diaSemana = (int) (new \DateTimeImmutable('now'))->format('N'); // 1=seg, 7=dom
                $inicioUltimoDiaUtil = $diaSemana === 1
                    ? new \DateTimeImmutable(date('Y-m-d', strtotime('-3 days')) . ' 06:00:00')
                    : new \DateTimeImmutable(date('Y-m-d', strtotime('-1 day')) . ' 06:00:00');

                $progsArquivadasHoje = \Illuminate\Support\Facades\DB::table('programacoes')
                    ->where('linha_id', $linha['id'])
                    ->where('status', 'arquivada')
                    ->where('arquivada_em', '>=', $inicioUltimoDiaUtil->format('Y-m-d H:i:s'))
                    ->pluck('id');

                foreach ($progsArquivadasHoje as $progArq) {
                    // Buscar OPs desta programação arquivada
                    $numOpsArq = \Illuminate\Support\Facades\DB::table('itens_programacao')
                        ->where('programacao_id', $progArq)
                        ->whereNotIn('numero_op', $numeroOpsConfirmadosLinha ?? [])
                        ->pluck('numero_op')
                        ->filter()
                        ->values()
                        ->toArray();

                    if (empty($numOpsArq)) continue;

                    // Somar só a produção real dessas OPs no CODI hoje
                    $prodRealArq = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                        ->whereIn('ordem_producao', $numOpsArq)
                        ->where('tipo_evento', 'PRODUCAO')
                        ->where('inicio_evento', '>=', $hoje6->format('Y-m-d H:i:s'))
                        ->sum('quantidade');

                    $somaOpsHoje += $prodRealArq;
                }

                // Total Programado nunca fica menor que o que já foi produzido de
                // verdade hoje — evita mostrar um "programado" abaixo do realizado
                // quando o somaOps proporcional não capturou tudo.
                $somaOpsHoje = $prodLinha + $somaOpsHoje;

                // Sem OP programada na janela de hoje (gap de sincronização ou fila
                // realmente vazia) — não há base confiável para os cards, esconder ambos
                if ($somaOpsHoje <= 0) {
                    $projecaoLinha = null;
                } else {
                    // CARD 2 — Prev./Dia = soma proporcional das OPs programadas para hoje
                    $prevDia = (int) round($somaOpsHoje);

                    // CARD 3 — Prev. x Real. = capacidade teórica (ritmo) - programado hoje
                    // Positivo = ritmo dá pra produzir mais que o programado (sobra/confortável)
                    // Negativo = ritmo não dá conta do programado (risco)
                    $prevXRealVal = (int) round($capacidadeTeorica - $somaOpsHoje);

                    $projecaoLinha = $prevDia;
                    $prevDiaStr    = number_format($prevDia, 0, ',', '.');
                    $prevXRealStr  = ($prevXRealVal > 0 ? '+' : '') . number_format($prevXRealVal, 0, ',', '.');
                    // Negativo = linha produz a menos que o programado (atrasada); positivo = produz a mais (sobra)
                    $prevXRealCor  = $prevXRealVal < 0 ? '#f85149' : ($prevXRealVal > 0 ? '#39d353' : '#8b949e');
                    $atrasado      = $prevXRealVal < 0;

                    // Tempo equivalente de atraso no ritmo: quanto o déficit de caixas representa em minutos no ritmo atual
                    if ($atrasado && $ritmoLinha > 0) {
                        $atrasoRitmoMin = (int) round((abs($prevXRealVal) / $ritmoLinha) * 60);
                        $atrasoRitmoMin = min($atrasoRitmoMin, 999); // máximo 16h39 — valores maiores são artefatos de ritmo baixo
                    }
                }
            } catch (\Throwable $e) {
                $projecaoLinha = null;
            }
        }

        $temParada = str_contains($linha['estado'] ?? '', 'Parada')
                  || !empty($linha['parada_aberta'])
                  || in_array($linha['codigo'], $linhasComParada);

        // Classificação (Parada Programada/Intervalo/Troca de Kit/Troca de
        // Líquido/Desconexão/Atrasada por ritmo) já vem pronta de
        // AcompanharProducao::reclassificarComSinaisTempoReal() — fonte única
        // compartilhada com o dashboard "Acompanhar Produção". Antes essa
        // lógica era re-derivada aqui de novo (consultando codi_eventos
        // direto), o que fazia a TV e o dashboard divergirem pra mesma linha.
        $ehParadaProgramada = $linha['estado'] === 'Parada Programada';
        $ehIntervalo        = $linha['estado'] === 'Intervalo';
        $ehTrocaKit         = $linha['estado'] === 'Troca de Kit';
        $ehTrocaLiquido     = $linha['estado'] === 'Troca de Líquido';
        $ehDesconexao       = $linha['estado'] === 'Desconexão';
        $opAtrasada         = $linha['cor'] === 'red' && $linha['estado'] === 'Atrasada';

        // Cronômetro de troca — só o tempo decorrido da parada atual (exibição
        // visual), não influencia a classificação acima.
        $tempoTrocaMin = 0;
        if ($ehTrocaKit || $ehTrocaLiquido) {
            $inicioUltimaParada = \Illuminate\Support\Facades\DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecursoLinha)
                ->where('tipo_evento', 'PARADA')
                ->orderByDesc('inicio_evento')
                ->value('inicio_evento');

            if ($inicioUltimaParada) {
                $tempoTrocaMin = (int) \Carbon\Carbon::parse($inicioUltimaParada)->diffInMinutes(now());
            }
        }
        $tempoTrocaHhmm = str_pad(intdiv($tempoTrocaMin, 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad($tempoTrocaMin % 60, 2, '0', STR_PAD_LEFT);

        $pulsingClass = '';
        if ($temParada && !$ehParadaProgramada) {
            $pulsingClass = $linha['cor'] === 'orange' ? 'pulsing-orange' : 'pulsing-red';
        }
    @endphp
    @php
        // --- Mapeamento para a nova estrutura visual dos cards ---

        // Número da linha (ex.: "2" extraído do código "LN02") — variável separada
        // do $linha do loop (que é o array inteiro), pra não quebrar a lógica acima
        $linhaNumero = ltrim(preg_replace('/\D+/', '', $linha['codigo'] ?? ''), '0');
        if ($linhaNumero === '') { $linhaNumero = '0'; }

        $statusCorMapa = [
            'green'  => 'verde',
            'red'    => 'vermelho',
            'orange' => 'laranja',
            'yellow' => 'amarelo',
            'blue'   => 'azul',
            'gray'   => 'cinza',
        ];

        // Prioridade: Parada Programada > Intervalo > Troca de Kit > Troca de Líquido > Desconexão > Atrasada (ritmo) > cor da linha
        $statusClasse = $ehParadaProgramada
            ? 'amarelo'
            : ($ehIntervalo
                ? 'azul'
                : ($ehTrocaKit
                    ? 'laranja'
                    : ($ehTrocaLiquido
                        ? 'laranja'
                        : ($ehDesconexao
                            ? 'preto'
                            : ($opAtrasada
                                ? 'vermelho'
                                : ($statusCorMapa[$cor] ?? 'cinza'))))));

        $statusTexto = $ehParadaProgramada
            ? 'Parada'
            : ($ehIntervalo
                ? 'Intervalo'
                : ($ehTrocaKit
                    ? 'Troca de Kit'
                    : ($ehTrocaLiquido
                        ? 'Troca de Líquido'
                        : ($ehDesconexao
                            ? 'Desconexão'
                            : ($opAtrasada
                                ? 'Atrasada'
                                : $linha['estado'])))));

        // Classe do status-pill (componente próprio, independente da cor do card)
        $statusPillMapa = [
            'verde'    => 'em-dia',
            'vermelho' => 'atrasada',
            'laranja'  => 'troca-kit',
            'amarelo'  => 'parada',
            'azul'     => 'intervalo',
            'cinza'    => 'em-dia',
            'preto'    => 'desconexao',
        ];
        $statusPillClasse = $statusPillMapa[$statusClasse] ?? 'em-dia';

        // Possível erro de apontamento: produzido da OP atual acima do
        // programado sugere que o CODI está somando produção de outra
        // OP/lote no apontamento desta. $op['programado']/$op['realizado'] já são
        // os campos usados no card (meta e "número grande") — não existem
        // $op['quantidade'] nem $linha['produzido'].
        $qtdOp       = (int) ($op['programado'] ?? 0);
        $produzidoOp = (int) ($op['realizado'] ?? 0);
        $possivelErroApontamento = $qtdOp > 0 && $produzidoOp > $qtdOp;

        // Condição 1: divergência registrada pelo comando pcp:verificar-divergencias
        // (OP rodando no CODI que não está na programação confirmada da linha)
        $temDivergenciaOp = \Illuminate\Support\Facades\DB::table('divergencias_op')
            ->where('modulo', 'envase')
            ->where('linha_codigo', $linha['codigo'])
            ->whereNull('resolvida_em')
            ->exists();

        // Condição 2: produção da OP atual ultrapassou a quantidade programada
        // (erro de apontamento — mesmo gatilho do triângulo amarelo)
        $temDivergencia = $temDivergenciaOp || $possivelErroApontamento;

        $fotoProduto = \Illuminate\Support\Facades\DB::table('produtos')
            ->where('sku', $op['sku'] ?? '')
            ->value('foto');

        $imagemProduto = $fotoProduto
            ? asset('fotos-produtos/' . $fotoProduto)
            : asset('images/aquafast-logo.svg');

        // Embalagens de 1,5L são visualmente mais altas que as demais — aplica
        // uma classe pra reduzir levemente o tamanho e nivelar com as outras
        $ehEmbalagem15L = (bool) preg_match('/1[.,]5\s*l\b/i', $op['descricao'] ?? '');

        $oeeReal  = $linha['oee_tempo_real']['oee']            ?? null;
        $dispReal = $linha['oee_tempo_real']['disponibilidade'] ?? null;
        $perfReal = $linha['oee_tempo_real']['performance']     ?? null;
        $oeeClasse  = is_null($oeeReal)  ? 'neutro' : ($oeeReal  >= 75 ? 'verde' : ($oeeReal  >= 60 ? 'amarelo' : 'vermelho'));
        $dispClasse = is_null($dispReal) ? 'neutro' : ($dispReal >= 85 ? 'verde' : ($dispReal >= 70 ? 'amarelo' : 'vermelho'));
        $perfClasse = is_null($perfReal) ? 'neutro' : ($perfReal >= 85 ? 'verde' : ($perfReal >= 70 ? 'amarelo' : 'vermelho'));

        $prevRealClasse = $projecaoLinha === null
            ? 'neutro'
            : ($prevXRealVal > 0 ? 'verde' : ($prevXRealVal < 0 ? 'vermelho' : 'neutro'));

        // Aliases dos valores já calculados acima pros nomes de variável da nova estrutura
        $produzido       = number_format($op['realizado'] ?? 0, 0, ',', '.');
        $meta            = number_format($op['programado'] ?? 0, 0, ',', '.');
        $percentual      = $pct . '%';
        $percentualBarra = $pct;
        $totalDia        = number_format($linha['total_hoje'] ?? 0, 0, ',', '.');
        $prevDiaExibicao = $prevDiaStr;
        $prevReal        = $prevXRealStr;
        $disponibilidade = !is_null($dispReal) ? number_format($dispReal,1,',','.').'%' : '—';
        $performance     = !is_null($perfReal) ? number_format($perfReal,1,',','.').'%' : '—';
        $oee             = !is_null($oeeReal)  ? number_format($oeeReal,1,',','.').'%'  : '—';
    @endphp
    <div class="linha-card {{ $statusClasse }} {{ $pulsingClass }} {{ $ehParadaProgramada ? 'em-pausa' : ($ehIntervalo ? 'em-intervalo' : ($ehTrocaKit ? 'em-troca-kit' : ($ehTrocaLiquido ? 'em-troca-liquido' : ($ehDesconexao ? 'em-desconexao' : '')))) }} {{ $temDivergencia ? 'tem-divergencia' : '' }} {{ $ehEmbalagem15L ? 'produto-15l-card' : '' }}">
        <div class="status-pill {{ $statusPillClasse }}">
            <span class="pill-label">{{ $statusTexto }}</span>
            @if($temDivergencia)
                <span class="divergencia-icon" title="OP rodando sem programação">⚠</span>
            @endif
        </div>
        @if($ehParadaProgramada)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">⏸</div>
            <div style="font-size:26px;font-weight:800;color:#e3b341;letter-spacing:1px;text-transform:uppercase;">Parada Programada</div>
        </div>
        @elseif($ehIntervalo)
        <div class="status-overlay">
            <img src="{{ asset('images/aquafast-logo.svg') }}" style="max-height:60px;max-width:140px;object-fit:contain;filter:brightness(0) invert(1);opacity:0.9;margin-bottom:14px;" alt="">
            <div style="font-size:26px;font-weight:800;color:#58a6ff;letter-spacing:1px;text-transform:uppercase;">Intervalo</div>
        </div>
        @elseif($ehTrocaKit)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🔧</div>
            <div style="font-size:26px;font-weight:800;color:#ff8c00;letter-spacing:1px;text-transform:uppercase;">Troca de Kit</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px;color:#ffb347;font-size:20px;font-weight:700;">
                <span style="font-size:22px;">⏱</span>
                <span class="cronometro-troca" data-minutos="{{ $tempoTrocaMin }}">{{ $tempoTrocaHhmm }}</span>
            </div>
        </div>
        @elseif($ehTrocaLiquido)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🧴</div>
            <div style="font-size:26px;font-weight:800;color:#ff8c00;letter-spacing:1px;text-transform:uppercase;">Troca de Líquido</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px;color:#ffb347;font-size:20px;font-weight:700;">
                <span style="font-size:22px;">⏱</span>
                <span class="cronometro-troca" data-minutos="{{ $tempoTrocaMin }}">{{ $tempoTrocaHhmm }}</span>
            </div>
        </div>
        @elseif($ehDesconexao)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">⚡</div>
            <div style="font-size:26px;font-weight:800;color:#c0c0c0;letter-spacing:1px;text-transform:uppercase;">Desconexão</div>
        </div>
        @endif
        <div class="card-content">
            <div class="linha-topo">
                <div class="linha-info">
                    <div class="linha-nome">LINHA {{ $linhaNumero }}</div>
                    @if($op)
                        <div class="op-info">OP {{ $op['numero_op'] }}</div>
                        <div class="produto">{{ $op['descricao'] }}</div>
                        @php
                            // Atraso de início da OP tem prioridade; se não houver, usa o equivalente
                            // em tempo do déficit de ritmo (Card "Prev. x Real." negativo)
                            $aMin = ($op['atraso_inicio_min'] ?? 0) > 0
                                ? (int) $op['atraso_inicio_min']
                                : ($opAtrasada ? $atrasoRitmoMin : 0);
                            $aHhmm = $aMin > 0
                                ? intdiv($aMin,60).':'.str_pad($aMin%60,2,'0',STR_PAD_LEFT)
                                : null;
                        @endphp
                    @else
                        <div class="op-info" style="margin-top:16px;">Aguardando início</div>
                    @endif
                </div>
                <div class="produto-coluna">
                    <div class="produto-img-wrap">
                        <img class="produto-img {{ ($fotoProduto ?? null) ? '' : 'produto-img-placeholder' }} {{ $ehEmbalagem15L ? 'produto-15l' : '' }}"
                             src="{{ $imagemProduto }}" alt="{{ $op['descricao'] ?? '' }}">
                    </div>
                    @if($op && ($aMin ?? 0) > 0)
                        <div class="atraso">{{ $aHhmm }} de atraso</div>
                    @endif
                </div>
            </div>
            <div class="bloco-inferior">
                <div class="indicadores indicadores-meio">
                    <div class="indicador">
                        <div class="indicador-label">DISPONIBILIDADE</div>
                        <div class="indicador-valor {{ $dispClasse }}">{{ $disponibilidade }}</div>
                    </div>
                    <div class="indicador">
                        <div class="indicador-label">PERFORMANCE</div>
                        <div class="indicador-valor {{ $perfClasse }}">{{ $performance }}</div>
                    </div>
                    <div class="indicador oee-card">
                        <div class="indicador-label">OEE</div>
                        <div class="indicador-valor {{ $oeeClasse }}">{{ $oee }}</div>
                    </div>
                </div>
                @if($op)
                <div class="producao-area">
                    <div class="producao-main">
                        <span class="cx-valor">{{ $produzido }}</span>
                        <span class="cx-meta">
                            @if(($op['programado'] ?? 0) > 0)
                                / {{ $meta }} cx <span>•</span> {{ $percentual }}@if($possivelErroApontamento) <span class="triangulo-apontamento">⚠</span>@endif
                            @else
                                cx
                            @endif
                        </span>
                    </div>
                    <div class="barra-wrap">
                        <div class="barra" style="width: {{ $percentualBarra }}%;background:{{ $barCor }};"></div>
                    </div>
                </div>
                @endif
                <div class="totais-inferiores">
                    <div class="total-box">
                        <div class="total-label">Total produzido</div>
                        <div class="total-valor">{{ $totalDia }}</div>
                        <div class="total-meta">cx produzidas</div>
                    </div>
                    @if($projecaoLinha !== null)
                    <div class="total-box">
                        <div class="total-label">Total Programado</div>
                        <div class="total-valor">{{ $prevDiaExibicao }}</div>
                        <div class="total-meta">cx programadas</div>
                    </div>
                    <div class="total-box">
                        <div class="total-label">Previsto x Realizado</div>
                        <div class="total-valor {{ $prevRealClasse }}">{{ $prevReal }}</div>
                        <div class="total-meta">diferença</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>{{-- card-content --}}
    </div>
    @empty
        <div style="color:#8b949e;grid-column:1/-1;text-align:center;padding:40px;">Nenhuma linha ativa</div>
    @endforelse

    {{-- Card de resumo — mesmos dados/regras da tela Envase > Histórico --}}
    <div class="card-programacao-producao">
        <div class="programacao-header">
            <div class="programacao-title-area">
                <div class="programacao-icon">📅</div>
                <div>
                    <h2>Programação de Produção</h2>
                    <span>Grade diária por turno</span>
                </div>
            </div>
            <div class="programacao-date">
                📅 {{ now()->format('d/m/Y') }}
            </div>
        </div>
        <div class="programacao-table">
            <div class="programacao-row programacao-row-head">
                <div class="linha-col">Linha</div>
                <div class="turno-col"><strong>T1</strong><span>07:05–11:30</span></div>
                <div class="turno-col"><strong>T2</strong><span>13:27–17:45</span></div>
                <div class="turno-col turno-alerta"><strong>T3</strong><span>17:45–22:00</span></div>
                <div class="turno-col turno-alerta"><strong>T4</strong><span>23:00–03:00</span></div>
            </div>
            @foreach($programacoesResumo as $progResumo)
                @php $gradeResumo = $gradeTurnosResumo[$progResumo->id] ?? []; @endphp
                <div class="programacao-row">
                    <div class="linha-col programacao-linha-nome">
                        🏭 Linha {{ substr($progResumo->linha->codigo, 2) }}
                    </div>
                    @foreach($gradeResumo as $idx => $turnoAtivoResumo)
                        <div class="turno-status">
                            @if($turnoAtivoResumo)
                                <span class="status-check {{ $idx >= 2 ? 'status-check-alerta' : '' }}">✓</span>
                            @else
                                <span class="status-empty">—</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="programacao-footer">
            <span>Produção prevista do dia</span>
            <strong>{{ number_format($producaoPrevistaResumo, 0, ',', '.') }}</strong>
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
}

window.addEventListener('resize', ajustarEscala);
window.addEventListener('load', ajustarEscala);
document.addEventListener('fullscreenchange', ajustarEscala);
ajustarEscala();

// Cronômetros de troca de kit/líquido
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
</script>
</div>
</div>
</body>
</html>
