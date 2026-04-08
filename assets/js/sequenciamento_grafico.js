/**
 * Sequenciamento Gráfico - Gantt com Previsto × Realizado
 * Integra com APIs: programacoes_historico.php, sequenciamento_gantt.php
 */

const CONFIG = {
    API_PROGRAMACOES: '/api/programacoes_historico.php',
    API_GANTT: '/api/sequenciamento_gantt.php',
    HOURS_PER_DAY: 24,
    SIDEBAR_WIDTH: 250,
    ROW_HEIGHT: 56,
    DAY_WIDTH_PX: 150,  // Aumentado de 100 → 150 para ter mais espaço por dia
};

class SequenciamentoGrafico {
    constructor() {
        this.currentProgramacao = null;
        this.currentData = null;
        this.selectedItem = null;
        this.semanal = 1;
        this.ano = new Date().getFullYear();
        this.init();
    }

    async init() {
        this.setupEventListeners();
        await this.carregarProgramacoes();
    }

    setupEventListeners() {
        document.getElementById('programacaoSelect').addEventListener('change', (e) => this.onProgramacaoChange(e));
        document.getElementById('aplicarBtn').addEventListener('click', () => this.aplicarFiltros());
        document.getElementById('restaurarBtn').addEventListener('click', () => this.restaurarFiltros());
        document.getElementById('semanaAnteriorBtn').addEventListener('click', () => this.semanaAnterior());
        document.getElementById('proximaSemanaBtn').addEventListener('click', () => this.proximaSemana());
    }

    addMessage(message, type = 'info') {
        const container = document.getElementById('messagesContainer');
        const el = document.createElement('div');
        el.className = `message ${type}`;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(() => el.remove(), 5000);
    }

    showLoading(show = true) {
        if (show) {
            document.getElementById('metricsContainer').style.display = 'none';
            document.getElementById('timelineContainer').style.display = 'none';
        }
    }

    async carregarProgramacoes() {
        try {
            const response = await fetch(CONFIG.API_PROGRAMACOES);
            const data = await response.json();

            if (!data.sucesso) {
                throw new Error(data.erro || 'Erro ao carregar programações');
            }

            const select = document.getElementById('programacaoSelect');
            select.innerHTML = '<option value="">Selecione uma programação...</option>';

            data.programacoes.forEach(prog => {
                const option = document.createElement('option');
                option.value = prog.id;
                option.textContent = prog.label;
                option.dataset.data = JSON.stringify(prog);
                select.appendChild(option);
            });

            if (data.programacoes.length > 0) {
                select.value = data.programacoes[0].id;
                await this.onProgramacaoChange();
            }
        } catch (error) {
            this.addMessage(`Erro ao carregar programações: ${error.message}`, 'error');
        }
    }

    async onProgramacaoChange() {
        const select = document.getElementById('programacaoSelect');
        const prg_id = select.value;

        if (!prg_id) {
            this.showLoading(true);
            return;
        }

        this.currentProgramacao = JSON.parse(
            select.options[select.selectedIndex].dataset.data
        );

        // Pré-preencher datas
        document.getElementById('dataInicio').value = this.currentProgramacao.data_inicio;
        document.getElementById('dataFim').value = this.currentProgramacao.data_fim;

        await this.carregarGantt();
    }

    async carregarGantt() {
        if (!this.currentProgramacao) return;

        this.showLoading(true);

        try {
            const prg_id = this.currentProgramacao.id;
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;

            const url = new URL(CONFIG.API_GANTT, window.location.origin);
            url.searchParams.set('programacao_id', prg_id);
            if (dataInicio) url.searchParams.set('data_inicio', dataInicio);
            if (dataFim) url.searchParams.set('data_fim', dataFim);

            const response = await fetch(url.toString());
            const data = await response.json();

            if (!data.sucesso) {
                throw new Error(data.erro || 'Erro ao carregar dados');
            }

            this.currentData = data;
            this.renderizar();
            this.mostrarMensagemSucesso();
        } catch (error) {
            this.addMessage(`Erro ao carregar gráfico: ${error.message}`, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    renderizar() {
        if (!this.currentData) return;

        this.renderMetricas();
        this.renderResumo();
        this.renderSemanas();
        this.renderTimeline();

        document.getElementById('metricsContainer').style.display = 'block';
        document.getElementById('layoutGrid').style.display = 'flex';
        document.getElementById('timelineContainer').style.display = 'block';
        document.getElementById('detailContainer').style.display = 'block';
    }

    renderMetricas() {
        const data = this.currentData;
        const m = data.metricas;

        const formatNum = (n) => new Intl.NumberFormat('pt-BR', {
            maximumFractionDigits: 0
        }).format(n);

        document.getElementById('metricLinha').textContent = data.programacao.linha;
        document.getElementById('metricPeriodo').textContent = `${data.periodo.inicio} a ${data.periodo.fim}`;
        document.getElementById('metricOrdens').textContent = `${m.producoes} / ${m.setups}`;
        document.getElementById('metricPrevisto').textContent = formatNum(m.total_previsto);
        document.getElementById('metricRealizado').textContent = formatNum(m.total_realizado);
        document.getElementById('metricCumprimento').textContent = `${m.percentual.toFixed(1)}%`;

        // Cores baseadas em cumprimento
        const cumprimentoEl = document.getElementById('metricCumprimento');
        if (m.percentual >= 100) {
            cumprimentoEl.style.color = '#22c55e'; // Verde
        } else if (m.percentual >= 80) {
            cumprimentoEl.style.color = '#fb923c'; // Laranja
        } else {
            cumprimentoEl.style.color = '#ef4444'; // Vermelho
        }
    }

    renderResumo() {
        const m = this.currentData.metricas;
        const formatNum = (n) => new Intl.NumberFormat('pt-BR', {
            maximumFractionDigits: 2
        }).format(n);

        document.getElementById('resumoPrevisto').textContent = formatNum(m.total_previsto);
        document.getElementById('resumoRealizado').textContent = formatNum(m.total_realizado);
        document.getElementById('resumoDiferenca').textContent = formatNum(m.diferenca);
        document.getElementById('resumoTaxa').textContent = `${m.percentual.toFixed(1)}%`;
    }

    renderSemanas() {
        // Calcular semanas do período
        const inicio = new Date(this.currentData.periodo.inicio);
        const fim = new Date(this.currentData.periodo.fim);
        const semanas = [];

        let current = new Date(inicio);
        while (current <= fim) {
            const semana = this.getWeekNumber(current);
            if (!semanas.find(s => s.week === semana)) {
                semanas.push({
                    week: semana,
                    start: new Date(current),
                    label: `S${semana}`
                });
            }
            current.setDate(current.getDate() + 1);
        }

        const selector = document.getElementById('semanaSelector');
        selector.innerHTML = '';

        semanas.forEach((s, idx) => {
            const btn = document.createElement('button');
            btn.className = 'semana-btn';
            if (idx === 0) btn.classList.add('active');
            btn.textContent = s.label;
            btn.dataset.week = s.week;
            btn.addEventListener('click', () => this.selecionarSemana(s.week));
            selector.appendChild(btn);
        });
    }

    /**
     * ETAPA 5D+: Agregar operações duplicadas pelo mesmo OP na mesma data
     * Reduz de 1829 ops para ~200-300 agregadas
     */
    agregarOperacoes(timeline) {
        const grupos = {};
        
        timeline.forEach(item => {
            // Chave de agrupamento: OP + tipo + data_inicio
            const chave = `${item.op}|${item.tipo || 'producao'}|${item.data}`;
            
            if (!grupos[chave]) {
                // Primeira ocorrência: inicializar grupo
                grupos[chave] = {
                    op: item.op,
                    nome: item.nome,
                    tipo: item.tipo,
                    data: item.data,
                    dia: item.dia,
                    start: item.start,
                    end: item.end,
                    duracao_horas: item.duracao_horas,
                    quantidade_prevista: item.quantidade_prevista,
                    quantidade_realizada: item.quantidade_realizada,
                    status: item.status,
                    hora_inicio: item.hora_inicio,
                    hora_fim: item.hora_fim,
                    _count: 1  // Contador de items agregados
                };
            } else {
                // Item adicional: agregar
                const g = grupos[chave];
                g.start = Math.min(g.start, item.start);  // Expandir início
                g.end = Math.max(g.end, item.end);        // Expandir fim
                g.quantidade_prevista += item.quantidade_prevista;
                g.quantidade_realizada += item.quantidade_realizada;
                g.duracao_horas = g.end - g.start;
                g._count += 1;
                
                // Atualizar hora_inicio/fim se necessário
                if (item.hora_inicio < g.hora_inicio) g.hora_inicio = item.hora_inicio;
                if (item.hora_fim > g.hora_fim) g.hora_fim = item.hora_fim;
            }
        });
        
        // Converter dicionário para array e recalcular percentuais
        const agregadas = Object.values(grupos).map(g => {
            return {
                ...g,
                percentual_cumprimento: g.quantidade_prevista > 0 
                    ? (g.quantidade_realizada / g.quantidade_prevista) * 100 
                    : 0,
                _agregada: g._count > 1  // Flag para indicar que foi agregada
            };
        });
        
        console.log(`[Agregação] ${timeline.length} → ${agregadas.length} operações (redução de ${((1 - agregadas.length/timeline.length)*100).toFixed(1)}%)`);
        return agregadas;
    }

    renderTimeline() {
        const timeline = this.currentData.timeline;
        const container = document.getElementById('timelineChart');
        container.innerHTML = '';

        if (!timeline || timeline.length === 0) {
            container.innerHTML = '<div class="loading">Nenhuma operação encontrada</div>';
            return;
        }

        const opsUnicos = {};
        const timelineUnicos = [];

        timeline.forEach(item => {
            const chave = item.op;
            if (!opsUnicos[chave]) {
                opsUnicos[chave] = true;
                timelineUnicos.push(item);
            }
        });

        console.log(`[Filtro] ${timeline.length} → ${timelineUnicos.length} operações únicas`);

        timelineUnicos.sort((a, b) => a.start - b.start);

        const periodo = this.currentData.periodo;
        const startDate = new Date(periodo.inicio);
        const endDate = new Date(periodo.fim);
        const daysCount = Math.ceil((endDate - startDate) / (24 * 60 * 60 * 1000)) + 1;

        this.renderTimelineHeader_v2(container, startDate, daysCount);
        timelineUnicos.forEach(item => this.renderTimelineRow_v2(container, item, startDate, 0));
    }

    /**
     * NOVO LAYOUT: Gantt Horizontal Simples
     * Cada OP = uma linha com barra horizontal
     */
    renderGanttHorizontal(container, items, startDate, endDate, totalDays) {
        // Container principal
        const ganttDiv = document.createElement('div');
        ganttDiv.style.display = 'flex';
        ganttDiv.style.backgroundColor = '#fff';
        ganttDiv.style.borderRadius = '4px';
        ganttDiv.style.overflow = 'hidden';
        ganttDiv.style.boxShadow = '0 1px 3px rgba(0,0,0,0.05)';

        // SIDEBAR com lista de OPs
        const sidebar = document.createElement('div');
        sidebar.style.width = '280px';
        sidebar.style.flexShrink = '0';
        sidebar.style.borderRight = '2px solid #e5e7eb';
        sidebar.style.overflowY = 'auto';
        sidebar.style.maxHeight = '600px';

        const sidebarHeader = document.createElement('div');
        sidebarHeader.style.padding = '12px';
        sidebarHeader.style.fontWeight = '700';
        sidebarHeader.style.fontSize = '12px';
        sidebarHeader.style.color = '#0f172a';
        sidebarHeader.style.borderBottom = '1px solid #e5e7eb';
        sidebarHeader.style.backgroundColor = '#f8fafc';
        sidebarHeader.textContent = 'OP / Produto';
        sidebar.appendChild(sidebarHeader);

        items.forEach(item => {
            const itemEl = document.createElement('div');
            itemEl.style.padding = '8px 12px';
            itemEl.style.borderBottom = '1px solid #e5e7eb';
            itemEl.style.cursor = 'pointer';
            itemEl.style.fontSize = '12px';
            itemEl.style.color = '#0f172a';
            itemEl.style.transition = 'background 0.2s';

            const opEl = document.createElement('div');
            opEl.style.fontWeight = '600';
            opEl.style.color = '#1f2937';
            opEl.textContent = `OP ${item.op}`;
            itemEl.appendChild(opEl);

            const nomeEl = document.createElement('div');
            nomeEl.style.fontSize = '11px';
            nomeEl.style.color = '#6b7280';
            nomeEl.style.marginTop = '2px';
            nomeEl.style.whiteSpace = 'nowrap';
            nomeEl.style.overflow = 'hidden';
            nomeEl.style.textOverflow = 'ellipsis';
            nomeEl.textContent = item.nome;
            itemEl.appendChild(nomeEl);

            itemEl.addEventListener('click', () => this.selecionarItem(item));
            itemEl.addEventListener('mouseenter', () => {
                itemEl.style.backgroundColor = '#f0f9f7';
            });
            itemEl.addEventListener('mouseleave', () => {
                itemEl.style.backgroundColor = 'transparent';
            });

            sidebar.appendChild(itemEl);
        });

        ganttDiv.appendChild(sidebar);

        // TIMELINE: Header + Barras
        const timelineDiv = document.createElement('div');
        timelineDiv.style.flex = '1';
        timelineDiv.style.display = 'flex';
        timelineDiv.style.flexDirection = 'column';
        timelineDiv.style.overflowX = 'auto';

        // Header com datas
        this.renderGanttHeader(timelineDiv, startDate, totalDays);

        // Container de barras (com scrollY)
        const barsContainer = document.createElement('div');
        barsContainer.style.flex = '1';
        barsContainer.style.overflowY = 'auto';
        barsContainer.style.maxHeight = '600px';

        // Renderizar cada OP como uma barra
        items.forEach(item => {
            const barRow = this.createGanttBarRow(item, startDate, endDate);
            barsContainer.appendChild(barRow);
        });

        timelineDiv.appendChild(barsContainer);
        ganttDiv.appendChild(timelineDiv);
        container.appendChild(ganttDiv);
    }

    renderGanttHeader(container, startDate, totalDays) {
        const headerDiv = document.createElement('div');
        headerDiv.style.display = 'flex';
        headerDiv.style.borderBottom = '2px solid #0f172a';
        headerDiv.style.backgroundColor = '#f8fafc';
        headerDiv.style.minHeight = '80px';

        for (let i = 0; i < totalDays; i++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(currentDate.getDate() + i);

            const dayDiv = document.createElement('div');
            dayDiv.style.flex = '1';
            dayDiv.style.minWidth = '150px';
            dayDiv.style.borderRight = '1px solid #cbd5e1';
            dayDiv.style.padding = '8px 4px';
            dayDiv.style.textAlign = 'center';
            dayDiv.style.fontSize = '12px';
            dayDiv.style.fontWeight = '600';
            dayDiv.style.color = '#0f172a';

            const weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            const dayName = weekdays[currentDate.getDay()];
            const dayNum = String(currentDate.getDate()).padStart(2, '0');
            const monthNum = String(currentDate.getMonth() + 1).padStart(2, '0');

            const weekNum = this.getWeekNumber(currentDate);
            const semanaEl = document.createElement('div');
            semanaEl.style.fontSize = '10px';
            semanaEl.style.color = '#64748b';
            semanaEl.textContent = `Semana ${weekNum}`;
            dayDiv.appendChild(semanaEl);

            const dateEl = document.createElement('div');
            dateEl.style.marginTop = '4px';
            dateEl.textContent = `${dayName} ${dayNum}/${monthNum}`;
            dayDiv.appendChild(dateEl);

            // Alternância de cores por semana
            const bgColor = weekNum % 2 === 0 ? '#f0f9f7' : '#fff7ed';
            dayDiv.style.backgroundColor = bgColor;

            headerDiv.appendChild(dayDiv);
        }

        container.appendChild(headerDiv);
    }

    createGanttBarRow(item, startDate, endDate) {
        const rowDiv = document.createElement('div');
        rowDiv.style.display = 'flex';
        rowDiv.style.minHeight = '40px';
        rowDiv.style.alignItems = 'center';
        rowDiv.style.borderBottom = '1px solid #e5e7eb';
        rowDiv.style.paddingLeft = '0';

        // A data de INÍCIO da operação
        const itemStartDate = new Date(item.data);
        
        // Calcular data de FIM: origem + duração em horas
        const durationMs = (item.end - item.start) * 60 * 60 * 1000;
        const itemEndDate = new Date(itemStartDate.getTime() + durationMs);

        // Posição: dias desde o começo do período
        const daysFromStart = Math.max(0, Math.floor((itemStartDate - startDate) / (24 * 60 * 60 * 1000)));
        const totalDays = Math.ceil((endDate - startDate) / (24 * 60 * 60 * 1000)) + 1;
        
        // Duração: diferença em dias entre início e fim
        const durationDays = Math.max(0.1, (itemEndDate - itemStartDate) / (24 * 60 * 60 * 1000));
        
        // Posição e largura em %
        const barStartPercent = (daysFromStart / totalDays) * 100;
        const barWidthPercent = ((durationDays) / totalDays) * 100;

        // Cores por status
        let bgColor = '#f97316';  // Laranja padrão (previsto)
        if (item.tipo && item.tipo.toUpperCase() === 'SETUP') {
            bgColor = '#f59e0b';  // Laranja mais escuro para setup
        }
        if (item.quantidade_realizada > 0) {
            bgColor = '#10b981';  // Verde se tem realizado
        }

        // Barra
        const barDiv = document.createElement('div');
        barDiv.style.marginLeft = barStartPercent + '%';
        barDiv.style.width = Math.max(barWidthPercent, 1) + '%';
        barDiv.style.minWidth = '60px';
        barDiv.style.height = '24px';
        barDiv.style.backgroundColor = bgColor;
        barDiv.style.borderRadius = '2px';
        barDiv.style.cursor = 'pointer';
        barDiv.style.display = 'flex';
        barDiv.style.alignItems = 'center';
        barDiv.style.paddingLeft = '4px';
        barDiv.style.fontSize = '10px';
        barDiv.style.fontWeight = '600';
        barDiv.style.color = '#fff';
        barDiv.style.overflow = 'hidden';
        barDiv.style.textOverflow = 'ellipsis';
        barDiv.style.whiteSpace = 'nowrap';
        barDiv.style.boxShadow = '0 1px 2px rgba(0,0,0,0.1)';
        barDiv.style.transition = 'all 0.2s ease';

        // Mostrar percentual se tiver realizado
        if (item.percentual_cumprimento > 0) {
            barDiv.textContent = `${item.percentual_cumprimento.toFixed(0)}%`;
        } else {
            barDiv.textContent = '';
        }

        barDiv.addEventListener('click', () => this.selecionarItem(item));
        barDiv.addEventListener('mouseenter', () => {
            barDiv.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
            barDiv.style.opacity = '0.9';
            const tooltipText = `OP ${item.op} | ${item.hora_inicio}-${item.hora_fim} | ${item.quantidade_realizada.toFixed(0)}/${item.quantidade_prevista.toFixed(0)}un`;
            this.createTooltip(barDiv, tooltipText);
        });
        barDiv.addEventListener('mouseleave', () => {
            barDiv.style.boxShadow = '0 1px 2px rgba(0,0,0,0.1)';
            barDiv.style.opacity = '1';
            this.hideTooltip(barDiv);
        });

        rowDiv.appendChild(barDiv);
        return rowDiv;
    }
    }

    renderTimelineHeader(container, dayLabels, daysCount) {
        const headerDiv = document.createElement('div');
        headerDiv.style.display = 'flex';
        headerDiv.style.borderBottom = '1px solid #e5e7eb';
        headerDiv.style.borderTop = '1px solid #e5e7eb';

        // Sidebar label
        const sidebarLabel = document.createElement('div');
        sidebarLabel.style.width = CONFIG.SIDEBAR_WIDTH + 'px';
        sidebarLabel.style.flexShrink = '0';
        sidebarLabel.style.padding = '12px';
        sidebarLabel.style.fontSize = '12px';
        sidebarLabel.style.fontWeight = '600';
        sidebarLabel.style.borderRight = '1px solid #e5e7eb';
        sidebarLabel.style.background = '#f9fafb';
        sidebarLabel.textContent = 'OP / Produto';
        headerDiv.appendChild(sidebarLabel);

        // Day headers
        const daysDiv = document.createElement('div');
        daysDiv.style.display = 'flex';
        daysDiv.style.flex = '1';
        daysDiv.style.overflow = 'auto';

        dayLabels.forEach((label, idx) => {
            const dayDiv = document.createElement('div');
            dayDiv.style.flex = '1';
            dayDiv.style.minWidth = CONFIG.DAY_WIDTH_PX + 'px';
            dayDiv.style.padding = '12px 8px';
            dayDiv.style.textAlign = 'center';
            dayDiv.style.fontSize = '11px';
            dayDiv.style.fontWeight = '500';
            dayDiv.style.borderRight = '1px solid #e5e7eb';
            dayDiv.style.background = idx % 2 === 0 ? 'white' : '#f9fafb';
            dayDiv.textContent = label;
            daysDiv.appendChild(dayDiv);
        });

        headerDiv.appendChild(daysDiv);
        container.appendChild(headerDiv);
    }

    renderTimelineRow(container, item, startHour, totalHours, daysCount) {
        const rowDiv = document.createElement('div');
        rowDiv.style.display = 'flex';
        rowDiv.style.borderBottom = '1px solid #e5e7eb';
        rowDiv.style.minHeight = CONFIG.ROW_HEIGHT + 'px';
        rowDiv.style.alignItems = 'stretch';

        // Sidebar
        const sidebar = document.createElement('div');
        sidebar.style.width = CONFIG.SIDEBAR_WIDTH + 'px';
        sidebar.style.flexShrink = '0';
        sidebar.style.padding = '12px';
        sidebar.style.borderRight = '1px solid #e5e7eb';
        sidebar.style.background = '#f9fafb';
        sidebar.style.overflow = 'hidden';

        const opEl = document.createElement('div');
        opEl.style.fontSize = '12px';
        opEl.style.fontWeight = '600';
        opEl.style.color = '#1f2937';
        opEl.textContent = item.op;
        sidebar.appendChild(opEl);

        const nomeEl = document.createElement('div');
        nomeEl.style.fontSize = '11px';
        nomeEl.style.color = '#6b7280';
        nomeEl.style.marginTop = '2px';
        nomeEl.style.whiteSpace = 'nowrap';
        nomeEl.style.overflow = 'hidden';
        nomeEl.style.textOverflow = 'ellipsis';
        nomeEl.textContent = item.nome;
        sidebar.appendChild(nomeEl);

        rowDiv.appendChild(sidebar);

        // Timeline content
        const contentDiv = document.createElement('div');
        contentDiv.style.flex = '1';
        contentDiv.style.position = 'relative';
        contentDiv.style.display = 'flex';
        contentDiv.style.overflow = 'auto';
        contentDiv.style.background = 'white';

        // Background days
        for (let i = 0; i < daysCount; i++) {
            const dayBg = document.createElement('div');
            dayBg.style.flex = '1';
            dayBg.style.minWidth = CONFIG.DAY_WIDTH_PX + 'px';
            dayBg.style.borderRight = '1px solid #e5e7eb';
            dayBg.style.background = i % 2 === 0 ? 'white' : '#f9fafb';
            contentDiv.appendChild(dayBg);
        }

        // Bar para a operação
        const leftPercent = ((item.start - startHour) / totalHours) * 100;
        const widthPercent = ((item.end - item.start) / totalHours) * 100;

        const barDiv = document.createElement('div');
        barDiv.style.position = 'absolute';
        barDiv.style.top = '50%';
        barDiv.style.transform = 'translateY(-50%)';
        barDiv.style.left = leftPercent + '%';
        barDiv.style.width = Math.max(widthPercent, 1) + '%';
        barDiv.style.height = '32px';
        barDiv.style.borderRadius = '4px';
        barDiv.style.cursor = 'pointer';
        barDiv.style.padding = '4px 8px';
        barDiv.style.display = 'flex';
        barDiv.style.alignItems = 'center';
        barDiv.style.justifyContent = 'center';
        barDiv.style.color = 'white';
        barDiv.style.fontSize = '10px';
        barDiv.style.fontWeight = '600';
        barDiv.style.whiteSpace = 'nowrap';
        barDiv.style.overflow = 'hidden';
        barDiv.style.textOverflow = 'ellipsis';
        barDiv.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        barDiv.style.transition = 'all 0.2s ease';

        // Cores por status
        const statusColors = {
            'done': '#64748b',
            'setup': '#fb923c',
            'running': '#22c55e',
            'planned': '#3b82f6'
        };
        barDiv.style.background = statusColors[item.status] || '#6b7280';

        barDiv.textContent = item.op;
        barDiv.addEventListener('click', () => this.selecionarItem(item));
        barDiv.addEventListener('mouseenter', () => {
            barDiv.style.transform = 'translateY(-50%) scale(1.05)';
        });
        barDiv.addEventListener('mouseleave', () => {
            barDiv.style.transform = 'translateY(-50%)';
        });

        contentDiv.appendChild(barDiv);
        rowDiv.appendChild(contentDiv);

        container.appendChild(rowDiv);
    }

    /**
     * ETAPA 2 - Nova versão do timeline com Gantt layout apropriado
     * Grid com colunas de dia + escala de horas
     */
    renderTimelineHeader_v2(container, startDate, daysCount) {
        // Container principal (2 linhas: dia + horas)
        const headerDiv = document.createElement('div');
        headerDiv.style.display = 'flex';
        headerDiv.style.borderBottom = '2px solid #0f172a';

        // Sidebar (deixar espaço para operações)
        const sidebarPlaceholder = document.createElement('div');
        sidebarPlaceholder.style.width = CONFIG.SIDEBAR_WIDTH + 'px';
        sidebarPlaceholder.style.flexShrink = '0';
        sidebarPlaceholder.style.borderRight = '2px solid #0f172a';
        sidebarPlaceholder.style.background = '#f8fafc';
        headerDiv.appendChild(sidebarPlaceholder);

        // Grid de dias
        const daysHeaderDiv = document.createElement('div');
        daysHeaderDiv.style.display = 'flex';
        daysHeaderDiv.style.flex = '1';
        daysHeaderDiv.style.background = '#f8fafc';

        for (let dayIdx = 0; dayIdx < daysCount; dayIdx++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(currentDate.getDate() + dayIdx);
            
            const weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            const dayName = weekdays[currentDate.getDay()];
            const dayNum = String(currentDate.getDate()).padStart(2, '0');
            const monthNum = String(currentDate.getMonth() + 1).padStart(2, '0');
            const dayLabel = `${dayName} ${dayNum}/${monthNum}`;

            // Container dia (tem 24h / 4h cada coluna = 6 colunas por dia)
            const dayDiv = document.createElement('div');
            dayDiv.style.display = 'flex';
            dayDiv.style.flex = '1';
            dayDiv.style.minWidth = (CONFIG.DAY_WIDTH_PX) + 'px';
            dayDiv.style.borderRight = '2px solid #0f172a';
            dayDiv.style.flexDirection = 'column';

            // Header do dia (título)
            const dayTitleDiv = document.createElement('div');
            dayTitleDiv.style.padding = '8px 4px';
            dayTitleDiv.style.textAlign = 'center';
            dayTitleDiv.style.fontSize = '12px';
            dayTitleDiv.style.fontWeight = '700';
            dayTitleDiv.style.color = '#0f172a';
            dayTitleDiv.style.borderBottom = '1px solid #cbd5e1';
            dayTitleDiv.style.background = '#e2e8f0';
            dayTitleDiv.textContent = dayLabel;
            dayDiv.appendChild(dayTitleDiv);

            // Escala de horas (0h, 6h, 12h, 18h)
            const hoursDiv = document.createElement('div');
            hoursDiv.style.display = 'flex';
            hoursDiv.style.flex = '1';
            hoursDiv.style.borderBottom = '1px solid #cbd5e1';

            const hourMarkers = [0, 6, 12, 18];
            hourMarkers.forEach((hour, idx) => {
                const markerDiv = document.createElement('div');
                markerDiv.style.flex = '1';
                markerDiv.style.padding = '4px 2px';
                markerDiv.style.fontSize = '10px';
                markerDiv.style.fontWeight = '600';
                markerDiv.style.color = '#475569';
                markerDiv.style.textAlign = 'center';
                markerDiv.style.borderRight = idx < 3 ? '1px solid #cbd5e1' : 'none';
                markerDiv.textContent = `${hour}h`;
                hoursDiv.appendChild(markerDiv);
            });

            dayDiv.appendChild(hoursDiv);
            daysHeaderDiv.appendChild(dayDiv);
        }

        headerDiv.appendChild(daysHeaderDiv);
        container.appendChild(headerDiv);
    }

    renderTimelineRow_v2(container, item, startDate, firstDayIndex) {
        const rowDiv = document.createElement('div');
        rowDiv.style.display = 'flex';
        rowDiv.style.borderBottom = '1px solid #e2e8f0';
        rowDiv.style.minHeight = '110px';
        rowDiv.style.alignItems = 'stretch';
        rowDiv.style.position = 'relative';

        // Sidebar com OP + Nome
        const sidebar = document.createElement('div');
        sidebar.style.width = CONFIG.SIDEBAR_WIDTH + 'px';
        sidebar.style.flexShrink = '0';
        sidebar.style.padding = '8px 12px';
        sidebar.style.borderRight = '2px solid #0f172a';
        sidebar.style.background = '#f8fafc';
        sidebar.style.overflow = 'hidden';
        sidebar.style.display = 'flex';
        sidebar.style.flexDirection = 'column';
        sidebar.style.justifyContent = 'center';
        sidebar.style.zIndex = '1';  // Deixa acima das barras overlay

        const opEl = document.createElement('div');
        opEl.style.fontSize = '12px';
        opEl.style.fontWeight = '700';
        opEl.style.color = '#0f172a';
        opEl.textContent = 'OP ' + item.op;
        sidebar.appendChild(opEl);

        const nomeEl = document.createElement('div');
        nomeEl.style.fontSize = '10px';
        nomeEl.style.color = '#64748b';
        nomeEl.style.marginTop = '2px';
        nomeEl.style.whiteSpace = 'nowrap';
        nomeEl.style.overflow = 'hidden';
        nomeEl.style.textOverflow = 'ellipsis';
        nomeEl.textContent = item.nome.substring(0, 25);
        sidebar.appendChild(nomeEl);

        const tipoEl = document.createElement('div');
        tipoEl.style.fontSize = '9px';
        tipoEl.style.color = '#94a3b8';
        tipoEl.style.marginTop = '2px';
        tipoEl.textContent = item.tipo && item.tipo.toUpperCase() === 'SETUP' ? '🔧 SETUP' : '⚙️ Produção';
        sidebar.appendChild(tipoEl);

        const qtdEl = document.createElement('div');
        qtdEl.style.fontSize = '8px';
        qtdEl.style.color = '#16a34a';
        qtdEl.style.marginTop = '1px';
        qtdEl.textContent = `${item.percentual_cumprimento.toFixed(0)}% (${item.quantidade_realizada.toFixed(0)}/${item.quantidade_prevista.toFixed(0)})`;
        sidebar.appendChild(qtdEl);

        rowDiv.appendChild(sidebar);

        // Container timeline (grid com dias)
        const timelineDiv = document.createElement('div');
        timelineDiv.style.flex = '1';
        timelineDiv.style.display = 'flex';
        timelineDiv.style.position = 'relative';
        timelineDiv.style.overflow = 'auto';
        timelineDiv.style.background = 'white';

        // ETAPA 5B: Obter total de dias no período para cálculo global de horas
        const periodo = this.currentData.periodo;
        const endDate = new Date(periodo.fim);
        const daysInPeriod = Math.ceil((endDate - startDate) / (24 * 60 * 60 * 1000)) + 1;
        const totalHorasOverall = daysInPeriod * 24;  // Total de horas: 11 dias × 24 = 264h

        // Criar grid de fundo (linhas para cada dia)
        for (let dayIdx = 0; dayIdx < daysInPeriod; dayIdx++) {
            const dayColumnBg = document.createElement('div');
            dayColumnBg.style.flex = '1';
            dayColumnBg.style.minWidth = CONFIG.DAY_WIDTH_PX + 'px';
            dayColumnBg.style.borderRight = '2px solid #0f172a';
            dayColumnBg.style.background = dayIdx % 2 === 0 ? 'white' : '#f8fafc';
            dayColumnBg.style.position = 'relative';
            timelineDiv.appendChild(dayColumnBg);
        }

        // ETAPA 5B: Criar overlay de barras (fica sobre o grid)
        const barsOverlay = document.createElement('div');
        barsOverlay.style.position = 'absolute';
        barsOverlay.style.top = '0';
        barsOverlay.style.left = '0';
        barsOverlay.style.right = '0';
        barsOverlay.style.bottom = '0';
        barsOverlay.style.pointerEvents = 'none';  // Deixa clique passar pro grid

        // ETAPA 5B: Calcular posição global da barra (em horas totais, não por dia)
        const leftPercent = (item.start / totalHorasOverall) * 100;
        const widthPercent = ((item.end - item.start) / totalHorasOverall) * 100;

        // RENDERIZAR BARRA PREVISTO UMA VEZ (não repetir por dia)
        const barPrevisto = document.createElement('div');
        barPrevisto.style.position = 'absolute';
        barPrevisto.style.left = leftPercent + '%';
        barPrevisto.style.top = '6px';
        barPrevisto.style.width = Math.max(widthPercent, 1) + '%';
        barPrevisto.style.height = '22px';
        barPrevisto.style.borderRadius = '2px';
        barPrevisto.style.cursor = 'pointer';
        barPrevisto.style.padding = '1px 4px';
        barPrevisto.style.display = 'flex';
        barPrevisto.style.flexDirection = 'column';
        barPrevisto.style.alignItems = 'center';
        barPrevisto.style.justifyContent = 'center';
        barPrevisto.style.color = '#fff';
        barPrevisto.style.fontSize = '7px';
        barPrevisto.style.fontWeight = '600';
        barPrevisto.style.overflow = 'hidden';
        barPrevisto.style.textOverflow = 'ellipsis';
        barPrevisto.style.boxShadow = '0 2px 4px rgba(0,0,0,0.15)';
        barPrevisto.style.transition = 'all 0.2s ease';
        barPrevisto.style.border = '1px solid rgba(255, 255, 255, 0.4)';
        barPrevisto.style.opacity = '0.95';
        barPrevisto.style.pointerEvents = 'auto';

        // ETAPA 5D++ SIMPLES: Apenas cores, sem labels textuais
        if (item.tipo && item.tipo.toUpperCase() === 'SETUP') {
            barPrevisto.style.background = 'linear-gradient(45deg, #f97316 25%, #fb923c 25%, #fb923c 50%, #f97316 50%, #f97316 75%, #fb923c 75%, #fb923c)';
            barPrevisto.style.backgroundSize = '10px 10px';
            barPrevisto.textContent = '';  // Sem rótulo
            barPrevisto.title = `Setup: ${this.hourToTime(item.start)} - ${this.hourToTime(item.end)}`;
        } else {
            barPrevisto.style.background = '#f97316';
            barPrevisto.textContent = '';  // Sem rótulo
            barPrevisto.title = `OP ${item.op}: ${this.hourToTime(item.start)} - ${this.hourToTime(item.end)}`;
        }

        barPrevisto.addEventListener('click', () => this.selecionarItem(item));
        barPrevisto.addEventListener('mouseenter', () => {
            barPrevisto.style.opacity = '1';
            barPrevisto.style.boxShadow = '0 4px 8px rgba(0,0,0,0.25)';
            barPrevisto.style.zIndex = '10';
            const tooltipText = `OP${item.op} | ${item.duracao_horas.toFixed(1)}h | ${item.quantidade_prevista.toFixed(0)}un`;
            this.createTooltip(barPrevisto, tooltipText);
        });
        barPrevisto.addEventListener('mouseleave', () => {
            barPrevisto.style.opacity = '0.95';
            barPrevisto.style.boxShadow = '0 2px 4px rgba(0,0,0,0.15)';
            barPrevisto.style.zIndex = 'auto';
            this.hideTooltip(barPrevisto);
        });

        barsOverlay.appendChild(barPrevisto);

        // RENDERIZAR BARRA REALIZADO UMA VEZ (não repetir por dia)
        if (item.quantidade_realizada > 0) {
            const cumprimentoRatio = item.percentual_cumprimento / 100;
            const widthRealizadoPercent = widthPercent * cumprimentoRatio;

            const barRealizado = document.createElement('div');
            barRealizado.style.position = 'absolute';
            barRealizado.style.left = leftPercent + '%';
            barRealizado.style.top = '32px';
            barRealizado.style.width = Math.max(widthRealizadoPercent, 1) + '%';
            barRealizado.style.height = '22px';
            barRealizado.style.borderRadius = '2px';
            barRealizado.style.cursor = 'pointer';
            barRealizado.style.padding = '1px 4px';
            barRealizado.style.display = 'flex';
            barRealizado.style.flexDirection = 'column';
            barRealizado.style.alignItems = 'center';
            barRealizado.style.justifyContent = 'center';
            barRealizado.style.color = '#fff';
            barRealizado.style.fontSize = '7px';
            barRealizado.style.fontWeight = '600';
            barRealizado.style.overflow = 'hidden';
            barRealizado.style.textOverflow = 'ellipsis';
            barRealizado.style.boxShadow = '0 2px 4px rgba(0,0,0,0.15)';
            barRealizado.style.transition = 'all 0.2s ease';
            barRealizado.style.border = '1px solid rgba(255, 255, 255, 0.3)';
            barRealizado.style.opacity = '0.9';
            barRealizado.style.pointerEvents = 'auto';

            if (item.tipo && item.tipo.toUpperCase() === 'SETUP') {
                barRealizado.style.background = 'linear-gradient(45deg, #16a34a 25%, #22c55e 25%, #22c55e 50%, #16a34a 50%, #16a34a 75%, #22c55e 75%, #22c55e)';
                barRealizado.style.backgroundSize = '10px 10px';
                barRealizado.textContent = '';
                barRealizado.title = `Setup realizado: ${item.percentual_cumprimento.toFixed(1)}%`;
            } else {
                barRealizado.style.background = '#16a34a';
                barRealizado.textContent = '';
                barRealizado.title = `Realizado: ${item.quantidade_realizada.toFixed(0)}un / ${item.quantidade_prevista.toFixed(0)}un (${item.percentual_cumprimento.toFixed(1)}%)`;
            }

            barRealizado.addEventListener('click', () => this.selecionarItem(item));
            barRealizado.addEventListener('mouseenter', () => {
                barRealizado.style.opacity = '1';
                barRealizado.style.boxShadow = '0 4px 8px rgba(0,0,0,0.25)';
                barRealizado.style.zIndex = '10';
                const tooltipText = `OP${item.op} | ${item.quantidade_realizada.toFixed(0)}/${item.quantidade_prevista.toFixed(0)}un (${item.percentual_cumprimento.toFixed(0)}%)`;
                this.createTooltip(barRealizado, tooltipText);
            });
            barRealizado.addEventListener('mouseleave', () => {
                barRealizado.style.opacity = '0.9';
                barRealizado.style.boxShadow = '0 2px 4px rgba(0,0,0,0.15)';
                barRealizado.style.zIndex = 'auto';
                this.hideTooltip(barRealizado);
            });

            barsOverlay.appendChild(barRealizado);
        } else {
            // Barra vazia para não iniciado
            const barVazio = document.createElement('div');
            barVazio.style.position = 'absolute';
            barVazio.style.left = leftPercent + '%';
            barVazio.style.top = '32px';
            barVazio.style.width = Math.max(widthPercent, 1) + '%';
            barVazio.style.height = '22px';
            barVazio.style.borderRadius = '2px';
            barVazio.style.border = '2px dashed #cbd5e1';
            barVazio.style.background = 'transparent';
            barVazio.style.opacity = '0.5';
            barsOverlay.appendChild(barVazio);
        }

        // Adicionar overlay ao timeline
        timelineDiv.appendChild(barsOverlay);
        rowDiv.appendChild(timelineDiv);
        container.appendChild(rowDiv);
    }

    selecionarItem(item) {
        this.selectedItem = item;
        document.getElementById('detailContainer').style.display = 'block';

        const statusLabels = {
            'done': 'Concluída',
            'setup': 'Setup',
            'running': 'Em Execução',
            'planned': 'Planejada'
        };

        const formatNum = (n) => new Intl.NumberFormat('pt-BR', {
            maximumFractionDigits: 2
        }).format(n);

        document.getElementById('detailOp').textContent = item.op;
        document.getElementById('detailNome').textContent = item.nome;
        document.getElementById('detailTipo').textContent = item.tipo === 'producao' ? 'Produção' : 'Setup';
        document.getElementById('detailQtdPrev').textContent = formatNum(item.quantidade_prevista) + ' un';
        document.getElementById('detailQtdReal').textContent = formatNum(item.quantidade_realizada) + ' un';
        document.getElementById('detailStatus').textContent = statusLabels[item.status] || item.status;

        // Horário
        const inicio = this.hourToTime(item.start);
        const fim = this.hourToTime(item.end);
        document.getElementById('detailHorario').textContent = `${inicio} → ${fim}`;

        // Duração
        const duracao = item.end - item.start;
        const horas = Math.floor(duracao);
        const minutos = Math.round((duracao - horas) * 60);
        document.getElementById('detailDuracao').textContent = `${horas}h ${minutos}m`;
        
        // Nota se foi agregada (ETAPA 5D+)
        const notaAgregacao = document.querySelector('.detail-nota-agregacao') || 
            (() => {
                const nota = document.createElement('div');
                nota.className = 'detail-nota-agregacao';
                nota.style.gridColumn = '1 / -1';
                nota.style.padding = '8px 12px';
                nota.style.background = '#fff7ed';
                nota.style.borderRadius = '4px';
                nota.style.fontSize = '11px';
                nota.style.color = '#92400e';
                nota.style.marginTop = '8px';
                nota.style.borderLeft = '3px solid #f97316';
                document.querySelector('.detail-panel').appendChild(nota);
                return nota;
            })();
        
        if (item._agregada) {
            notaAgregacao.innerHTML = `<strong>📊 Operações Agrupadas</strong><br>Esta operação representa múltiplas execuções agregadas para melhor visualização.`;
            notaAgregacao.style.display = 'block';
        } else {
            notaAgregacao.style.display = 'none';
        }
    }

    aplicarFiltros() {
        this.carregarGantt();
    }

    restaurarFiltros() {
        if (this.currentProgramacao) {
            document.getElementById('dataInicio').value = this.currentProgramacao.data_inicio;
            document.getElementById('dataFim').value = this.currentProgramacao.data_fim;
            this.carregarGantt();
        }
    }

    selecionarSemana(weekNumber) {
        // Atualizar botões ativos
        document.querySelectorAll('.semana-btn').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.week) === weekNumber);
        });
    }

    semanaAnterior() {
        this.semanal = Math.max(1, this.semanal - 1);
    }

    proximaSemana() {
        this.semanal = Math.min(53, this.semanal + 1);
    }

    gerarDiasTimeline(startHour, daysCount) {
        const labels = [];
        const weekdayMap = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        const startDate = new Date(this.currentData.periodo.inicio);
        const startDayOfWeek = Math.floor(startHour / CONFIG.HOURS_PER_DAY);

        for (let i = 0; i < daysCount; i++) {
            const d = new Date(startDate);
            d.setDate(d.getDate() + i);
            const weekday = weekdayMap[d.getDay()];
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            labels.push(`${weekday} ${day}/${month}`);
        }

        return labels;
    }

    hourToTime(hour) {
        const totalMinutes = Math.round(hour * 60);
        const hh = String(Math.floor((totalMinutes % 1440) / 60)).padStart(2, '0');
        const mm = String(totalMinutes % 60).padStart(2, '0');
        return `${hh}:${mm}`;
    }

    /**
     * Converter horas decimais para string de duração (ex: 1.5h → "01:30")
     */
    horaToMinutos(horas) {
        const totalMinutos = Math.round(horas * 60);
        const h = String(Math.floor(totalMinutos / 60)).padStart(2, '0');
        const m = String(totalMinutos % 60).padStart(2, '0');
        return `${h}:${m}`;
    }

    /**
     * ETAPA 4: Criar e mostrar tooltip ao hover
     */
    createTooltip(element, text) {
        let tooltip = element.querySelector('.tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.style.position = 'absolute';
            tooltip.style.bottom = '100%';
            tooltip.style.left = '50%';
            tooltip.style.transform = 'translateX(-50%)';
            tooltip.style.marginBottom = '8px';
            tooltip.style.background = '#0f172a';
            tooltip.style.color = 'white';
            tooltip.style.padding = '8px 12px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '11px';
            tooltip.style.fontWeight = '500';
            tooltip.style.whiteSpace = 'nowrap';
            tooltip.style.zIndex = '1000';
            tooltip.style.pointerEvents = 'none';
            tooltip.style.opacity = '0';
            tooltip.style.transition = 'opacity 0.2s ease';
            tooltip.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
            element.style.position = 'relative';
            element.appendChild(tooltip);
        }
        tooltip.textContent = text;
        tooltip.style.opacity = '1';
        return tooltip;
    }

    hideTooltip(element) {
        const tooltip = element.querySelector('.tooltip');
        if (tooltip) {
            tooltip.style.opacity = '0';
        }
    }

    getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }

    mostrarMensagemSucesso() {
        const m = this.currentData.metricas;
        const msg = `Dados carregados: ${m.producoes} operações, ${m.setups} setups • Previsto: ${m.total_previsto.toFixed(0)} | Realizado: ${m.total_realizado.toFixed(0)}`;
        this.addMessage(msg, 'success');
    }
}

// Inicializar ao carregar página
document.addEventListener('DOMContentLoaded', () => {
    new SequenciamentoGrafico();
});
