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
<div id="tv-conteudo" style="padding:8px 12px;display:flex;flex-direction:column;gap:8px;background:#0d1117;color:#e6edf3;font-family:'Segoe UI',Arial,sans-serif;width:1920px;height:1080px;overflow:hidden;transform-origin:top left;">

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
.kpi-label{font-size:14px;color:#8b949e;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kpi-value{font-size:64px;font-weight:600;line-height:1}
.kpi-sub{font-size:16px;color:#8b949e;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kpi-oee{background:#1e2a1e;border-top:1px solid rgba(255,255,255,0.15);border-right:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08);border-left:1px solid rgba(255,255,255,0.08);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.1)}
.kpi-oee-mini{background:#1a2518;border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:5px 8px;text-align:center;flex:1;box-shadow:0 2px 8px rgba(0,0,0,0.4)}
.kpi-oee-mini .kpi-label-mini{color:#8b949e;font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;}
.kpi-oee-mini .kpi-val-mini{font-size:18px;font-weight:600}

.c-green{color:#39d353}.c-amber{color:#e3b341}.c-red{color:#f85149}.c-blue{color:#58a6ff}.c-muted{color:#8b949e}

.linhas-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;overflow:hidden;align-content:start;}
.pagina-linhas{display:flex;flex-direction:column;flex:1;min-height:0;}

.linha-card {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 30px 24px 14px 27px;
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

    font-size:25px;
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
    grid-template-columns: minmax(0, 1fr) 253px;
    gap: 8px;
    height: 262px;
    position: relative;
    z-index: 3;
}
.linha-info { min-width: 0; }
/* Fora do .card-content (que é borrado nos estados de parada) — assim o nome
   da linha continua nítido e visível por cima do overlay, sem duplicar. */
.linha-nome {
    position: relative;
    z-index: 25;
    font-size: clamp(61px, 7.3vw, 85px);
    line-height: .92;
    font-weight: 850;
    white-space: nowrap;
    margin-bottom: 8px;
    color:#f8fafc;text-transform:uppercase;text-shadow:0 6px 22px rgba(0,0,0,.38);
}
.op-info { font-size: 25px; margin-bottom: 8px; color:#8b949e; }
.produto {
    font-size: 25px;
    line-height: 1.18;
    font-weight: 800;
    max-width: 360px;
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
    width: 253px;
    height: 260px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    overflow: visible;
    z-index: 3;
    margin-right: 3px;
    margin-top: -68px;
}
.produto-img-wrap::before {
    content: "";
    position: absolute;
    left: 50%;
    bottom: 48px;
    transform: translateX(-50%);
    width: 260px;
    height: 260px;
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
    width: 207px;
    height: 43px;
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
    max-height: 243px;
    max-width: 207px;
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
/* ==========================================================
   EMBALAGENS ALTAS (galão 5L e pulverizador)
   ========================================================== */
.produto-img.produto-alto {
    max-height: 195px;
    max-width: 166px;
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
    gap: 18px;
    margin-top: 1px;
    position: relative;
    z-index: 3;
}
.indicador {
    min-height: 128px;
    padding: 16px 24px;
    border-radius: 15px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:rgba(31,42,58,.84);border:1px solid rgba(148,163,184,.12);text-align:center;
}
.indicador-label{font-size:25px;line-height:1;font-weight:700;color:#a8b3c7;letter-spacing:1px;}
.indicador-valor { margin-top:14px;font-size:68px;line-height:1;font-weight:850; }
.indicador-valor.verde{color:#39d353;}
.indicador-valor.amarelo{color:#ffc83d;}
.indicador-valor.vermelho{color:#ff514f;}
.indicador-valor.neutro{color:#8b949e;}
.oee-card{background:rgba(16,31,19,.72);border-color:rgba(109,255,93,.18);}
.producao-area {
    margin-top: 0px;
    position: relative;
    z-index: 3;
}
.producao-main {
    display: flex;
    align-items: flex-end;
    gap: 6px;
}
.cx-valor {
    font-size: clamp(58px, 6.8vw, 82px);
    line-height: .9;
    font-weight: 400;
    color:#ffffff;letter-spacing:-2px;text-shadow:0 8px 28px rgba(0,0,0,.45);
}
.cx-meta {
    font-size: 50px;
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
    height: 22px;
    border-radius: 999px;
    overflow: hidden;
    background:rgba(148,163,184,.18);
}
.barra { height: 100%; border-radius: inherit; }
.totais-inferiores {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-top: 14px;
    position: relative;
    z-index: 3;
}
.total-box {
    min-height: 128px;
    border-radius: 15px;
    padding: 16px 24px 16px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:rgba(15,23,35,.72);border:1px solid rgba(109,255,93,.26);text-align:center;
}
.total-label {
    font-size: 28px;
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: .04em;
    text-align: center;
    white-space: normal;
    text-transform: none;
    margin-bottom: 4px;
    color:#8fe874;
}
.total-valor { font-size: 75px; font-weight: 850; line-height: .95; margin-bottom: 6px; color:#fff; }
.total-valor.verde{color:#39d353;}
.total-valor.vermelho{color:#f85149;}
.total-valor.neutro{color:#8b949e;}
.total-meta { font-size: 30px; line-height: 1; opacity: .72; color:#a8b3c7; }
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
    width: 68px;
    height: 68px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, rgba(50, 255, 106, 0.25), rgba(50, 255, 106, 0.08));
    border: 1px solid rgba(91, 255, 129, 0.28);
    color: #64ff7b;
    font-size: 22px;
    flex-shrink: 0;
}
.programacao-title-area h2 {
    margin: 0;
    font-size: 32px;
    line-height: 1.05;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: -0.3px;
}
.programacao-title-area span {
    display: block;
    margin-top: 4px;
    color: #8fa1b7;
    font-size: 19px;
    font-weight: 600;
}
.programacao-date {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    border-radius: 999px;
    color: #64ff7b;
    background: rgba(45, 255, 104, 0.10);
    border: 1px solid rgba(91, 255, 129, 0.24);
    font-size: 25px;
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
    min-height: 78px;
    border-bottom: 1px solid rgba(125, 150, 180, 0.10);
}
.programacao-row:last-child { border-bottom: none; }
.programacao-row-head {
    min-height: 96px;
    background: linear-gradient(180deg, rgba(42, 255, 108, 0.13), rgba(42, 255, 108, 0.04));
    border-bottom: 1px solid rgba(91, 255, 129, 0.20);
}
.linha-col, .turno-col, .turno-status {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border-right: 1px solid rgba(125, 150, 180, 0.10);
}
.linha-col { justify-content: flex-start; padding-left: 20px; }
.programacao-row > div:last-child { border-right: none; }
.programacao-row-head .linha-col {
    color: #64ff7b;
    font-size: 26px;
    font-weight: 900;
    text-transform: uppercase;
}
.turno-col { flex-direction: column; gap: 4px; }
.turno-col strong { color: #7dff86; font-size: 28px; font-weight: 900; }
.turno-col span { color: #a9b7c9; font-size: 19px; font-weight: 600; }
.turno-col.turno-alerta strong, .turno-col.turno-alerta span { color: #ffae34; }
.programacao-linha-nome { gap: 12px; color: #f5f7fb; font-size: 24px; font-weight: 900; }
.programacao-linha-nome i { color: #64ff7b; font-size: 28px; }
.status-check {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #79ff86;
    background: radial-gradient(circle at 35% 30%, rgba(119, 255, 134, 0.32), rgba(119, 255, 134, 0.10));
    border: 1px solid rgba(119, 255, 134, 0.22);
    font-size: 26px;
    font-weight: 900;
}
.status-check-alerta {
    color: #ffb138;
    background: radial-gradient(circle at 35% 30%, rgba(255, 177, 56, 0.34), rgba(255, 177, 56, 0.10));
    border-color: rgba(255, 177, 56, 0.24);
}
.status-empty { color: #7f8da1; font-size: 32px; font-weight: 900; }
.programacao-footer {
    min-height: 46px;
    border-radius: 7px;
    padding: 9px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.01));
    border: 1px solid rgba(125, 150, 180, 0.08);
}
.programacao-footer span { color: #8fa1b7; font-size: 20px; font-weight: 600; }
.programacao-footer strong {
    color: #64ff7b;
    font-size: 34px;
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
        <div class="kpi-value c-blue">{{ number_format($totalProgramado ?? 0, 0, ',', '.') }}</div>
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
        <div class="kpi-value {{ ($kpis['oee_medio'] ?? 0) >= 75 ? 'c-green' : (($kpis['oee_medio'] ?? 0) >= 60 ? 'c-amber' : 'c-red') }}" style="font-size:63px;">
            {{ isset($kpis['oee_medio']) ? number_format($kpis['oee_medio'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
</div>

{{-- Divide as linhas em páginas de 2 (lado a lado, ~meia tela cada),
     alternadas automaticamente a cada 10s. O card de resumo entra como um
     "item" a mais na última página (par com a última linha, se sobrar 1). --}}
@php
    $linhasParaExibir = $linhas;
    $itensEnv = $linhasParaExibir;
    $itensEnv[] = '__resumo_producao__'; // marcador — vira o card de resumo no loop
    $gruposEnv = array_chunk($itensEnv, 2);
@endphp

{{-- Indicador de página --}}
<div style="display:flex;justify-content:center;gap:8px;margin-bottom:6px;">
    @foreach($gruposEnv as $idxDot => $grupoDot)
        <div id="dot-env-{{ $idxDot + 1 }}" style="width:10px;height:10px;border-radius:50%;background:{{ $idxDot === 0 ? '#4ade80' : '#374151' }};transition:background 0.3s;"></div>
    @endforeach
</div>

@foreach($gruposEnv as $idxPag => $grupoEnv)
    <div id="pagina-env-{{ $idxPag + 1 }}" class="pagina-linhas" style="display:{{ $idxPag === 0 ? 'flex' : 'none' }};flex:1;">
        <div style="display:grid;grid-template-columns:repeat(2,1fr);grid-template-rows:repeat(1,1fr);gap:14px;width:100%;flex:1;min-height:0;">
            @forelse($grupoEnv as $itemEnv)
                @if($itemEnv === '__resumo_producao__')
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
                @else
                    @php $linha = $itemEnv; @endphp
                    @include('tv._envase-linha-card')
                @endif
            @empty
                <div style="color:#8b949e;grid-column:1/-1;text-align:center;padding:40px;">Nenhuma linha ativa</div>
            @endforelse
        </div>
    </div>
@endforeach

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

// Alternância automática entre as páginas de linhas (2 por vez, lado a lado)
const INTERVALO_ENV = 13000;
const TOTAL_PAGINAS_ENV = {{ count($gruposEnv) }};
let paginaEnvAtual = 1;

function alternarPaginaEnv() {
    if (TOTAL_PAGINAS_ENV <= 1) return;

    const atual = document.getElementById('pagina-env-' + paginaEnvAtual);
    const dotAtual = document.getElementById('dot-env-' + paginaEnvAtual);
    if (atual) atual.style.display = 'none';
    if (dotAtual) dotAtual.style.background = '#374151';

    paginaEnvAtual = paginaEnvAtual >= TOTAL_PAGINAS_ENV ? 1 : paginaEnvAtual + 1;

    const proxima = document.getElementById('pagina-env-' + paginaEnvAtual);
    const dotProxima = document.getElementById('dot-env-' + paginaEnvAtual);
    if (proxima) proxima.style.display = 'flex';
    if (dotProxima) dotProxima.style.background = '#4ade80';

    ajustarEscala();
}
setInterval(alternarPaginaEnv, INTERVALO_ENV);

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
