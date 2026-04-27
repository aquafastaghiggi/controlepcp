const config = window.__GANTT_CONFIG__ || {};
const apiPrefix = config.apiPrefix || "/api/v1";
const defaultProgramacaoId = Number(config.defaultProgramacaoId || 0) || null;

const state = {
  programacoes: [],
  payload: null,
  selectedLineKey: null,
  selectedProgramacaoId: defaultProgramacaoId,
  selectedOp: null,
  filter: "all",
  search: "",
  programacaoPayloadCache: {},
  opDetailCache: {},
  detailRequestToken: 0,
  axis: null,
  ganttScrollSyncing: false,
  ganttScrollLastTop: 0,
  ganttScrollLastLeft: 0,
  ganttScrollCarry: 0,
};

const els = {};

function $(id) {
  return document.getElementById(id);
}

function setHidden(el, hidden) {
  if (!el) return;
  if (hidden) {
    el.setAttribute("hidden", "");
  } else {
    el.removeAttribute("hidden");
  }
}

function setDrawerOpen(drawerEl, open) {
  if (!drawerEl) return;
  drawerEl.classList.toggle("is-open", open);
  drawerEl.setAttribute("aria-hidden", open ? "false" : "true");
}

function syncBackdrop() {
  const anyOpen = Boolean(
    els.programDrawer?.classList.contains("is-open") ||
    els.inspector?.classList.contains("is-open") ||
    els.summaryDrawer?.classList.contains("is-open")
  );
  setHidden(els.drawerBackdrop, !anyOpen);
  document.body.classList.toggle("has-drawer-open", anyOpen);
}

function openProgramDrawer() {
  setDrawerOpen(els.programDrawer, true);
  setDrawerOpen(els.inspector, false);
  setDrawerOpen(els.summaryDrawer, false);
  syncBackdrop();
}

function closeProgramDrawer() {
  setDrawerOpen(els.programDrawer, false);
  syncBackdrop();
}

function openInspectorDrawer() {
  setDrawerOpen(els.programDrawer, false);
  setDrawerOpen(els.summaryDrawer, false);
  setDrawerOpen(els.inspector, true);
  syncBackdrop();
}

function closeInspectorDrawer() {
  setDrawerOpen(els.inspector, false);
  syncBackdrop();
}

function openSummaryDrawer() {
  setDrawerOpen(els.programDrawer, false);
  setDrawerOpen(els.inspector, false);
  setDrawerOpen(els.summaryDrawer, true);
  syncBackdrop();
}

function closeSummaryDrawer() {
  setDrawerOpen(els.summaryDrawer, false);
  syncBackdrop();
}

function setTextContent(el, value) {
  if (el) {
    el.textContent = value;
  }
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function displayValue(value, fallback = "-") {
  if (value === null || value === undefined || value === "") return fallback;
  if (typeof value === "object") {
    if (Array.isArray(value)) {
      return value.map((item) => displayValue(item, "")).filter(Boolean).join(", ") || fallback;
    }
    return displayValue(value.label ?? value.nome ?? value.nomeParada ?? value.title ?? value.text ?? value.value ?? value.chave ?? value.key ?? value.codigo ?? value.descricao ?? value.id, fallback);
  }
  return String(value);
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function parseDate(value) {
  if (!value) return null;
  if (value instanceof Date) return value;
  const raw = String(value).trim();
  const dmyMatch = raw.match(/^(\d{2})-(\d{2})-(\d{4})(?:\s+(\d{2}):(\d{2}))?$/);
  if (dmyMatch) {
    const [, dd, mm, yyyy, hh = "00", min = "00"] = dmyMatch;
    return new Date(Number(yyyy), Number(mm) - 1, Number(dd), Number(hh), Number(min), 0, 0);
  }
  const parsed = new Date(raw);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatDateKey(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return "";
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function formatDateShort(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return "-";
  return new Intl.DateTimeFormat("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function formatDateLong(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return "-";
  return new Intl.DateTimeFormat("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function formatNumber(value, digits = 2) {
  const num = Number(value ?? 0);
  return new Intl.NumberFormat("pt-BR", {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(num);
}

function formatPercent(value, digits = 1) {
  return `${formatNumber(Number(value ?? 0), digits)}%`;
}

function formatClock(value) {
  if (value === null || value === undefined || value === "") return "-";
  const total = Math.max(0, Math.round(Number(value)));
  const hours = Math.floor(total / 60);
  const minutes = total % 60;
  return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
}

function normalizeLineKey(value) {
  return String(value ?? "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "")
    .replace(/\//g, "")
    .replace(/\./g, "")
    .replace(/-/g, "");
}

function normalizeStatusKey(value) {
  return String(value ?? "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "_")
    .replace(/[^a-z0-9_]/g, "");
}

function statusClassForKey(key) {
  switch (normalizeStatusKey(key)) {
    case "ok":
      return "status-ok";
    case "atencao":
      return "status-atencao";
    case "critico":
      return "status-critico";
    case "divergente":
      return "status-divergente";
    case "sem_realizado":
    case "sem-realizado":
      return "status-no-realized";
    case "acima":
      return "status-atencao";
    case "abaixo":
    case "no_prazo":
      return "status-ok";
    case "sem_evento":
      return "status-no-realized";
    case "plano":
    case "planned":
    case "sem_setup":
      return "status-planned";
    default:
      return `status-${normalizeStatusKey(key) || "ok"}`;
  }
}

function getProgramacaoId(item) {
  return item?.prg_id ?? item?.id ?? null;
}

function getProgramacaoNumero(item) {
  return item?.prg_numero_op || item?.numero || item?.op || item?.prg_id || item?.id || "-";
}

function getProgramacaoLineLabel(item) {
  return item?.lin_nome || item?.lin_codigo || item?.linha || item?.linha_excel_dominante || "Sem linha";
}

function getProgramacaoLineKey(item) {
  return normalizeLineKey(
    item?.linha_excel_dominante || item?.lin_codigo || item?.linha || getProgramacaoLineLabel(item)
  );
}

function getProgramacaoSearchText(item) {
  return [
    getProgramacaoNumero(item),
    item?.lin_codigo,
    item?.lin_nome,
    item?.linha_excel_dominante,
    item?.prg_status,
    item?.total_itens,
  ]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();
}

function getProgramacaoLabelFromPayload(payload) {
  const id = payload?.programacao?.id ?? payload?.programacao?.prg_id ?? payload?.linha?.programacao_id ?? "-";
  return `Programacao ${id}`;
}

function getOpCode(op) {
  return String(op?.op ?? op?.codigo ?? op?.codigoPerformance ?? "-");
}

function getOpSku(op) {
  return String(op?.sku ?? op?.item?.codigo ?? op?.item?.codItem ?? op?.codigo_item ?? "-");
}

function getOpTitle(op) {
  return op?.descricao_produto || op?.item?.descricao || op?.item?.nomeItem || "Sem descricao";
}

function getOpSequence(op) {
  const order = Number(op?.program_order ?? op?.program_seq ?? op?.sequence ?? 0);
  return Number.isFinite(order) ? order : 0;
}

function getStatus(op) {
  const raw = op?.status_operacional || {};
  const key = normalizeStatusKey(raw.chave || raw.key || op?.status || "");
  if (key) {
    return {
      key,
      label: raw.label || (key === "atencao" ? "Atenção" : key === "critico" ? "Crítico" : key === "ok" ? "OK" : key),
      classe: statusClassForKey(key),
      critico: Boolean(raw.critico),
    };
  }
  if (op?.no_realized) return { key: "sem-realizado", label: "Sem realizado", classe: "status-no-realized", critico: false };
  if (op?.divergent) return { key: "divergente", label: "Divergência", classe: "status-divergente", critico: false };
  if (op?.late) return { key: "critico", label: "Crítico", classe: "status-critico", critico: true };
  return { key: "ok", label: "OK", classe: "status-ok", critico: false };
}

function getSetupStatus(op) {
  const raw = op?.setup?.status_key || op?.status_setup?.chave || op?.status_setup?.key || "";
  const key = normalizeStatusKey(raw);
  const status = key || "unknown";
  const label = op?.setup?.status_label || op?.status_setup?.label || (status === "acima" ? "Setup acima" : status === "abaixo" ? "Setup abaixo" : status);
  const classes = statusClassForKey(status === "unknown" ? "planned" : status);
  return { key: status, label, classe: classes };
}

function getOpWindow(op) {
  return {
    plannedStart: parseDate(op?.tempo?.inicio_previsto ?? op?.start_date ?? null),
    plannedEnd: parseDate(op?.tempo?.fim_previsto ?? op?.end_date ?? null),
    realStart: parseDate(op?.tempo?.inicio_real ?? op?.realizado_inicio ?? null),
    realEnd: parseDate(op?.tempo?.fim_real ?? op?.realizado_fim ?? null),
    setupStart: parseDate(op?.setup?.inicio_previsto ?? op?.setup_start ?? null),
    setupEnd: parseDate(op?.setup?.fim_previsto ?? op?.setup_end ?? null),
    setupRealStart: parseDate(op?.setup?.inicio_real ?? op?.setup_start ?? null),
    setupRealEnd: parseDate(op?.setup?.fim_real ?? op?.setup_end ?? null),
  };
}

function collectAxisDates(ops) {
  const dates = [];
  ops.forEach((op) => {
    const window = getOpWindow(op);
    [
      window.plannedStart,
      window.plannedEnd,
      window.realStart,
      window.realEnd,
      window.setupStart,
      window.setupEnd,
      window.setupRealStart,
      window.setupRealEnd,
    ].forEach((date) => {
      if (date instanceof Date && !Number.isNaN(date.getTime())) {
        dates.push(date);
      }
    });
  });

  if (!dates.length) {
    const now = new Date();
    return { start: now, end: new Date(now.getTime() + 24 * 60 * 60 * 1000) };
  }

  const start = new Date(Math.min(...dates.map((date) => date.getTime())));
  const end = new Date(Math.max(...dates.map((date) => date.getTime())));
  const span = Math.max(end.getTime() - start.getTime(), 60 * 60 * 1000);
  const pad = Math.max(span * 0.06, 60 * 60 * 1000);
  return {
    start: new Date(start.getTime() - pad),
    end: new Date(end.getTime() + pad),
  };
}

function buildAxisTicks(start, end, count = 8) {
  const ticks = [];
  const span = Math.max(end.getTime() - start.getTime(), 1);
  const step = span / Math.max(count - 1, 1);
  for (let i = 0; i < count; i += 1) {
    ticks.push(new Date(start.getTime() + step * i));
  }
  return ticks;
}

function positionToPercentLinear(date, start, end) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return 0;
  const total = end.getTime() - start.getTime();
  if (total <= 0) return 0;
  const pct = ((date.getTime() - start.getTime()) / total) * 100;
  return Math.max(0, Math.min(100, pct));
}

function isSunday(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return false;
  return date.getDay() === 0;
}

function workingMsAt(layout, date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return 0;
  const starts = layout?.includedHourStarts || [];
  if (!starts.length) return 0;

  const time = date.getTime();
  if (time <= starts[0]) return 0;

  const lastStart = starts[starts.length - 1];
  const lastEnd = lastStart + 3600000;
  if (time >= lastEnd) return starts.length * 3600000;

  // Find the last included hour start that is <= time.
  let lo = 0;
  let hi = starts.length - 1;
  let idx = -1;
  while (lo <= hi) {
    const mid = (lo + hi) >> 1;
    if (starts[mid] <= time) {
      idx = mid;
      lo = mid + 1;
    } else {
      hi = mid - 1;
    }
  }

  if (idx < 0) return 0;
  const hourStart = starts[idx];
  const partial = Math.max(0, Math.min(3600000, time - hourStart));
  return idx * 3600000 + partial;
}

function positionToPercentWorking(date, layout) {
  const total = layout?.totalWorkingMs ?? 0;
  if (total <= 0) return 0;
  const value = workingMsAt(layout, date);
  const pct = (value / total) * 100;
  return Math.max(0, Math.min(100, pct));
}

function makeSegment(start, end, axis) {
  if (!(start instanceof Date) || !(end instanceof Date)) return null;
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
  const left = positionToPercentWorking(start, axis);
  const right = positionToPercentWorking(end, axis);
  const width = Math.max(0, right - left);
  if (width <= 0) return null;
  return {
    left,
    width: Math.max(width, width > 0 ? 0.65 : 0),
    start,
    end,
  };
}

const TIMELINE_CONFIG = {
  labelWidth: 220,
  hourWidth: 36,
  minTimelineWidth: 1600,
};

function cloneDate(date) {
  return new Date(date.getTime());
}

function floorToHour(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return null;
  const next = cloneDate(date);
  next.setMinutes(0, 0, 0);
  return next;
}

function ceilToHour(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return null;
  const next = floorToHour(date);
  if (!next) return null;
  if (next.getTime() < date.getTime()) {
    next.setHours(next.getHours() + 1);
  }
  return next;
}

function addHours(date, hours) {
  const next = cloneDate(date);
  next.setHours(next.getHours() + hours);
  return next;
}

function addDays(date, days) {
  const next = cloneDate(date);
  next.setDate(next.getDate() + days);
  return next;
}

function startOfIsoWeek(date) {
  const next = cloneDate(date);
  const day = next.getDay() || 7;
  if (day !== 1) {
    next.setDate(next.getDate() - (day - 1));
  }
  next.setHours(0, 0, 0, 0);
  return next;
}

function getIsoWeekNumber(date) {
  const next = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  next.setUTCDate(next.getUTCDate() + 4 - (next.getUTCDay() || 7));
  const yearStart = new Date(Date.UTC(next.getUTCFullYear(), 0, 1));
  return Math.ceil((((next - yearStart) / 86400000) + 1) / 7);
}

function formatWeekRange(start, end) {
  return `${String(start.getDate()).padStart(2, "0")}/${String(start.getMonth() + 1).padStart(2, "0")} - ${String(end.getDate()).padStart(2, "0")}/${String(end.getMonth() + 1).padStart(2, "0")}`;
}

function formatWeekLabel(start, end) {
  return `Semana ${getIsoWeekNumber(start)} | ${formatWeekRange(start, end)}`;
}

function formatDayLabel(date) {
  return new Intl.DateTimeFormat("pt-BR", {
    weekday: "short",
    day: "2-digit",
    month: "2-digit",
  })
    .format(date)
    .replace(/\./g, "");
}

function formatHourLabel(date) {
  return new Intl.DateTimeFormat("pt-BR", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  }).format(date);
}

function buildTimelineLayout(axis) {
  const rawStart = floorToHour(axis.start) || axis.start;
  const rawEnd = ceilToHour(axis.end) || axis.end;
  let start = rawStart instanceof Date ? rawStart : axis.start;
  let end = rawEnd instanceof Date ? rawEnd : axis.end;

  // Remove domingo da visualizacao: se o padding empurrar o range para domingo,
  // deslocamos o range para o proximo/ultimo horario exibivel.
  while (start < end && isSunday(start)) {
    start = addHours(start, 1);
  }
  while (start < end && isSunday(addHours(end, -1))) {
    end = addHours(end, -1);
  }
  const hourWidth = TIMELINE_CONFIG.hourWidth;

  const hours = [];
  let hourCursor = cloneDate(start);
  while (hourCursor < end) {
    if (!isSunday(hourCursor)) {
      hours.push({
        start: cloneDate(hourCursor),
        end: addHours(hourCursor, 1),
        width: hourWidth,
        label: formatHourLabel(hourCursor),
        showLabel: true,
      });
    }
    hourCursor = addHours(hourCursor, 1);
  }

  const totalHours = Math.max(1, hours.length);
  const totalWorkingMs = totalHours * 3600000;
  const timelineWidth = Math.max(TIMELINE_CONFIG.minTimelineWidth, totalHours * hourWidth);
  const hourLabelStep = totalHours > 96 ? 4 : totalHours > 48 ? 2 : 1;

  hours.forEach((cell, index) => {
    cell.showLabel = index % hourLabelStep === 0 || index === totalHours - 1;
  });

  const includedHourStarts = hours.map((cell) => cell.start.getTime());

  const weeks = [];
  const days = [];

  // Group included hours into (non-Sunday) day cells.
  let currentDayKey = null;
  let currentDay = null;
  hours.forEach((cell) => {
    const dayKey = formatDateKey(cell.start);
    if (dayKey !== currentDayKey) {
      if (currentDay) {
        currentDay.width = currentDay.hours * hourWidth;
        days.push(currentDay);
      }
      currentDayKey = dayKey;
      const weekday = new Intl.DateTimeFormat("pt-BR", { weekday: "short" }).format(cell.start).replace(/\./g, "");
      currentDay = {
        start: new Date(cell.start.getFullYear(), cell.start.getMonth(), cell.start.getDate()),
        end: addDays(new Date(cell.start.getFullYear(), cell.start.getMonth(), cell.start.getDate()), 1),
        hours: 0,
        width: 0,
        label: `${weekday} ${String(cell.start.getDate()).padStart(2, "0")}/${String(cell.start.getMonth() + 1).padStart(2, "0")}`,
        compact: formatDayLabel(cell.start),
      };
    }
    if (currentDay) currentDay.hours += 1;
  });
  if (currentDay) {
    currentDay.width = currentDay.hours * hourWidth;
    days.push(currentDay);
  }

  // Group day cells into week cells (ISO week), with width derived from included hours (domingo nao aparece).
  let currentWeekKey = null;
  let currentWeek = null;
  days.forEach((dayCell) => {
    const isoStart = startOfIsoWeek(dayCell.start);
    const weekKey = `${isoStart.getFullYear()}-${String(getIsoWeekNumber(isoStart)).padStart(2, "0")}`;
    if (weekKey !== currentWeekKey) {
      if (currentWeek) {
        weeks.push(currentWeek);
      }
      currentWeekKey = weekKey;
      currentWeek = {
        start: isoStart,
        end: addDays(isoStart, 7),
        width: 0,
        label: formatWeekLabel(isoStart, dayCell.start),
      };
    }
    if (currentWeek) {
      currentWeek.width += dayCell.width;
      currentWeek.label = formatWeekLabel(isoStart, dayCell.start);
    }
  });
  if (currentWeek) {
    weeks.push(currentWeek);
  }

  return {
    ...axis,
    start,
    end,
    hourWidth,
    hourLabelStep,
    totalHours,
    totalWorkingMs,
    includedHourStarts,
    timelineWidth,
    labelWidth: TIMELINE_CONFIG.labelWidth,
    weeks,
    days,
    hours,
  };
}

function renderAxisCells(cells, className, labelClassName = "") {
  return cells
    .map((cell) => {
      const classes = ["gantt-axis-cell", className];
      if (labelClassName) classes.push(labelClassName);
      const showCompact = Boolean(cell.compact) && cell.compact !== cell.label;
      return `
        <div class="${classes.join(" ")}" style="width:${cell.width}px">
          <strong>${escapeHtml(cell.label)}</strong>
          ${showCompact ? `<small>${escapeHtml(cell.compact)}</small>` : ""}
        </div>
      `;
    })
    .join("");
}

function updateTimelineCanvas(layout) {
  if (!els.ganttCanvas) return;
  els.ganttCanvas.style.setProperty("--label-width", `${layout.labelWidth}px`);
  els.ganttCanvas.style.setProperty("--timeline-width", `${layout.timelineWidth}px`);
  els.ganttCanvas.style.setProperty("--hour-width", `${layout.hourWidth}px`);
  els.ganttCanvas.style.setProperty("--hours-total", String(layout.totalHours));
}

function getSummary() {
  return state.payload?.resumo || state.payload?.metricas || {};
}

function sortOps(ops) {
  return [...ops].sort((a, b) => {
    const aOrder = getOpSequence(a);
    const bOrder = getOpSequence(b);
    if (aOrder !== bOrder) return aOrder - bOrder;
    const aStart = getOpWindow(a).plannedStart?.getTime?.() ?? 0;
    const bStart = getOpWindow(b).plannedStart?.getTime?.() ?? 0;
    if (aStart !== bStart) return aStart - bStart;
    return getOpCode(a).localeCompare(getOpCode(b), "pt-BR");
  });
}

function getVisibleOps() {
  if (!state.payload?.ops?.length) return [];
  const query = state.search.trim().toLowerCase();
  return sortOps(state.payload.ops).filter((op) => {
    const status = getStatus(op);
    const text = [
      getOpCode(op),
      getOpSku(op),
      getOpTitle(op),
      op?.linha,
      op?.programacao_id,
      status.label,
      status.key,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
    if (query && !text.includes(query)) return false;
    switch (state.filter) {
      case "ok":
        return status.key === "ok";
      case "atencao":
        return status.key === "atencao";
      case "critico":
        return status.key === "critico";
      case "sem-realizado":
        return Boolean(op?.no_realized || getOpWindow(op).realStart === null);
      case "divergente":
        return Boolean(op?.divergent);
      case "setup":
        return Number(op?.setup?.realizado_min ?? op?.setup_realizado_min ?? 0) > 0 ||
          Number(op?.setup?.previsto_min ?? op?.setup_previsto_min ?? 0) > 0;
      default:
        return true;
    }
  });
}

function fetchJson(path) {
  const url = `${apiPrefix}${path.startsWith("/") ? path : `/${path}`}`;
  return fetch(url).then(async (response) => {
    if (!response.ok) {
      const text = await response.text();
      throw new Error(`HTTP ${response.status}: ${text.slice(0, 200)}`);
    }
    return response.json();
  });
}

function lineKeyFromProgramacaoId(id) {
  const item = state.programacoes.find((entry) => String(getProgramacaoId(entry)) === String(id));
  return item ? getProgramacaoLineKey(item) : null;
}

function groupProgramacoes(items) {
  const groups = new Map();
  items.forEach((item) => {
    const key = getProgramacaoLineKey(item);
    const label = getProgramacaoLineLabel(item);
    if (!groups.has(key)) {
      groups.set(key, { key, label, items: [] });
    }
    groups.get(key).items.push(item);
  });
  return Array.from(groups.values()).sort((a, b) => a.label.localeCompare(b.label, "pt-BR"));
}

function renderLineSelect(groups) {
  if (!els.lineSelect) return;
  const options = ['<option value="">Todas as linhas</option>'];
  groups.forEach((group) => {
    options.push(`<option value="${escapeHtml(group.key)}">${escapeHtml(group.label)} (${group.items.length})</option>`);
  });
  els.lineSelect.innerHTML = options.join("");
  if (state.selectedLineKey) {
    els.lineSelect.value = state.selectedLineKey;
  }
}

function formatProgramacaoSelectLabel(item) {
  const lineLabel = displayValue(item?.lin_nome || item?.lin_codigo || item?.linha?.label || item?.linha?.codigo, "Linha -");
  const inicio = parseDate(item?.inicio_base_cronograma || item?.prg_base_inicio || item?.prg_data_consulta || item?.programacao_criada_em);
  const dataProgramacao = parseDate(item?.prg_data_consulta || item?.programacao_criada_em || item?.prg_criado_em);
  const eficiencia = Number(item?.prg_eficiencia ?? item?.eficiencia ?? NaN);
  const parts = [lineLabel];
  if (inicio) {
    parts.push(`Início: ${formatDateLong(inicio)}`);
  }
  if (dataProgramacao) {
    parts.push(`Data da Programação: ${formatDateLong(dataProgramacao)}`);
  }
  if (Number.isFinite(eficiencia)) {
    parts.push(`Eficiência: ${formatNumber(eficiencia, 0)}%`);
  }
  return parts.join(" | ");
}

function renderProgramacaoSelect() {
  if (!els.programacaoSelect) return;
  const options = ['<option value="">Selecionar programação</option>'];
  state.programacoes.forEach((item) => {
    const id = getProgramacaoId(item);
    options.push(`<option value="${escapeHtml(String(id))}">${escapeHtml(formatProgramacaoSelectLabel(item))}</option>`);
  });
  els.programacaoSelect.innerHTML = options.join("");
  if (state.selectedProgramacaoId) {
    els.programacaoSelect.value = String(state.selectedProgramacaoId);
  }
}

function renderSidebar() {
  if (!els.programacaoList) return;
  const query = state.search.trim().toLowerCase();
  const groups = groupProgramacoes(
    state.programacoes.filter((item) => {
      if (!query) return true;
      return getProgramacaoSearchText(item).includes(query);
    })
  );

  setTextContent(els.programacaoCount, String(state.programacoes.length));
  renderLineSelect(groupProgramacoes(state.programacoes));

  if (!groups.length) {
    els.programacaoList.innerHTML = '<div class="empty-state">Nenhuma programacao encontrada.</div>';
    return;
  }

  els.programacaoList.innerHTML = groups
    .map((group) => {
      const isActiveGroup = group.key === state.selectedLineKey;
      return `
        <div class="program-group ${isActiveGroup ? "is-active" : ""}">
          <button type="button" class="program-group-title" data-line-key="${escapeHtml(group.key)}">
            <span>${escapeHtml(group.label)}</span>
            <small>${group.items.length} programacao${group.items.length > 1 ? "s" : ""}</small>
          </button>
          <div class="program-group-items">
            ${group.items
              .map((item) => {
                const id = getProgramacaoId(item);
                const isActive = String(id) === String(state.selectedProgramacaoId);
                return `
                  <button type="button" class="program-item ${isActive ? "is-active" : ""}" data-programacao-id="${escapeHtml(String(id))}">
                    <strong>Programacao ${escapeHtml(String(getProgramacaoNumero(item)))}</strong>
                    <span>${escapeHtml(item?.lin_nome || item?.lin_codigo || "Sem linha")}</span>
                    <span>${escapeHtml(item?.prg_status || "status nao informado")}</span>
                    <em>${escapeHtml(String(item?.total_itens ?? 0))} itens</em>
                  </button>
                `;
              })
              .join("")}
          </div>
        </div>
      `;
    })
    .join("");
}

function renderSummaryCards() {
  const summary = getSummary();
  const setup = summary.setup || {};
  const production = summary.producao || {};
  const tempo = summary.tempo || {};
  const turnos = summary.turnos || {};
  const codi = summary.codi || {};
  const ops = Number(summary.ops ?? state.payload?.ops?.length ?? 0);
  const programs = Number(summary.programacoes ?? 1);

  setTextContent(els.kpiCompletionValue, formatPercent(production.percentual ?? 0, 1));
  setTextContent(els.kpiCompletionHint, `${ops} OPs | ${programs} programacao${programs > 1 ? "es" : ""}`);

  setTextContent(els.kpiSetupValue, `${formatNumber(setup.realizado_min ?? 0, 1)} / ${formatNumber(setup.previsto_min ?? 0, 1)} min`);
  setTextContent(els.kpiSetupHint, `Pendente ${formatNumber(setup.pendente_min ?? 0, 1)} min`);

  setTextContent(els.kpiProductionValue, `${formatNumber(production.realizada ?? 0, 1)} / ${formatNumber(production.prevista ?? 0, 1)}`);
  setTextContent(els.kpiProductionHint, `Desvio ${formatNumber(production.desvio ?? 0, 1)}`);

  setTextContent(els.kpiTempoValue, `${formatNumber(tempo.realizado_min ?? 0, 1)} / ${formatNumber(tempo.previsto_min ?? 0, 1)} min`);
  setTextContent(els.kpiTempoHint, `Desvio ${formatNumber(tempo.desvio_min ?? 0, 1)} min`);

  setTextContent(els.kpiTurnosValue, `${formatNumber(turnos.adm ?? 0, 1)} ADM`);
  setTextContent(els.kpiTurnosHint, `${formatNumber(turnos.noite ?? 0, 1)} noite | Total ${formatNumber(turnos.total ?? 0, 1)}`);

  setTextContent(els.kpiCodiValue, formatPercent(codi.eficiencia_media_pct ?? 0, 1));
  setTextContent(els.kpiCodiHint, `${Number(codi.ops_com_codi ?? 0)} OPs com CODI`);
}

function renderBoardMeta(layout, visibleOps) {
  if (!els.boardMeta) return;
  const summary = getSummary();
  const status = summary.status || {};
  const chips = [];
  chips.push(`<span class="meta-chip">${visibleOps.length} OPs</span>`);
  chips.push(`<span class="meta-chip">${formatDateShort(layout.start)} &rarr; ${formatDateShort(layout.end)}</span>`);
  chips.push(`<span class="meta-chip">OK ${status.ok ?? 0} | At ${status.atencao ?? 0} | Cr ${status.critico ?? 0}</span>`);
  setTextContent(els.boardTitle, `${state.payload?.linha?.label || state.payload?.linha?.codigo || "Linha"} | ${getProgramacaoLabelFromPayload(state.payload)}`);
  setTextContent(
    els.boardSubtitle,
    `Grade temporal em semana, dia e hora. Clique na linha para abrir o detalhe operacional no painel lateral.`
  );
  els.boardMeta.innerHTML = chips.join("");
  if (els.opsAlertLine) {
    setTextContent(
      els.opsAlertLine,
      `${visibleOps.length} OPs na janela.`
    );
  }
}

function syncGanttScrollFromVertical(deltaY = 0) {
  if (!els.ganttScroll) return;
  const maxTop = els.ganttScroll.scrollHeight - els.ganttScroll.clientHeight;
  const maxLeft = els.ganttScroll.scrollWidth - els.ganttScroll.clientWidth;
  if (maxTop <= 0 || maxLeft <= 0) return;
  const currentLeft = els.ganttScroll.scrollLeft;
  const horizontalFactor = maxLeft / Math.max(maxTop, 1);
  const hasDelta = Number.isFinite(deltaY) && deltaY !== 0;
  let targetLeft = currentLeft;

  if (hasDelta) {
    // Accumulate fractional pixels so the scrollbar moves even when the vertical range is huge.
    state.ganttScrollCarry += deltaY * horizontalFactor;
    const step = Math.trunc(state.ganttScrollCarry);
    if (step !== 0) {
      state.ganttScrollCarry -= step;
      targetLeft = currentLeft + step;
    }
  } else {
    // Fallback used when we don't know the delta (e.g., programmatic scrollTop changes).
    targetLeft = maxLeft * clamp(els.ganttScroll.scrollTop / maxTop, 0, 1);
  }

  targetLeft = Math.round(clamp(targetLeft, 0, maxLeft));
  if (Math.abs(els.ganttScroll.scrollLeft - targetLeft) < 1) return;
  state.ganttScrollSyncing = true;
  els.ganttScroll.scrollLeft = targetLeft;
  requestAnimationFrame(() => {
    state.ganttScrollSyncing = false;
  });
}

function renderAxis(layout, visibleOps) {
  if (!els.ganttAxis) return;
  updateTimelineCanvas(layout);

  const weeksHtml = renderAxisCells(layout.weeks, "gantt-axis-week");
  const daysHtml = renderAxisCells(layout.days, "gantt-axis-day");
  const hoursHtml = layout.hours
    .map(
      (cell, index) => `
        <div class="gantt-axis-cell gantt-axis-hour ${index % 2 === 0 ? "is-even" : ""}" style="width:${cell.width}px">
          <strong>${cell.showLabel ? escapeHtml(cell.label) : "&nbsp;"}</strong>
          ${cell.showLabel ? "" : `<small>${escapeHtml(String(cell.start.getHours()).padStart(2, "0"))}h</small>`}
        </div>
      `
    )
    .join("");

  els.ganttAxis.innerHTML = `
    <div class="gantt-axis-stub">
      <span>Semana / Dia / Hora</span>
      <strong>${escapeHtml(state.payload?.linha?.label || state.payload?.linha?.codigo || "Linha")}</strong>
      <small>${visibleOps.length} OPs</small>
    </div>
    <div class="gantt-axis-lanes" style="--timeline-width:${layout.timelineWidth}px">
      <div class="gantt-axis-row gantt-axis-row-week">
        <div class="gantt-axis-row-label">Semana</div>
        <div class="gantt-axis-row-track">${weeksHtml}</div>
      </div>
      <div class="gantt-axis-row gantt-axis-row-day">
        <div class="gantt-axis-row-label">Dia</div>
        <div class="gantt-axis-row-track">${daysHtml}</div>
      </div>
      <div class="gantt-axis-row gantt-axis-row-hour">
        <div class="gantt-axis-row-label">Horário</div>
        <div class="gantt-axis-row-track">${hoursHtml}</div>
      </div>
    </div>
  `;
}

function renderGanttRows(layout, visibleOps) {
  if (!els.ganttList || !els.ganttEmpty) return;
  if (!visibleOps.length) {
    els.ganttList.innerHTML = "";
    els.ganttEmpty.style.display = "block";
    setTextContent(els.ganttEmpty, state.search.trim() ? "Nenhuma OP corresponde aos filtros atuais." : "Selecione uma programacao para ver o grafico de Gantt operacional.");
    return;
  }

  els.ganttEmpty.style.display = "none";
  els.ganttList.innerHTML = visibleOps
    .map((op) => {
      const window = getOpWindow(op);
      const status = getStatus(op);
      const planned = makeSegment(window.plannedStart, window.plannedEnd, layout);
      const realized = makeSegment(window.realStart, window.realEnd, layout);
      const setupPlan = makeSegment(window.setupStart, window.setupEnd, layout);
      const setupReal = makeSegment(window.setupRealStart, window.setupRealEnd, layout);
      const selected = String(getOpCode(op)) === String(state.selectedOp);
      const startMarker = window.realStart || window.plannedStart || window.setupRealStart || window.setupStart;
      const endMarker = window.realEnd || window.plannedEnd || window.setupRealEnd || window.setupEnd;
      const plannedText = window.plannedStart && window.plannedEnd ? `${formatDateShort(window.plannedStart)} - ${formatDateShort(window.plannedEnd)}` : "Sem previsto";
      const realText = window.realStart && window.realEnd ? `${formatDateShort(window.realStart)} - ${formatDateShort(window.realEnd)}` : "Sem realizado";
      const setupText = setupReal
        ? `${formatDateShort(setupReal.start)} - ${formatDateShort(setupReal.end)}`
        : Number(op?.setup?.realizado_min ?? op?.setup_realizado_min ?? 0) > 0
          ? `${formatNumber(op?.setup?.realizado_min ?? op?.setup_realizado_min ?? 0, 1)} min`
          : "Sem setup";

      return `
        <article class="gantt-row ${selected ? "is-selected" : ""} ${status.classe}" data-op="${escapeHtml(getOpCode(op))}" role="button" tabindex="0" aria-pressed="${selected ? "true" : "false"}">
          <div class="gantt-row-label">
            <div class="gantt-row-idline">
              <strong>OP ${escapeHtml(getOpCode(op))}</strong>
              <span>Seq ${escapeHtml(op?.sequence ?? op?.program_seq ?? op?.program_order ?? "-")}</span>
            </div>
            <div class="gantt-row-product">${escapeHtml(getOpTitle(op))}</div>
            <div class="gantt-row-meta">
              <span class="gantt-row-status-pill ${status.classe}">${escapeHtml(status.label)}</span>
            </div>
          </div>
          <div class="gantt-row-timeline">
            <div class="gantt-track" style="--hour-width:${layout.hourWidth}px;--hours-total:${layout.totalHours};width:${layout.timelineWidth}px">
              ${setupPlan ? `<div class="gantt-bar gantt-bar-setup planned" style="left:${setupPlan.left}%;width:${setupPlan.width}%" title="Setup previsto: ${escapeHtml(setupText)}"></div>` : ""}
              ${setupReal ? `<div class="gantt-bar gantt-bar-setup realized" style="left:${setupReal.left}%;width:${setupReal.width}%" title="Setup realizado: ${escapeHtml(setupText)}"></div>` : ""}
              ${planned ? `<div class="gantt-bar gantt-bar-planned" style="left:${planned.left}%;width:${planned.width}%" title="Previsto: ${escapeHtml(plannedText)}"></div>` : ""}
              ${realized ? `<div class="gantt-bar gantt-bar-realized" style="left:${realized.left}%;width:${realized.width}%" title="Realizado: ${escapeHtml(realText)}"></div>` : ""}
              ${startMarker ? `<span class="gantt-marker gantt-marker-start" style="left:${positionToPercentWorking(startMarker, layout)}%" title="Início: ${escapeHtml(formatDateShort(startMarker))}"></span>` : ""}
              ${endMarker ? `<span class="gantt-marker gantt-marker-end" style="left:${positionToPercentWorking(endMarker, layout)}%" title="Fim: ${escapeHtml(formatDateShort(endMarker))}"></span>` : ""}
            </div>
          </div>
        </article>
      `;
    })
    .join("");

  void hydrateSetupBars(layout, visibleOps);
}

function renderProgramacaoView() {
  if (!state.payload) return;
  const visibleOps = getVisibleOps();
  const axis = collectAxisDates(visibleOps.length ? visibleOps : state.payload.ops || []);
  const layout = buildTimelineLayout(axis);
  state.axis = layout;
  renderSummaryCards();
  renderBoardMeta(layout, visibleOps);
  renderProgramacaoSelect();
  renderAxis(layout, visibleOps);
  renderGanttRows(layout, visibleOps);
  highlightSelectedOp();
}

function renderDetailLoading() {
  if (!els.inspectorBody) return;
  els.inspectorBody.innerHTML = `
    <div class="detail-block">
      <p class="muted">Carregando detalhe operacional...</p>
    </div>
  `;
}

function renderDetailError(message) {
  if (!els.inspectorBody) return;
  els.inspectorBody.innerHTML = `
    <div class="detail-block">
      <p class="muted">${escapeHtml(message)}</p>
    </div>
  `;
}

function renderListRows(items) {
  if (!items?.length) {
    return '<div class="detail-empty">Nenhum item disponivel.</div>';
  }
  return items
    .map(
      (item) => `
        <div class="detail-grid-row">
          <span>${escapeHtml(item.label)}</span>
          <strong>${escapeHtml(item.value)}</strong>
        </div>
      `
    )
    .join("");
}

function renderEventCard(item, variant = "principal") {
  const title = displayValue(item?.parada_nomeParada || item?.parada_tipo_nome || item?.tipo_bloco || "Evento");
  const subtitle = displayValue(item?.setup_referencia_detail || item?.setup_referencia || item?.origem || "");
  const timeStart = displayValue(item?.inicio_evento || item?.data_evento || "-");
  const timeEnd = displayValue(item?.fim_evento || "-");
  const qty = item?.quantidade ?? "";
  const minutes = item?.setup_duracao_minutos ?? item?.duracao_evento_minutos ?? "";
  return `
    <div class="detail-item detail-item-${variant}">
      <div class="detail-item-head">
        <div>
          <span>${escapeHtml(variant === "principal" ? "Principal" : variant === "support" ? "Apoio" : "Parada")}</span>
          <h4>${escapeHtml(title)}</h4>
        </div>
        <div class="detail-item-time">
          <strong>${escapeHtml(String(timeStart))}</strong>
          <small>${escapeHtml(String(timeEnd))}</small>
        </div>
      </div>
      <div class="detail-grid">
        ${subtitle && subtitle !== "-" ? `<div><span>Referencia</span><strong>${escapeHtml(subtitle)}</strong></div>` : ""}
        ${qty !== "" ? `<div><span>Quantidade</span><strong>${escapeHtml(String(qty))}</strong></div>` : ""}
        ${minutes !== "" ? `<div><span>Minutos</span><strong>${escapeHtml(formatNumber(minutes, 2))}</strong></div>` : ""}
        ${item?.setup_eventos_count !== undefined ? `<div><span>Eventos setup</span><strong>${escapeHtml(String(item.setup_eventos_count))}</strong></div>` : ""}
      </div>
    </div>
  `;
}

function renderGroupedStops(items) {
  if (!items?.length) {
    return '<div class="detail-empty">Nenhum agrupamento de paradas disponivel.</div>';
  }
  return `
    <div class="detail-list">
      ${items
        .map(
          (item) => `
            <div class="detail-item detail-item-grouped">
              <div class="detail-item-head">
                <div>
                  <span>Parada agrupada</span>
                  <h4>${escapeHtml(displayValue(item.nome || item.parada_nomeParada || "Parada"))}</h4>
                </div>
                <strong>${escapeHtml(String(item.total ?? item.count ?? 0))}</strong>
              </div>
              <div class="detail-grid">
                ${item.minutos !== undefined ? `<div><span>Minutos</span><strong>${escapeHtml(formatNumber(item.minutos, 2))}</strong></div>` : ""}
                ${item.quantidade !== undefined ? `<div><span>Quantidade</span><strong>${escapeHtml(formatNumber(item.quantidade, 2))}</strong></div>` : ""}
              </div>
            </div>
          `
        )
        .join("")}
    </div>
  `;
}

function renderDetailBlock(title, content, extraClasses = "") {
  return `
    <section class="detail-block ${extraClasses}">
      <h3>${escapeHtml(title)}</h3>
      ${content}
    </section>
  `;
}

function renderOpDetail(detail, op) {
  if (!els.inspectorBody) return;
  const summary = detail?.summary || {};
  const turnos = detail?.turnos || {};
  const codi = detail?.codi || {};
  const principal = Array.isArray(detail?.principal) ? detail.principal : [];
  const apoio = Array.isArray(detail?.apoio) ? detail.apoio : [];
  const grouped = Array.isArray(detail?.paradas_agrupadas) ? detail.paradas_agrupadas : [];

  const header = `
    <div class="detail-badges">
      <span class="meta-chip">${escapeHtml(displayValue(detail?.detail_source || "fonte nao informada"))}</span>
      <span class="meta-chip">${escapeHtml(displayValue(detail?.main_rule || "regra nao informada"))}</span>
      ${codi?.disponivel ? `<span class="meta-chip">CODI ${formatPercent(codi.eficiencia_pct ?? 0, 2)}</span>` : ""}
    </div>
    <div class="detail-grid detail-grid-two">
      <div><span>OP foco</span><strong>${escapeHtml(detail?.op || op?.op || "-")}</strong></div>
      <div><span>Periodo</span><strong>${escapeHtml(detail?.period_start || "-")} a ${escapeHtml(detail?.period_end || "-")}</strong></div>
      <div><span>Principal rows</span><strong>${escapeHtml(String(summary.principal_rows ?? 0))}</strong></div>
      <div><span>Apoio rows</span><strong>${escapeHtml(String(summary.apoio_rows ?? 0))}</strong></div>
      <div><span>Principal min</span><strong>${escapeHtml(formatNumber(summary.principal_minutes ?? 0, 2))}</strong></div>
      <div><span>Apoio min</span><strong>${escapeHtml(formatNumber(summary.apoio_minutes ?? 0, 2))}</strong></div>
    </div>
  `;

  const principalBlock = renderDetailBlock(
    "Bloco principal",
    principal.length
      ? `<div class="detail-list">${principal.map((item) => renderEventCard(item, "principal")).join("")}</div>`
      : '<div class="detail-empty">Sem itens de bloco principal para este periodo.</div>',
    "detail-block-principal"
  );

  const supportBlock = renderDetailBlock(
    "Bloco de apoio",
    apoio.length
      ? `<div class="detail-list">${apoio.map((item) => renderEventCard(item, "support")).join("")}</div>`
      : '<div class="detail-empty">Sem itens de apoio para este periodo.</div>',
    "detail-block-support"
  );

  const groupedBlock = renderDetailBlock(
    "Agrupamento de paradas",
    renderGroupedStops(grouped),
    "detail-block-grouped"
  );

  const sourceBlock = renderDetailBlock(
    "Resumo tecnico",
    `
      <div class="detail-grid">
        ${summary.rows_total !== undefined ? `<div><span>Linhas totais</span><strong>${escapeHtml(String(summary.rows_total))}</strong></div>` : ""}
        ${summary.raw_rows_total !== undefined ? `<div><span>Linhas brutas</span><strong>${escapeHtml(String(summary.raw_rows_total))}</strong></div>` : ""}
        ${summary.principal_events !== undefined ? `<div><span>Eventos principal</span><strong>${escapeHtml(String(summary.principal_events))}</strong></div>` : ""}
        ${summary.apoio_events !== undefined ? `<div><span>Eventos apoio</span><strong>${escapeHtml(String(summary.apoio_events))}</strong></div>` : ""}
        ${turnos?.disponivel ? `<div><span>Turnos</span><strong>ADM ${formatNumber(turnos.adm ?? 0, 1)} / Noite ${formatNumber(turnos.noite ?? 0, 1)}</strong></div>` : ""}
      </div>
    `
  );

  els.inspectorBody.innerHTML = [renderDetailBlock("Detalhe operacional", header), principalBlock, supportBlock, groupedBlock, sourceBlock].join("");
  setTextContent(els.inspectorTitle, `OP ${detail?.op || op?.op || "-"}`);
  setTextContent(els.inspectorSubtitle, `${displayValue(detail?.detail_source || "Fonte nao informada")} | ${displayValue(detail?.main_rule || "Regra nao informada")}`);
}

function highlightSelectedOp() {
  if (!els.ganttList) return;
  const rows = els.ganttList.querySelectorAll(".gantt-row");
  rows.forEach((row) => {
    row.classList.toggle("is-selected", row.dataset.op === String(state.selectedOp));
  });
}

function getDetailPeriodForOp(op) {
  const window = getOpWindow(op);
  const axis = state.axis || collectAxisDates(state.payload?.ops || []);
  const start = window.plannedStart || window.realStart || axis.start;
  const end = window.realEnd || window.plannedEnd || axis.end;
  return {
    periodStart: formatDateKey(start || axis.start),
    periodEnd: formatDateKey(end || axis.end),
    setupPlanMin: Number(op?.setup?.previsto_min ?? op?.setup_previsto_min ?? 0),
  };
}

function getDetailCacheKey(opCode, period) {
  return `${opCode}|${period.periodStart}|${period.periodEnd}|${period.setupPlanMin}`;
}

async function hydrateSetupBars(axis, visibleOps) {
  const candidates = visibleOps.filter((op) => {
    const window = getOpWindow(op);
    const hasSetupWindow = window.setupStart instanceof Date && window.setupEnd instanceof Date;
    const setupMinutes = Number(op?.setup?.realizado_min ?? op?.setup_realizado_min ?? 0);
    return !hasSetupWindow && setupMinutes > 0;
  });

  const limited = candidates.slice(0, 12);
  await Promise.all(
    limited.map(async (op) => {
      const opCode = getOpCode(op);
      const period = getDetailPeriodForOp(op);
      const cacheKey = getDetailCacheKey(opCode, period);
      let detail = state.opDetailCache[cacheKey];
      if (!detail) {
        try {
          detail = await fetchJson(
            `/gantt/ops/${encodeURIComponent(opCode)}/detalhe?period_start=${encodeURIComponent(period.periodStart)}&period_end=${encodeURIComponent(period.periodEnd)}&setup_plan_min=${encodeURIComponent(period.setupPlanMin)}`
          );
          state.opDetailCache[cacheKey] = detail;
        } catch (error) {
          return;
        }
      }

      const principalEvent = Array.isArray(detail?.principal) ? detail.principal[0] : null;
      if (!principalEvent) return;
      const setupStart = parseDate(principalEvent.inicio_evento);
      const setupEnd = parseDate(principalEvent.fim_evento);
      const segment = makeSegment(setupStart, setupEnd, axis);
      if (!segment) return;

      const row = els.ganttList.querySelector(`.gantt-row[data-op="${CSS.escape(opCode)}"]`);
      if (!row || row.dataset.setupHydrated === "1") return;
      const track = row.querySelector(".gantt-track");
      if (!track) return;

      const existing = row.querySelector(".gantt-bar-setup.realized");
      if (existing) {
        row.dataset.setupHydrated = "1";
        return;
      }

      const setupBar = document.createElement("div");
      setupBar.className = "gantt-bar gantt-bar-setup realized";
      setupBar.style.left = `${segment.left}%`;
      setupBar.style.width = `${segment.width}%`;
      setupBar.title = `Setup realizado: ${formatDateShort(setupStart)} - ${formatDateShort(setupEnd)}`;
      track.appendChild(setupBar);
      row.dataset.setupHydrated = "1";
    })
  );
}

async function loadOpDetail(op) {
  const opCode = getOpCode(op);
  const period = getDetailPeriodForOp(op);
  const cacheKey = getDetailCacheKey(opCode, period);
  state.selectedOp = opCode;
  highlightSelectedOp();
  openInspectorDrawer();

  if (state.opDetailCache[cacheKey]) {
    renderOpDetail(state.opDetailCache[cacheKey], op);
    return;
  }

  renderDetailLoading();

  const requestToken = ++state.detailRequestToken;
  try {
    const detail = await fetchJson(
      `/gantt/ops/${encodeURIComponent(opCode)}/detalhe?period_start=${encodeURIComponent(period.periodStart)}&period_end=${encodeURIComponent(period.periodEnd)}&setup_plan_min=${encodeURIComponent(period.setupPlanMin)}`
    );
    if (requestToken !== state.detailRequestToken) return;
    state.opDetailCache[cacheKey] = detail;
    renderOpDetail(detail, op);
  } catch (error) {
    if (requestToken !== state.detailRequestToken) return;
    renderDetailError(`Nao foi possivel carregar o detalhe da OP ${opCode}. ${error.message}`);
  }
}

async function loadProgramacao(programacaoId) {
  if (!programacaoId && programacaoId !== 0) return;
  const key = String(programacaoId);
  let payload = state.programacaoPayloadCache[key];
  if (!payload) {
    payload = await fetchJson(`/gantt/programacoes/${encodeURIComponent(key)}`);
    state.programacaoPayloadCache[key] = payload;
  }
  state.payload = payload;
  state.selectedProgramacaoId = Number(payload?.programacao?.id ?? programacaoId) || programacaoId;
  state.selectedLineKey = getProgramacaoLineKey({
    linha_excel_dominante: payload?.linha?.key || payload?.linha?.codigo,
    lin_nome: payload?.linha?.label,
    lin_codigo: payload?.linha?.codigo,
  });
  setTextContent(els.selectedLineChip, payload?.linha?.label || payload?.linha?.codigo || "Linha -");
  setTextContent(els.selectedProgramChip, getProgramacaoLabelFromPayload(payload));
  setTextContent(els.reportSubtitle, "Gantt operacional baseado no dominio de relgantt.php.");
  if (els.programacaoSelect) {
    els.programacaoSelect.value = String(state.selectedProgramacaoId || "");
  }
  renderSidebar();
  state.selectedOp = null;
  closeProgramDrawer();
  closeInspectorDrawer();
  closeSummaryDrawer();
  renderProgramacaoView();
  renderDetailError("Clique em uma OP no grafico para abrir o detalhe operacional.");
  highlightSelectedOp();
}

function selectFirstProgramacaoForLine(lineKey) {
  const lineItems = state.programacoes.filter((item) => getProgramacaoLineKey(item) === lineKey);
  if (!lineItems.length) return null;
  return lineItems[0];
}

async function loadLine(lineKey) {
  state.selectedLineKey = lineKey || null;
  const current = state.programacoes.find((item) => String(getProgramacaoId(item)) === String(state.selectedProgramacaoId));
  if (lineKey && current && getProgramacaoLineKey(current) === lineKey) {
    renderSidebar();
    renderProgramacaoView();
    return;
  }
  const next = lineKey ? selectFirstProgramacaoForLine(lineKey) : state.programacoes[0];
  renderSidebar();
  if (next) {
    await loadProgramacao(getProgramacaoId(next));
  } else {
    state.payload = null;
    renderSidebar();
    renderProgramacaoView();
  }
}

function applySearchAndFilter() {
  renderSidebar();
  if (state.payload) {
    renderProgramacaoView();
  }
}

function bindEvents() {
  if (els.summaryToggle) {
    els.summaryToggle.addEventListener("click", () => {
      openSummaryDrawer();
    });
  }

  if (els.programDrawerToggle) {
    els.programDrawerToggle.addEventListener("click", () => {
      openProgramDrawer();
    });
  }

  if (els.programDrawerClose) {
    els.programDrawerClose.addEventListener("click", () => {
      closeProgramDrawer();
    });
  }

  if (els.inspectorClose) {
    els.inspectorClose.addEventListener("click", () => {
      closeInspectorDrawer();
      state.selectedOp = null;
      highlightSelectedOp();
    });
  }

  if (els.summaryDrawerClose) {
    els.summaryDrawerClose.addEventListener("click", () => {
      closeSummaryDrawer();
    });
  }

  if (els.drawerBackdrop) {
    els.drawerBackdrop.addEventListener("click", () => {
      closeProgramDrawer();
      closeInspectorDrawer();
      closeSummaryDrawer();
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    if (els.programDrawer?.classList.contains("is-open")) {
      closeProgramDrawer();
    }
    if (els.inspector?.classList.contains("is-open")) {
      closeInspectorDrawer();
    }
    if (els.summaryDrawer?.classList.contains("is-open")) {
      closeSummaryDrawer();
    }
  });

  if (els.statusFilters) {
    els.statusFilters.addEventListener("click", (event) => {
      const button = event.target.closest("[data-filter]");
      if (!button) return;
      state.filter = button.dataset.filter || "all";
      els.statusFilters.querySelectorAll(".filter-pill").forEach((pill) => {
        pill.classList.toggle("is-active", pill === button);
      });
      applySearchAndFilter();
    });
  }

  if (els.programacaoSearch) {
    els.programacaoSearch.addEventListener("input", (event) => {
      state.search = event.target.value || "";
      applySearchAndFilter();
    });
  }

  if (els.lineSelect) {
    els.lineSelect.addEventListener("change", (event) => {
      const lineKey = event.target.value || "";
      if (!lineKey) {
        const fallback = state.programacoes[0];
        if (fallback) {
          loadProgramacao(getProgramacaoId(fallback));
        }
        return;
      }
      loadLine(lineKey);
    });
  }

  if (els.programacaoSelect) {
    els.programacaoSelect.addEventListener("change", (event) => {
      const programacaoId = event.target.value || "";
      if (!programacaoId) return;
      loadProgramacao(programacaoId);
    });
  }

  if (els.programacaoList) {
    els.programacaoList.addEventListener("click", (event) => {
      const programacaoButton = event.target.closest("[data-programacao-id]");
      if (programacaoButton) {
        loadProgramacao(programacaoButton.dataset.programacaoId);
        return;
      }
      const lineButton = event.target.closest("[data-line-key]");
      if (lineButton) {
        loadLine(lineButton.dataset.lineKey || "");
      }
    });
  }

  if (els.ganttList) {
    els.ganttList.addEventListener("click", (event) => {
      const row = event.target.closest(".gantt-row");
      if (row) {
        const op = (state.payload?.ops || []).find((item) => getOpCode(item) === row.dataset.op);
        if (op) {
          loadOpDetail(op);
        }
      }
    });
    els.ganttList.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      const row = event.target.closest(".gantt-row");
      if (!row) return;
      event.preventDefault();
      const op = (state.payload?.ops || []).find((item) => getOpCode(item) === row.dataset.op);
      if (op) {
        loadOpDetail(op);
      }
    });
  }

  if (els.ganttScroll) {
    els.ganttScroll.addEventListener("wheel", (event) => {
      if (event.shiftKey) return;
      if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
      const maxTop = els.ganttScroll.scrollHeight - els.ganttScroll.clientHeight;
      const maxLeft = els.ganttScroll.scrollWidth - els.ganttScroll.clientWidth;
      if (maxTop <= 0 || maxLeft <= 0) return;
      event.preventDefault();
      const nextTop = clamp(els.ganttScroll.scrollTop + event.deltaY, 0, maxTop);
      els.ganttScroll.scrollTop = nextTop;
      // Map vertical position -> horizontal position so the timeline visibly advances
      // even when scroll deltas are tiny (e.g. dragging the scrollbar / trackpads).
      syncGanttScrollFromVertical();
    }, { passive: false });

    els.ganttScroll.addEventListener("scroll", () => {
      if (state.ganttScrollSyncing) {
        state.ganttScrollLastTop = els.ganttScroll.scrollTop;
        state.ganttScrollLastLeft = els.ganttScroll.scrollLeft;
        return;
      }

      const currentTop = els.ganttScroll.scrollTop;
      const currentLeft = els.ganttScroll.scrollLeft;
      const deltaTop = currentTop - state.ganttScrollLastTop;
      const deltaLeft = currentLeft - state.ganttScrollLastLeft;
      state.ganttScrollLastTop = currentTop;
      state.ganttScrollLastLeft = currentLeft;

      if (Math.abs(deltaTop) > 0 && Math.abs(deltaTop) >= Math.abs(deltaLeft)) {
        syncGanttScrollFromVertical();
      }
    });
  }
}

async function loadProgramacoes() {
  const response = await fetchJson("/gantt/programacoes?limit=500&offset=0");
  state.programacoes = Array.isArray(response?.data) ? response.data : [];
  renderSidebar();
  renderProgramacaoSelect();
  if (state.selectedProgramacaoId) {
    const selected = state.programacoes.find((item) => String(getProgramacaoId(item)) === String(state.selectedProgramacaoId));
    if (selected) {
      state.selectedLineKey = getProgramacaoLineKey(selected);
      await loadProgramacao(getProgramacaoId(selected));
      return;
    }
  }
  const first = state.programacoes[0];
  if (first) {
    state.selectedLineKey = getProgramacaoLineKey(first);
    await loadProgramacao(getProgramacaoId(first));
  }
}

function initElements() {
  const ids = [
    "reportTitle",
    "reportSubtitle",
    "selectedLineChip",
    "selectedProgramChip",
    "summaryGrid",
    "summaryToggle",
    "summaryDrawer",
    "summaryDrawerClose",
    "kpiCompletionValue",
    "kpiCompletionHint",
    "kpiSetupValue",
    "kpiSetupHint",
    "kpiProductionValue",
    "kpiProductionHint",
    "kpiTempoValue",
    "kpiTempoHint",
    "kpiTurnosValue",
    "kpiTurnosHint",
    "kpiCodiValue",
    "kpiCodiHint",
    "programacaoSearch",
    "lineSelect",
    "programacaoSelect",
    "statusFilters",
    "programDrawerToggle",
    "programDrawer",
    "programDrawerClose",
    "drawerBackdrop",
    "programacaoCount",
    "programacaoList",
    "boardTitle",
    "boardSubtitle",
    "boardMeta",
    "opsAlertLine",
    "ganttScroll",
    "ganttCanvas",
    "ganttAxis",
    "ganttList",
    "ganttEmpty",
    "inspector",
    "inspectorClose",
    "inspectorTitle",
    "inspectorSubtitle",
    "inspectorBody",
  ];
  ids.forEach((id) => {
    els[id] = $(id);
  });
}

function renderInitialState() {
  setTextContent(els.reportTitle, "Gantt operacional");
  setTextContent(els.reportSubtitle, "Carregando programacoes...");
  setTextContent(els.selectedLineChip, "Linha -");
  setTextContent(els.selectedProgramChip, "Programacao -");
  setTextContent(els.boardTitle, "Selecione uma programacao");
  setTextContent(els.boardSubtitle, "O grafico operacional sera exibido aqui.");
  setTextContent(els.opsAlertLine, "Aguardando selecao de programacao.");
  closeProgramDrawer();
  closeInspectorDrawer();
  closeSummaryDrawer();
  if (els.ganttEmpty) {
    els.ganttEmpty.style.display = "block";
  }
}

async function init() {
  initElements();
  renderInitialState();
  bindEvents();
  try {
    await loadProgramacoes();
    if (state.payload) {
      setTextContent(els.selectedLineChip, state.payload?.linha?.label || state.payload?.linha?.codigo || "Linha -");
      setTextContent(els.selectedProgramChip, getProgramacaoLabelFromPayload(state.payload));
      setTextContent(els.reportSubtitle, "Gantt operacional baseado no dominio de relgantt.php.");
    }
  } catch (error) {
    if (els.ganttEmpty) {
      els.ganttEmpty.style.display = "block";
      setTextContent(els.ganttEmpty, `Falha ao carregar a visualizacao. ${error.message}`);
    }
    if (els.inspectorBody) {
      renderDetailError(`Falha ao carregar o backend de Gantt. ${error.message}`);
    }
  }
}

document.addEventListener("DOMContentLoaded", init);
