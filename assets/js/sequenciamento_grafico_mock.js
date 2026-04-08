/**
 * Sequenciamento Grafico - Layout baseado no mock (React)
 * Usa as APIs:
 * - /api/programacoes_historico.php
 * - /api/sequenciamento_gantt.php
 */

const PCP_CONFIG = {
  API_PROGRAMACOES: "/api/programacoes_historico.php",
  API_GANTT: "/api/sequenciamento_gantt.php",
  HOURS_PER_DAY: 24,
  SIDEBAR_W: 180,
  ROW_HEIGHT: 56,
};

class PCPGrafico {
  constructor() {
    this.currentProgramacao = null;
    this.currentData = null;
    this.selectedItem = null;
    this._dayLabels = [];
    this._totalDays = 0;
    this._totalHours = 0;
    this._nowHour = null;

    this.init();
  }

  async init() {
    this.setupEventListeners();
    await this.carregarProgramacoes();
  }

  setupEventListeners() {
    const programacaoSelect = document.getElementById("programacaoSelect");
    const aplicarBtn = document.getElementById("aplicarBtn");
    const restaurarBtn = document.getElementById("restaurarBtn");

    programacaoSelect.addEventListener("change", () => this.onProgramacaoChange());
    aplicarBtn.addEventListener("click", () => this.aplicarFiltros());
    restaurarBtn.addEventListener("click", () => this.restaurarFiltros());

    if (!this._resizeBound) {
      this._resizeBound = true;
      window.addEventListener("resize", () => {
        if (!this.currentData) return;
        this.posicionarMarcadorAgora();
      });
    }
  }

  addMessage(message, type = "info") {
    const container = document.getElementById("messagesContainer");
    const el = document.createElement("div");
    el.className = `message ${type}`;
    el.textContent = message;
    container.appendChild(el);
    setTimeout(() => el.remove(), 5000);
  }

  showLoading(show) {
    const mainContent = document.getElementById("mainContent");
    const gridContainer = document.getElementById("gridContainer");
    if (!mainContent || !gridContainer) return;

    if (show) {
      mainContent.style.display = "none";
      gridContainer.style.display = "none";
    }
  }

  pad2(n) {
    return String(n).padStart(2, "0");
  }

  formatHour(hour) {
    const totalMinutes = Math.round(hour * 60);
    const hh = this.pad2(Math.floor((totalMinutes % 1440) / 60));
    const mm = this.pad2(totalMinutes % 60);
    return `${hh}:${mm}`;
  }

  formatDayLabel(dateObj) {
    const weekdays = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "SÃ¡b"];
    const dayName = weekdays[dateObj.getDay()];
    const dd = this.pad2(dateObj.getDate());
    const mm = this.pad2(dateObj.getMonth() + 1);
    return `${dayName} ${dd}/${mm}`;
  }

  getDayLabelByHour(hour) {
    const idx = Math.max(0, Math.min(this._dayLabels.length - 1, Math.floor(hour / 24)));
    return this._dayLabels[idx] || "";
  }

  getStatusLabel(status) {
    if (status === "running") return "Em producao";
    if (status === "done") return "Concluida";
    if (status === "setup") return "Setup";
    return "Programada";
  }

  getBarClass(item) {
    if (String(item.tipo || "").toLowerCase() === "setup") return "pcp-bar--setup";
    if (item.status === "running") return "pcp-bar--running";
    if (item.status === "done") return "pcp-bar--done";
    return "pcp-bar--planned";
  }

  getWeekNumber(date) {
    // ISO week number (UTC-based, suficiente para separacao por dia)
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
  }

  buildDayLabels(startDate, totalDays) {
    const labels = [];
    for (let i = 0; i < totalDays; i++) {
      const d = new Date(startDate);
      d.setDate(d.getDate() + i);
      labels.push(this.formatDayLabel(d));
    }
    return labels;
  }

  buildWeekBands(startDate, totalDays) {
    const bands = [];
    for (let i = 0; i < totalDays; i++) {
      const d = new Date(startDate);
      d.setDate(d.getDate() + i);
      const w = this.getWeekNumber(d);
      const last = bands[bands.length - 1];
      if (!last || last.week !== w) {
        bands.push({ week: w, span: 1 });
      } else {
        last.span += 1;
      }
    }
    return bands;
  }

  computeNowHour(startDate, totalHours) {
    const now = new Date();
    const raw = (now - startDate) / (60 * 60 * 1000);
    if (!Number.isFinite(raw)) return null;
    return Math.max(0, Math.min(totalHours, raw));
  }

  buildDayGradient(totalDays, startDate) {
    // Mais fiel ao PDF: alternar faixas por SEMANA (nao por dia).
    const stops = [];
    const step = 100 / totalDays;
    const weekIndex = {};
    let nextWeekIdx = 0;

    for (let i = 0; i < totalDays; i++) {
      const d = new Date(startDate);
      d.setDate(d.getDate() + i);
      const w = this.getWeekNumber(d);
      if (weekIndex[w] === undefined) {
        weekIndex[w] = nextWeekIdx;
        nextWeekIdx += 1;
      }

      const bandIdx = weekIndex[w];
      const start = (i * step).toFixed(6);
      const end = ((i + 1) * step).toFixed(6);
      const color = bandIdx % 2 === 0 ? "rgba(251, 113, 133, 0.18)" : "rgba(96, 165, 250, 0.18)";
      stops.push(`${color} ${start}%`, `${color} ${end}%`);
    }

    return `linear-gradient(90deg, ${stops.join(", ")})`;
  }

  buildGridLines(totalDays) {
    // Linhas verticais para cada dia (mais "gantt tradicional")
    const step = 100 / totalDays;
    const stepPct = `${step}%`;
    return `repeating-linear-gradient(90deg, rgba(148, 163, 184, 0.45) 0px, rgba(148, 163, 184, 0.45) 1px, transparent 1px, transparent ${stepPct})`;
  }

  agruparTimelineParaGantt(timeline) {
    // Objetivo: remover quebras por dia/segmentos e ficar semelhante ao PDF
    // (barras mais longas e uma linha por "bloco" de operacao).
    const byKey = {};

    timeline.forEach((item) => {
      const op = String(item.op || "");
      const nome = String(item.nome || "");
      const tipo = String(item.tipo || "");
      const key = `${op}|${nome}|${tipo}`;
      if (!byKey[key]) byKey[key] = [];
      byKey[key].push(item);
    });

    const gapThresholdHours = 0.25; // 15 min: separa blocos nao contiguos
    const groups = [];

    Object.keys(byKey).forEach((key) => {
      const items = byKey[key].slice().sort((a, b) => a.start - b.start);
      let current = null;
      let idx = 0;

      const flush = () => {
        if (!current) return;
        const isSetup = String(current.tipo || "").toLowerCase() === "setup";
        const prev = current.quantidade_prevista || 0;
        const real = current.quantidade_realizada || 0;

        let status = "planned";
        if (isSetup) {
          status = "setup";
        } else if (real > 0) {
          status = real >= prev && prev > 0 ? "done" : "running";
        }

        groups.push({
          ...current,
          id: `${String(current.id || "x")}-g${idx}`,
          status,
        });
        idx += 1;
        current = null;
      };

      items.forEach((item) => {
        const isSetup = String(item.tipo || "").toLowerCase() === "setup";
        const start = Number(item.start) || 0;
        const end = Number(item.end) || start;

        if (!current) {
          current = {
            id: item.id,
            op: item.op,
            nome: item.nome,
            tipo: item.tipo,
            start,
            end,
            quantidade_prevista: Number(item.quantidade_prevista) || 0,
            quantidade_realizada: Number(item.quantidade_realizada) || 0,
            _isSetup: isSetup,
          };
          return;
        }

        // Se houver um gap grande, criar novo grupo (evita colar blocos separados).
        if (start > (current.end + gapThresholdHours)) {
          flush();
          current = {
            id: item.id,
            op: item.op,
            nome: item.nome,
            tipo: item.tipo,
            start,
            end,
            quantidade_prevista: Number(item.quantidade_prevista) || 0,
            quantidade_realizada: Number(item.quantidade_realizada) || 0,
            _isSetup: isSetup,
          };
          return;
        }

        current.start = Math.min(current.start, start);
        current.end = Math.max(current.end, end);
        current.quantidade_prevista += Number(item.quantidade_prevista) || 0;
        // Realizado na API vem agregado por OP; usar MAX para nao duplicar.
        current.quantidade_realizada = Math.max(current.quantidade_realizada, Number(item.quantidade_realizada) || 0);
      });

      flush();
    });

    groups.sort((a, b) => a.start - b.start);
    return groups;
  }

  async carregarProgramacoes() {
    try {
      const response = await fetch(PCP_CONFIG.API_PROGRAMACOES);
      const data = await response.json();

      if (!data.sucesso) {
        throw new Error(data.erro || "Erro ao carregar programacoes");
      }

      const select = document.getElementById("programacaoSelect");
      select.innerHTML = '<option value="">Selecione uma programacao...</option>';

      data.programacoes.forEach((prog) => {
        const option = document.createElement("option");
        option.value = prog.id;
        option.textContent = prog.label;
        option.dataset.data = JSON.stringify(prog);
        select.appendChild(option);
      });

      if (data.programacoes.length > 0) {
        select.value = data.programacoes[0].id;
        await this.onProgramacaoChange();
      }
    } catch (err) {
      this.addMessage(`Erro ao carregar programacoes: ${err.message}`, "error");
    }
  }

  async onProgramacaoChange() {
    const select = document.getElementById("programacaoSelect");
    const prgId = select.value;
    if (!prgId) return;

    this.currentProgramacao = JSON.parse(select.options[select.selectedIndex].dataset.data);
    document.getElementById("dataInicio").value = this.currentProgramacao.data_inicio;
    document.getElementById("dataFim").value = this.currentProgramacao.data_fim;

    await this.carregarGantt();
  }

  async carregarGantt() {
    if (!this.currentProgramacao) return;

    this.showLoading(true);

    try {
      const prgId = this.currentProgramacao.id;
      const dataInicio = document.getElementById("dataInicio").value;
      const dataFim = document.getElementById("dataFim").value;

      const url = new URL(PCP_CONFIG.API_GANTT, window.location.origin);
      url.searchParams.set("programacao_id", prgId);
      if (dataInicio) url.searchParams.set("data_inicio", dataInicio);
      if (dataFim) url.searchParams.set("data_fim", dataFim);

      const response = await fetch(url.toString());
      const data = await response.json();
      if (!data.sucesso) throw new Error(data.erro || "Erro ao carregar dados");

      this.currentData = data;
      this.renderizar();
      this.addMessage("Dados carregados com sucesso.", "success");
    } catch (err) {
      this.addMessage(`Erro ao carregar grafico: ${err.message}`, "error");
    } finally {
      this.showLoading(false);
    }
  }

  aplicarFiltros() {
    this.carregarGantt();
  }

  restaurarFiltros() {
    if (!this.currentProgramacao) return;
    document.getElementById("dataInicio").value = this.currentProgramacao.data_inicio;
    document.getElementById("dataFim").value = this.currentProgramacao.data_fim;
    this.carregarGantt();
  }

  renderizar() {
    const data = this.currentData;
    const timelineRaw = (data.timeline || []).slice();
    const timeline = this.agruparTimelineParaGantt(timelineRaw);
    if (timeline.length === 0) return;

    const startDate = new Date(`${data.periodo.inicio}T00:00:00`);
    const endDate = new Date(`${data.periodo.fim}T00:00:00`);
    const totalDays = Math.ceil((endDate - startDate) / (24 * 60 * 60 * 1000)) + 1;
    const totalHours = totalDays * 24;

    this._totalDays = totalDays;
    this._totalHours = totalHours;
    this._dayLabels = this.buildDayLabels(startDate, totalDays);
    this._nowHour = this.computeNowHour(startDate, totalHours);

    if (!this.selectedItem || !timeline.find((t) => String(t.id) === String(this.selectedItem.id))) {
      this.selectedItem = timeline[0];
    }

    this.renderTopo(timeline);
    this.renderCabecalho(totalDays, startDate);
    this.renderLinhas(timeline, totalDays, totalHours, startDate);
    this.renderDetalhes(this.selectedItem);

    document.getElementById("mainContent").style.display = "block";
    document.getElementById("gridContainer").style.display = "grid";
  }

  renderTopo(timeline) {
    const data = this.currentData;
    const ordens = timeline.filter((i) => String(i.tipo || "").toLowerCase() !== "setup");
    const setups = timeline.filter((i) => String(i.tipo || "").toLowerCase() === "setup");

    const first = timeline[0];
    const last = timeline[timeline.length - 1];

    document.getElementById("metricLinha").textContent = data.programacao?.linha || "-";
    document.getElementById("metricInicio").textContent = `${this.getDayLabelByHour(first.start)} ${this.formatHour(first.start)}`;
    document.getElementById("metricFim").textContent = `${this.getDayLabelByHour(last.end)} ${this.formatHour(last.end)}`;
    document.getElementById("metricOrdens").textContent = `${ordens.length} / ${setups.length}`;

    const currentOp = (this._nowHour === null)
      ? null
      : (ordens.find((i) => i.start <= this._nowHour && i.end >= this._nowHour) || null);
    const nextOp = (this._nowHour === null)
      ? null
      : (ordens.find((i) => i.start > this._nowHour) || null);

    document.getElementById("resumoAgora").textContent = currentOp ? currentOp.nome : "Nenhuma OP em execucao";

    const resumoInicioFim = document.getElementById("resumoInicioFim");
    if (currentOp) {
      resumoInicioFim.textContent = `Inicio: ${this.getDayLabelByHour(currentOp.start)} ${this.formatHour(currentOp.start)} - Fim: ${this.getDayLabelByHour(currentOp.end)} ${this.formatHour(currentOp.end)}`;
    } else {
      resumoInicioFim.textContent = "";
    }

    const resumoProxima = document.getElementById("resumoProxima");
    if (nextOp) {
      resumoProxima.innerHTML = `Proxima ordem: <b>${nextOp.nome}</b>`;
    } else {
      resumoProxima.textContent = "";
    }
  }

  renderCabecalho(totalDays, startDate) {
    // Semana
    const weekBandsEl = document.getElementById("weekBands");
    weekBandsEl.innerHTML = "";
    const bands = this.buildWeekBands(startDate, totalDays);
    bands.forEach((b) => {
      const el = document.createElement("div");
      el.className = "pcp-weekband";
      el.textContent = `Semana ${b.week}`;
      el.style.width = `${(b.span / totalDays) * 100}%`;
      weekBandsEl.appendChild(el);
    });

    // Dias
    const dayHeadersEl = document.getElementById("dayHeaders");
    dayHeadersEl.innerHTML = "";
    this._dayLabels.forEach((label) => {
      const el = document.createElement("div");
      el.className = "pcp-daycell";
      el.textContent = label;
      el.style.width = `${100 / totalDays}%`;
      dayHeadersEl.appendChild(el);
    });
  }

  renderLinhas(timeline, totalDays, totalHours, startDate) {
    const rowsEl = document.getElementById("timelineRows");
    rowsEl.innerHTML = "";

    const weekGradient = this.buildDayGradient(totalDays, startDate);
    const gridLines = this.buildGridLines(totalDays);

    // Gantt tradicional: poucas "raias" (ex.: Producao e Setup) e varias barras por raia.
    const lanes = [
      {
        key: "producao",
        label: "Producao",
        sublabel: "Ordens",
        items: timeline.filter((i) => String(i.tipo || "").toLowerCase() !== "setup"),
      },
      {
        key: "setup",
        label: "Setup",
        sublabel: "Setups",
        items: timeline.filter((i) => String(i.tipo || "").toLowerCase() === "setup"),
      },
    ];

    lanes.forEach((lane) => {
      const row = document.createElement("div");
      row.className = "pcp-row";

      const left = document.createElement("div");
      left.className = "pcp-row-left";

      const op = document.createElement("div");
      op.className = "pcp-row-op";
      op.textContent = lane.label;

      const nome = document.createElement("div");
      nome.className = "pcp-row-nome";
      nome.textContent = `${lane.sublabel}: ${lane.items.length}`;

      left.appendChild(op);
      left.appendChild(nome);

      const right = document.createElement("div");
      right.className = "pcp-row-right";
      right.style.flex = "1";
      // Camadas: faixa por semana + linhas verticais por dia
      right.style.backgroundImage = `${weekGradient}, ${gridLines}`;

      lane.items.forEach((item) => {
        const leftPct = (item.start / totalHours) * 100;
        const widthPct = ((item.end - item.start) / totalHours) * 100;

        const btn = document.createElement("button");
        btn.className = `pcp-bar ${this.getBarClass(item)} pcp-bar--compact`;
        if (this.selectedItem && String(this.selectedItem.id) === String(item.id)) {
          btn.className += " pcp-bar--selected";
        }
        btn.style.left = `${leftPct}%`;
        btn.style.width = `${Math.max(widthPct, 0.75)}%`;
        btn.style.minWidth = "18px";

        const opLabel = (String(item.tipo || "").toLowerCase() === "setup") ? "SETUP" : String(item.op || "");
        btn.title = `${opLabel} - ${item.nome || ""}`;

        // Progresso: realizado / previsto (quando for producao)
        const isSetup = (String(item.tipo || "").toLowerCase() === "setup");
        const prev = Number(item.quantidade_prevista || 0);
        const real = Number(item.quantidade_realizada || 0);
        const pct = isSetup
          ? 0
          : (prev > 0 ? Math.max(0, Math.min(100, (real / prev) * 100)) : 0);

        const progress = document.createElement("div");
        progress.className = "pcp-bar-progress";
        progress.style.width = `${pct}%`;
        btn.appendChild(progress);

        const label = document.createElement("div");
        label.className = "pcp-bar-label";
        label.textContent = opLabel;
        btn.appendChild(label);

        btn.addEventListener("click", () => {
          this.selectedItem = item;
          this.renderLinhas(timeline, totalDays, totalHours, startDate);
          this.renderDetalhes(item);
        });

        right.appendChild(btn);
      });

      row.appendChild(left);
      row.appendChild(right);
      rowsEl.appendChild(row);
    });

    this.posicionarMarcadorAgora();
  }

  posicionarMarcadorAgora() {
    const nowMarkerEl = document.getElementById("nowMarker");
    const rowsWrapperEl = document.getElementById("timelineRowsWrapper");
    if (!nowMarkerEl || !rowsWrapperEl) return;

    if (this._nowHour === null) {
      nowMarkerEl.style.display = "none";
      return;
    }

    const pct = this._totalHours > 0 ? (this._nowHour / this._totalHours) : 0;
    const wrapperWidth = rowsWrapperEl.scrollWidth;
    const timeAreaWidth = Math.max(0, wrapperWidth - PCP_CONFIG.SIDEBAR_W);
    const leftPx = PCP_CONFIG.SIDEBAR_W + (timeAreaWidth * pct);

    nowMarkerEl.style.left = `${leftPx}px`;
    nowMarkerEl.style.display = "block";
  }

  renderDetalhes(item) {
    if (!item) return;
    const isSetup = String(item.tipo || "").toLowerCase() === "setup";
    document.getElementById("detailProduto").textContent = item.nome || "-";
    document.getElementById("detailOp").textContent = isSetup ? "SETUP" : String(item.op || "-");
    document.getElementById("detailTipo").textContent = isSetup ? "Setup" : "OP";
    document.getElementById("detailStatus").textContent = this.getStatusLabel(item.status);
    document.getElementById("detailInicio").textContent = `${this.getDayLabelByHour(item.start)} ${this.formatHour(item.start)}`;
    document.getElementById("detailFim").textContent = `${this.getDayLabelByHour(item.end)} ${this.formatHour(item.end)}`;
    document.getElementById("detailDuracao").textContent = `${(item.end - item.start).toFixed(2)} h`;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new PCPGrafico();
});

