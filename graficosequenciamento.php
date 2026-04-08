<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();
Auth::requireLogin();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Gráfico de Sequenciamento</title>
  <link rel="stylesheet" href="assets/css/app.css">
  <style>
    :root {
      --sequenciamento-label-width: 270px;
    }
    #sequenciamento-page {
      margin: 24px auto;
      max-width: 1240px;
      padding: 0 12px;
    }
    #sequenciamento-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
      padding: 0 4px;
    }
    #sequenciamento-filters {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
      background: rgba(255, 255, 255, 0.8);
      padding: 14px;
      border-radius: 20px;
      border: 1px solid rgba(15, 23, 42, 0.08);
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
    }
    .period-buttons {
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
    }
    .period-btn {
      padding: 8px 16px;
      border-radius: 999px;
      border: 1px solid rgba(15, 23, 42, 0.15);
      background: #f8fafc;
      font-size: 12px;
      color: #0f172a;
      cursor: pointer;
      transition: all 0.2s ease;
      box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
    }
    .period-btn.active {
      background: #2563eb;
      color: white;
      border-color: #2563eb;
      box-shadow: 0 7px 18px rgba(37, 99, 235, 0.3);
    }
    #sequenciamento-filters label {
      font-size: 12px;
      color: #0f172a;
    }
    #sequenciamento-filters select {
      min-width: 140px;
      border-radius: 12px;
      border: 1px solid rgba(15, 23, 42, 0.2);
      padding: 8px 12px;
    }
    #sequenciamento-recurso {
      min-height: 80px;
    }
    #sequenciamento-summary {
      display: flex;
      gap: 12px;
      margin-bottom: 16px;
    }
    .sequenciamento-summary .summary-card {
      flex: 1;
      padding: 12px 16px;
      background: white;
      border-radius: 12px;
      border: 1px solid rgba(15, 23, 42, 0.08);
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .sequenciamento-summary .summary-label {
      font-size: 11px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: rgba(15, 23, 42, 0.7);
    }
    .sequenciamento-summary strong {
      font-size: 18px;
      color: #0f172a;
    }
    #sequenciamento-grafico {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 12px;
      min-height: 360px;
      padding: 16px;
      background: #fff;
      position: relative;
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
    }
    .sequenciamento-legend {
      display: flex;
      gap: 8px;
      font-size: 13px;
      margin-top: 12px;
      flex-wrap: wrap;
      padding-bottom: 12px;
      border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    }
    .sequenciamento-legend span {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .sequenciamento-legend b {
      width: 16px;
      height: 8px;
      display: inline-block;
      border-radius: 2px;
    }
    #sequenciamento-chart-wrapper {
      position: relative;
      margin-top: 12px;
      border-radius: 12px;
      background: linear-gradient(180deg, rgba(37, 99, 235, 0.08), rgba(255, 255, 255, 0.9));
      overflow: hidden;
      min-height: 360px;
      border: 1px solid rgba(15, 23, 42, 0.08);
      box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
    }
    #sequenciamento-axis {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 12px;
      padding: 8px 16px;
      border-bottom: 1px solid rgba(15, 23, 42, 0.08);
      background: #f8fafc;
      position: sticky;
      top: 0;
      z-index: 2;
      margin-left: var(--sequenciamento-label-width);
      width: calc(100% - var(--sequenciamento-label-width));
      box-sizing: border-box;
      overflow-x: hidden;
    }
    #sequenciamento-resource-head .resource-head-pills {
      display: flex;
      gap: 8px;
    }
    .resource-head-pill {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 12px;
      border: 1px solid rgba(15, 23, 42, 0.15);
      background: #fff;
      color: #1f2937;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }
    .resource-head-pill.programacao-pill {
      border-color: rgba(59, 130, 246, 0.3);
    }
    .resource-head-pill.realizado-pill {
      border-color: rgba(16, 185, 129, 0.3);
    }
    #resource-list {
      font-size: 12px;
      letter-spacing: 0.02em;
      color: #475569;
    }
    #sequenciamento-resource-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 16px;
      border-bottom: 1px solid rgba(15, 23, 42, 0.08);
      background: #fff;
      position: sticky;
      top: -56px;
      z-index: 3;
    }
    .axis-weeks,
    .axis-days {
      display: flex;
      width: 100%;
    }
    .axis-weeks span,
    .axis-days span {
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      color: rgba(15,23,42,0.75);
      border-right: 1px solid rgba(15,23,42,0.1);
      background: rgba(15,23,42,0.03);
      font-weight: 600;
      min-height: 26px;
      box-sizing: border-box;
      padding: 4px;
    }
    .axis-weeks span:last-child,
    .axis-days span:last-child {
      border-right: none;
    }
    .axis-hours {
      display: flex;
      width: 100%;
      border-top: 1px solid rgba(15,23,42,0.08);
      background: #eef2ff;
    }
    .axis-hours span {
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      color: rgba(15,23,42,0.7);
      border-right: 1px solid rgba(15,23,42,0.12);
      padding: 4px;
      box-sizing: border-box;
    }
    .axis-hours span:last-child {
      border-right: none;
    }
    #sequenciamento-chart {
      position: relative;
      padding: 16px 0;
      min-height: 240px;
      overflow-x: scroll;
      min-width: 720px;
    }
    .sequenciamento-row {
      position: relative;
      display: flex;
      align-items: center;
      height: 34px;
      border-bottom: 1px solid rgba(15,23,42,0.08);
      margin-bottom: 2px;
    }
    .sequenciamento-row:last-child {
      border-bottom: none;
    }
    .sequenciamento-row-label {
      flex: 0 0 var(--sequenciamento-label-width);
      padding-left: 10px;
      font-weight: 600;
      color: #0f172a;
      display: flex;
      flex-direction: column;
      gap: 0px;
      background: rgba(15, 23, 42, 0.04);
      border-radius: 6px;
      padding-right: 6px;
      min-height: 30px;
      justify-content: center;
      overflow: hidden;
    }
    .sequenciamento-row-label span {
      font-size: 11px;
      line-height: 1.2;
      max-height: 2.4em;
      overflow: hidden;
      text-overflow: ellipsis;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      display: -webkit-box;
    }
    .sequenciamento-row-label span {
      display: block;
      line-height: 1.2;
      font-size: 12px;
      color: #0f172a;
    }
    .sequenciamento-row-label .resource-name {
      font-weight: 700;
      font-size: 13px;
    }
    .sequenciamento-row-label .resource-detail {
      font-weight: 400;
      color: rgba(15, 23, 42, 0.8);
    }
    .sequenciamento-row-bar-area {
      position: relative;
      flex: 1;
      height: 100%;
    }
    .sequenciamento-bar {
      position: absolute;
      border-radius: 8px;
      box-shadow: 0 4px 10px rgba(15,23,42,0.12);
      color: #fff;
      font-size: 11px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: help;
      transition: transform 0.2s ease;
    }
    .sequenciamento-bar.plan {
      top: 10px;
      height: 20px;
    }
    .sequenciamento-bar.plan.plan-dimmed {
      opacity: 0.3;
    }
    .sequenciamento-bar.actual {
      top: calc(50% - 6px);
      height: 12px;
      border-radius: 6px;
    }
    .sequenciamento-bar:hover {
      transform: translateY(-2px);
    }
    .sequenciamento-bar[data-count]:after {
      content: attr(data-count);
      font-weight: 600;
    }
    .sequenciamento-row-bar-area::after {
      content: '';
      position: absolute;
      left: 0;
      right: 0;
      top: 0;
      bottom: 0;
      background-image: linear-gradient(90deg, rgba(15,23,42,0.04) 1px, transparent 1px);
      background-size: 24px 100%;
      pointer-events: none;
    }
    .sequenciamento-status {
      margin-top: 12px;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid transparent;
      background: #e0f2fe;
      color: #0c4a6e;
      min-height: 46px;
    }
    .sequenciamento-status.info {
      background: #e0f2fe;
      border-color: #bae6fd;
      color: #0c4a6e;
    }
    .sequenciamento-status.loading {
      background: #e0f2fe;
      border-color: #93c5fd;
      color: #0c4a6e;
    }
    .sequenciamento-status.success {
      background: #ecfdf5;
      border-color: #a7f3d0;
      color: #065f46;
    }
    .sequenciamento-status.warning {
      background: #fff7ed;
      border-color: #fed7aa;
      color: #92400e;
    }
    .sequenciamento-status.danger {
      background: #fee2e2;
      border-color: #fecaca;
      color: #7f1d1d;
    }
    .sequenciamento-tooltips {
      position: absolute;
      pointer-events: none;
      background: #0f172a;
      color: #fff;
      padding: 8px;
      border-radius: 6px;
      font-size: 12px;
      min-width: 200px;
      z-index: 10;
      display: none;
    }
    .sequenciamento-row.is-active {
      background: rgba(59, 130, 246, 0.08);
    }
    .sequenciamento-detail-panel {
      margin-top: 16px;
      background: #fff;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 12px;
      padding: 12px 16px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }
    .sequenciamento-detail-panel.has-data {
      border-color: rgba(59, 130, 246, 0.3);
    }
    .sequenciamento-detail-title {
      font-weight: 600;
      margin-bottom: 10px;
      color: #0f172a;
    }
    .sequenciamento-detail-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 8px;
      font-size: 13px;
      color: #1f2937;
    }
    .sequenciamento-detail-row {
      padding: 6px 8px;
      border-radius: 6px;
      background: rgba(15, 23, 42, 0.03);
      display: flex;
      flex-direction: column;
      gap: 2px;
      min-height: 52px;
    }
    .sequenciamento-detail-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: rgba(15, 23, 42, 0.7);
    }
    .sequenciamento-detail-value {
      font-weight: 600;
      color: #0f172a;
    }
    .sequenciamento-detail-empty {
      margin: 0;
      color: #475569;
      font-size: 13px;
    }
    @media (max-width: 900px) {
      :root {
        --sequenciamento-label-width: 120px;
      }
      #sequenciamento-page {
        margin: 12px;
      }
      .sequenciamento-row-label {
        flex: 0 0 120px;
        font-size: 12px;
      }
    }
    @media (max-width: 700px) {
      #sequenciamento-filters {
        flex-direction: column;
      }
      #sequenciamento-summary {
        flex-direction: column;
      }
      #sequenciamento-chart-wrapper {
        min-height: 280px;
      }
    }
  </style>
</head>
<body class="bg-gray-50">
  <main id="sequenciamento-page">
    <section id="sequenciamento-header">
      <div>
        <p class="text-3xl font-semibold text-slate-900 mb-1">Gráfico de Sequenciamento</p>
        <p class="text-sm text-slate-500">Visualize as operações por recurso e período para facilitar o sequenciamento.</p>
      </div>
      <div id="sequenciamento-actions">
        <button class="btn btn-outline btn-sm">Exportar PNG</button>
        <button class="btn btn-outline btn-sm">Gerar PDF</button>
      </div>
    </section>
    <section id="sequenciamento-summary" class="sequenciamento-summary">
      <div class="summary-card">
        <span class="summary-label">Produção planejada</span>
        <strong id="summary-production">00h 00m</strong>
      </div>
      <div class="summary-card">
        <span class="summary-label">Setup planejado</span>
        <strong id="summary-setup">00h 00m</strong>
      </div>
      <div class="summary-card">
        <span class="summary-label">Itens exibidos</span>
        <strong id="summary-count">0</strong>
      </div>
    </section>

    <section id="sequenciamento-filters">
      <label>Período
      <select id="sequenciamento-periodo">
        <option value="mes" selected>Mês</option>
        <option value="semana">Semana atual</option>
        <option value="14dias">14 dias</option>
        <option value="24h">24h</option>
        <option value="12h">12h</option>
        <option value="8h">8h</option>
        <option value="4h">4h</option>
        <option value="tudo">Tudo</option>
      </select>
      </label>
      <label>Visão
        <select id="sequenciamento-visao">
          <option value="planejado" selected>Planejado</option>
          <option value="execucao">Execução real</option>
        </select>
      </label>
      <label>Programação
        <select id="sequenciamento-programacao">
          <option value="">Carregando programações...</option>
        </select>
      </label>
      <label>Recurso
        <select id="sequenciamento-recurso" multiple size="3">
          <option value="__all__" selected>Todos</option>
        </select>
        <small style="display:block;font-size:11px;color:#475569;margin-top:4px;">Segure Ctrl para selecionar múltiplos recursos</small>
      </label>
      <label>Status
        <select id="sequenciamento-status">
          <option value="todos">Todos</option>
          <option value="produca">Produção</option>
          <option value="setup">Setup</option>
        </select>
      </label>
      <button class="btn btn-primary btn-sm" id="sequenciamento-buscar">Carregar</button>
    </section>
    <div id="period-buttons" class="period-buttons">
      <button data-period="24h" class="period-btn">24h</button>
      <button data-period="12h" class="period-btn">12h</button>
      <button data-period="8h" class="period-btn">8h</button>
      <button data-period="4h" class="period-btn">4h</button>
      <button data-period="tudo" class="period-btn active">Tudo</button>
    </div>

    <div id="sequenciamento-status-area" class="sequenciamento-status info">
      Informe o ID da programação e clique em carregar para visualizar o gráfico.
    </div>

    <section id="sequenciamento-grafico">
      <div id="sequenciamento-chart-wrapper">
        <div id="sequenciamento-resource-head">
          <div class="resource-head-labels">
            <span class="resource-head-pill programacao-pill">Programação</span>
            <span class="resource-head-pill realizado-pill">Realizado</span>
          </div>
          <div id="resource-list" class="resource-list">Recurso: --</div>
        </div>
        <div id="sequenciamento-axis"></div>
        <div id="sequenciamento-chart"></div>
      </div>
      <div id="sequenciamento-detail-panel" class="sequenciamento-detail-panel">
        <div class="sequenciamento-detail-title">Detalhes da operação</div>
        <div class="sequenciamento-detail-content">
          <p class="sequenciamento-detail-empty">Clique em uma barra para ver os dados completos.</p>
        </div>
      </div>
      <p class="text-slate-500 mt-4">Use a legenda para interpretar as cores e clique em uma barra para ver os detalhes.</p>
      <div class="sequenciamento-legend">
        <span><b style="background:#3B82F6"></b>Produção</span>
        <span><b style="background:#EA580C"></b>Setup</span>
        <span><b style="background:#F8B4D1"></b>Pausa</span>
        <span><b style="background:#10B981"></b>Execução atual</span>
      </div>
    </section>
  </main>
  <script src="assets/js/graficosequenciamento.js"></script>
</body>
</html>
