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
      --sequenciamento-label-width: 220px;
    }
    #sequenciamento-page {
      margin: 24px auto;
      max-width: 1200px;
    }
    #sequenciamento-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
    }
    #sequenciamento-filters {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }
    #sequenciamento-filters label {
      font-size: 12px;
      color: #0f172a;
    }
    #sequenciamento-grafico {
      border: 1px solid rgba(15,23,42,0.08);
      border-radius: 12px;
      min-height: 360px;
      padding: 16px;
      background: #fff;
      position: relative;
    }
    .sequenciamento-legend {
      display: flex;
      gap: 8px;
      font-size: 13px;
      margin-top: 12px;
      flex-wrap: wrap;
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
      border-radius: 8px;
      background: linear-gradient(180deg, rgba(37,99,235,0.05), rgba(37,99,235,0.03));
      overflow: hidden;
    }
    #sequenciamento-axis {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 12px;
      padding: 8px 16px;
      border-bottom: 1px solid rgba(15,23,42,0.08);
      background: #f8fafc;
      position: sticky;
      top: 0;
      z-index: 1;
      margin-left: var(--sequenciamento-label-width);
      width: calc(100% - var(--sequenciamento-label-width));
      box-sizing: border-box;
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
    #sequenciamento-chart {
      position: relative;
      padding: 16px 0;
      min-height: 240px;
    }
    .sequenciamento-row {
      position: relative;
      display: flex;
      align-items: center;
      height: 40px;
      border-bottom: 1px solid rgba(15,23,42,0.08);
    }
    .sequenciamento-row:last-child {
      border-bottom: none;
    }
    .sequenciamento-row-label {
      flex: 0 0 var(--sequenciamento-label-width);
      padding-left: 16px;
      font-weight: 600;
      color: #0f172a;
      display: flex;
      flex-direction: column;
      gap: 2px;
      background: rgba(15, 23, 42, 0.04);
      border-radius: 6px;
      padding-right: 8px;
      min-height: 36px;
      justify-content: center;
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
      top: 10px;
      height: 20px;
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

    <section id="sequenciamento-filters">
      <label>Período
        <select id="sequenciamento-periodo">
          <option value="semana">Semana atual</option>
          <option value="14dias">14 dias</option>
          <option value="mes" selected>Mês</option>
        </select>
      </label>
      <label>Programação
        <select id="sequenciamento-programacao">
          <option value="">Carregando programações...</option>
        </select>
      </label>
      <label>Recurso
        <input type="text" id="sequenciamento-recurso" placeholder="Deixar vazio para todos">
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

    <div id="sequenciamento-status-area" class="sequenciamento-status info">
      Informe o ID da programação e clique em carregar para visualizar o gráfico.
    </div>

    <section id="sequenciamento-grafico">
      <div id="sequenciamento-chart-wrapper">
        <div id="sequenciamento-axis"></div>
        <div id="sequenciamento-chart"></div>
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
