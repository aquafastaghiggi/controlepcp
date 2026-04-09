<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Prévia Visual: Novo Gantt PCP</title>
    <link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --setup-color: #e67e22;
            --prod-color: #3498db;
            --real-color: #27ae60; /* Verde para o Realizado */
            --delay-color: #e74c3c; /* Vermelho para Atrasos */
        }
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; margin: 0; padding: 20px; height: 100vh; display: flex; flex-direction: column; }
        .header { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 5px solid var(--primary-dark); }
        #gantt_here { flex: 1; border-radius: 10px; border: 1px solid #ddd; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        
        /* Estilos Customizados Gantt */
        .gantt_task_line { border-radius: 6px; border: none; height: 14px !important; }
        .gantt_task_content { font-size: 10px; line-height: 14px; }
        .gantt_task_row { height: 50px !important; } /* Linha mais alta para caber previsto e realizado */
        
        /* Cores das Barras */
        .bar-planned { background-color: var(--prod-color); opacity: 0.85; }
        .bar-realized { background-color: var(--real-color); margin-top: 18px !important; }
        .bar-setup { background-color: var(--setup-color); opacity: 0.8; }
        .bar-delayed { background-color: var(--delay-color); margin-top: 18px !important; }

        .legend { display: flex; gap: 20px; padding: 15px; background: white; border-radius: 8px; margin-top: 15px; font-size: 13px; font-weight: 600; }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .dot { width: 14px; height: 14px; border-radius: 3px; }
    </style>
</head>
<body>

<div class="header">
    <h1 style="margin:0; font-size: 22px; color: var(--primary-dark);">🚀 PRÉVIA VISUAL: Novo Sequenciamento PCP</h1>
    <p style="margin:5px 0 0; color: #666; font-size: 14px;">Implementando Cabeçalho de 6h, Escala Semanal e <b>Comparativo Realizado</b></p>
</div>

<div id="gantt_here"></div>

<div class="legend">
    <div class="legend-item"><div class="dot" style="background:var(--prod-color)"></div> Previsto (Planejado)</div>
    <div class="legend-item"><div class="dot" style="background:var(--real-color)"></div> Realizado (No Prazo)</div>
    <div class="legend-item"><div class="dot" style="background:var(--delay-color)"></div> Realizado (Com Atraso)</div>
    <div class="legend-item"><div class="dot" style="background:var(--setup-color)"></div> Setup</div>
    <div style="margin-left: auto; color: #e67e22;">⚠️ Esta é uma demonstração visual do novo layout.</div>
</div>

<script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script>
<script>
    // Configurações de Escala (O que você pediu: Semana > Dia > 6 Horas)
    gantt.config.scales = [
        {unit: "week", step: 1, format: "Semana %W"},
        {unit: "day", step: 1, format: "%D, %d %M"},
        {unit: "hour", step: 6, format: "%H:00"} // Guia de 6 em 6 horas
    ];

    gantt.config.scale_height = 80; // Cabeçalho triplo precisa de mais altura
    gantt.config.row_height = 55;   // Linha mais alta para barras sobrepostas
    gantt.config.readonly = true;
    gantt.config.columns = [
        {name: "text", label: "Produto / OP", width: 220, tree: true},
        {name: "status", label: "Status", align: "center", width: 100}
    ];

    // Simulação de Dados com Previsto e Realizado
    const tasks = {
        data: [
            { id: 1, text: "📦 SKU 20010003", start_date: "28-03-2026 07:00", duration: 12, status: "OK", css: "bar-planned" },
            { id: 11, text: "", start_date: "28-03-2026 07:15", duration: 11.5, parent: 1, css: "bar-realized" },
            
            { id: 2, text: "⚙️ SETUP", start_date: "28-03-2026 19:00", duration: 2, status: "OK", css: "bar-setup" },
            
            { id: 3, text: "📦 SKU 20160005", start_date: "28-03-2026 21:00", duration: 18, status: "ATRASO", css: "bar-planned" },
            { id: 31, text: "", start_date: "28-03-2026 22:30", duration: 20, parent: 3, css: "bar-delayed" }
        ]
    };

    gantt.init("gantt_here");
    gantt.parse(tasks);

    // Forçar scroll para o início dos dados
    gantt.showDate(new Date(2026, 2, 28, 0, 0));
</script>
</body>
</html>
