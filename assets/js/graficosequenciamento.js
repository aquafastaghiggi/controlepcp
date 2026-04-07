document.addEventListener('DOMContentLoaded', function () {
  var apiUrl = (function () {
    var pathname = window.location.pathname || '/';
    var trimmed = pathname.replace(/\/[^\/]*$/, '/');
    if (trimmed === '') {
      trimmed = '/';
    }
    return window.location.origin + trimmed + 'api/programacoes.php';
  })();

  var chart = document.getElementById('sequenciamento-chart');
  var axis = document.getElementById('sequenciamento-axis');
  var statusArea = document.getElementById('sequenciamento-status-area');
  var programSelect = document.getElementById('sequenciamento-programacao');
  var resourceInput = document.getElementById('sequenciamento-recurso');
  var statusFilter = document.getElementById('sequenciamento-status');
  var periodSelect = document.getElementById('sequenciamento-periodo');
  var buscarButton = document.getElementById('sequenciamento-buscar');
  var tooltip = document.createElement('div');
  tooltip.className = 'sequenciamento-tooltips';
  document.body.appendChild(tooltip);

  var tasks = [];
  var currentProgramacao = null;
  var currentProgramacaoLabel = '';
  var programOpLookup = {};

  var params = new URLSearchParams(window.location.search);
  var prefill = params.get('prg_id');
  if (prefill && programSelect) {
    programSelect.value = prefill;
  }

  if (buscarButton) {
    buscarButton.addEventListener('click', function () {
      loadGraph();
    });
  }
  if (programSelect) {
    programSelect.addEventListener('change', function () {
      loadGraph();
    });
  }

  loadProgramacoesList();

  function loadProgramacoesList() {
    if (!programSelect) {
      return;
    }
    programSelect.disabled = true;
    setStatus('Carregando programações...', 'loading');
    fetch(apiUrl + '?limit=20', { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Erro ao listar programações');
        }
        return response.json();
      })
      .then(function (json) {
        var data = Array.isArray(json.data) ? json.data : [];
        populateProgramSelect(data);
        if (data.length) {
          var selected = prefetchId(data);
          if (selected) {
            programSelect.value = selected;
          }
          loadGraph();
        } else {
          setStatus('Nenhuma programação disponível', 'warning');
        }
      })
      .catch(function (error) {
        console.error('Erro ao carregar programações', error);
        setStatus('Falha ao carregar programações', 'danger');
        if (programSelect) {
          programSelect.disabled = false;
        }
      });
  }

  function prefetchId(list) {
    if (prefill) {
      for (var i = 0; i < list.length; i++) {
        if (String(list[i].prg_id) === String(prefill)) {
          return list[i].prg_id;
        }
      }
    }
    return list.length ? list[0].prg_id : '';
  }

  function populateProgramSelect(items) {
    if (!programSelect) {
      return;
    }
    if (!items.length) {
      programSelect.innerHTML = '<option value="">Sem programações</option>';
      return;
    }
    var html = [];
    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var label = (item.prg_numero_op || item.numero_op || 'Programação ' + item.prg_id) + ' · ' + (item.lin_codigo || item.linha || 'Linha');
      html.push('<option value="' + item.prg_id + '">' + label + '</option>');
      programOpLookup[item.prg_id] = (item.prg_numero_op || item.numero_op || '').toString().trim();
    }
    programSelect.innerHTML = html.join('');
    programSelect.disabled = false;
  }

  if (periodSelect) {
    periodSelect.addEventListener('change', function () {
      handleFilterChange('Período');
    });
  }
  if (statusFilter) {
    statusFilter.addEventListener('change', function () {
      handleFilterChange('Status');
    });
  }
  if (resourceInput) {
    resourceInput.addEventListener('input', function () {
      handleFilterChange('Recurso');
    });
  }

  function loadGraph() {
    var identifier = getProgramValue();
    if (!identifier) {
      setStatus('Informe o ID da programação para renderizar o gráfico.', 'warning');
      return;
    }

    setStatus('Carregando dados...', 'loading');
    clearChart();

    fetchWithParams('id', identifier)
      .then(function (programacao) {
        var programacaoData = programacao.programacao || programacao;
        var scheduleData = [];
        if (programacao.schedule && Array.isArray(programacao.schedule)) {
          scheduleData = programacao.schedule;
        } else if (programacaoData.schedule && Array.isArray(programacaoData.schedule)) {
          scheduleData = programacaoData.schedule;
        }
        currentProgramacaoLabel = programOpLookup[identifier] || ''
          || (programacaoData.prg_numero_op || programacaoData.numero_op || '');
        currentProgramacao = programacaoData;
        tasks = buildTasksFromSchedule(scheduleData || []);
        var count = renderCurrentView();
        if (!count) {
          setStatus('Nenhuma tarefa encontrada com os filtros selecionados.', 'info');
          return;
        }
        var label = getProgramLabel();
        setStatus('Exibindo ' + count + ' itens da programação ' + label + '.', 'success');
      })
      .catch(function (error) {
        console.error('Erro ao buscar gráfico de sequenciamento', error);
        var message = error && error.message ? error.message : 'Erro desconhecido';
        setStatus(message, 'danger');
      });
  }

  function fetchWithParams(key, value) {
    var url = apiUrl + '?' + encodeURIComponent(key) + '=' + encodeURIComponent(value);
    return fetch(url, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) {
        var error = new Error('Programação não encontrada');
        error.status = response.status;
        throw error;
      }
      return response.json();
    });
  }

  function getProgramValue() {
    if (!programSelect) {
      return '';
    }
    return (programSelect.value || '').toString().trim();
  }

  function renderCurrentView() {
    clearChart();
    if (!tasks.length) {
      return 0;
    }
    var filtered = filtrarTarefas(tasks);
    if (!filtered.length) {
      return 0;
    }
    var range = getTimelineRange(filtered);
    var visible = applyWindowRange(range);
    renderAxis(filtered, visible);
    renderTasks(filtered, visible);
    return filtered.length;
  }

  function handleFilterChange(prefixLabel) {
    if (!tasks.length) {
      return;
    }
    var count = renderCurrentView();
    if (!count) {
      setStatus('Nenhuma tarefa encontrada com os filtros selecionados.', 'info');
      return;
    }
    var label = getProgramLabel();
    var prefix = prefixLabel ? prefixLabel + ' · ' : '';
    setStatus(prefix + 'Exibindo ' + count + ' itens da programação ' + label + '.', 'success');
  }

  function getProgramLabel() {
    if (currentProgramacaoLabel) {
      return currentProgramacaoLabel;
    }
    if (!currentProgramacao) {
      return '';
    }
    return currentProgramacao.prg_numero_op || currentProgramacao.numero_op || currentProgramacao.prg_id || currentProgramacao.id || '';
  }

  function filtrarTarefas(items) {
    var recursoBusca = '';
    var statusValor = 'todos';
    if (resourceInput && resourceInput.value) {
      recursoBusca = resourceInput.value.trim().toLowerCase();
    }
    if (statusFilter && statusFilter.value) {
      statusValor = statusFilter.value.toLowerCase();
    }
    return items.filter(function (task) {
      var rawTipo = task.tipo ? String(task.tipo) : '';
      var normalizedTipo = rawTipo.normalize && typeof rawTipo.normalize === 'function'
        ? rawTipo.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        : rawTipo;
      var tipo = normalizedTipo.toLowerCase();
      if (statusValor !== 'todos' && tipo !== statusValor) {
        return false;
      }
      if (recursoBusca && (task.recurso || '').toLowerCase().indexOf(recursoBusca) === -1) {
        return false;
      }
      return true;
    });
  }

  function renderAxis(items, visibleRange) {
    var range = visibleRange || getTimelineRange(items);
    if (!range.start || !range.end) {
      axis.innerHTML = '';
      return;
    }
    axis.innerHTML = '';
    var weeksContainer = document.createElement('div');
    weeksContainer.className = 'axis-weeks';
    var daysContainer = document.createElement('div');
    daysContainer.className = 'axis-days';
    axis.appendChild(weeksContainer);
    axis.appendChild(daysContainer);

    var dayList = buildDayList(range);
    if (!dayList.length) {
      return;
    }
    var totalDays = dayList.length;
    var weekSegments = buildWeekSegments(dayList);

    weekSegments.forEach(function (segment) {
      var span = document.createElement('span');
      span.textContent = segment.label;
      span.style.width = (segment.days / totalDays) * 100 + '%';
      weeksContainer.appendChild(span);
    });

    dayList.forEach(function (day) {
      var span = document.createElement('span');
      span.textContent = formatDayLabel(day);
      span.style.width = 100 / totalDays + '%';
      daysContainer.appendChild(span);
    });
  }

  function buildDayList(range) {
    var list = [];
    var current = new Date(range.start);
    current.setHours(0, 0, 0, 0);
    var end = new Date(range.end);
    end.setHours(0, 0, 0, 0);
    while (current.getTime() <= end.getTime()) {
      list.push(new Date(current));
      current.setDate(current.getDate() + 1);
    }
    return list;
  }

  function buildWeekSegments(dayList) {
    var segments = [];
    if (!dayList.length) {
      return segments;
    }
    var currentWeek = getWeekNumber(dayList[0]);
    var startDay = dayList[0];
    var count = 1;
    for (var i = 1; i < dayList.length; i++) {
      var week = getWeekNumber(dayList[i]);
      if (week === currentWeek) {
        count++;
        continue;
      }
      segments.push({
        label: formatWeekLabel(startDay, dayList[i - 1], currentWeek),
        days: count,
      });
      currentWeek = week;
      startDay = dayList[i];
      count = 1;
    }
    segments.push({
      label: formatWeekLabel(startDay, dayList[dayList.length - 1], currentWeek),
      days: count,
    });
    return segments;
  }

  function formatWeekLabel(startDate, endDate, weekNumber) {
    if (!startDate || !endDate) {
      return '';
    }
    return 'Semana ' + weekNumber + ' · ' + pad(startDate.getDate()) + '/' + pad(startDate.getMonth() + 1) + ' – ' + pad(endDate.getDate()) + '/' + pad(endDate.getMonth() + 1);
  }

  function formatDayLabel(date) {
    var weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return weekdays[date.getDay()] + ' ' + pad(date.getDate()) + '/' + pad(date.getMonth() + 1);
  }

  function getWeekNumber(date) {
    var target = new Date(date.valueOf());
    var dayNr = (date.getDay() + 6) % 7;
    target.setDate(target.getDate() - dayNr + 3);
    var firstThursday = new Date(target.getFullYear(), 0, 4);
    dayNr = (firstThursday.getDay() + 6) % 7;
    firstThursday.setDate(firstThursday.getDate() - dayNr + 3);
    var weekNumber = 1 + Math.round((target - firstThursday) / (7 * 24 * 60 * 60 * 1000));
    return weekNumber;
  }

  function getTimelineRange(items) {
    var min = Infinity;
    var max = -Infinity;
    for (var i = 0; i < items.length; i++) {
      var start = parseDate(items[i].start);
      var end = parseDate(items[i].end);
      if (start && start < min) {
        min = start;
      }
      if (end && end > max) {
        max = end;
      }
    }
    return { start: isFinite(min) ? min : null, end: isFinite(max) ? max : null };
  }

  function applyWindowRange(range) {
    if (!range.start || !range.end) {
      return range;
    }
    var periodMap = {
      semana: 7,
      '14dias': 14,
      mes: 30,
    };
    var periodValue = 'mes';
    if (periodSelect && periodSelect.value) {
      periodValue = periodSelect.value;
    }
    var days = periodMap[periodValue] || 7;
    var requestedMs = days * 24 * 60 * 60 * 1000;
    var span = range.end - range.start;
    if (span <= requestedMs) {
      return range;
    }
    var now = Date.now();
    var end = Math.min(range.end, Math.max(range.start + requestedMs / 2, now));
    var start = end - requestedMs;
    if (start < range.start) {
      start = range.start;
      end = Math.min(range.start + requestedMs, range.end);
    }
    if (end > range.end) {
      end = range.end;
      start = Math.max(range.start, range.end - requestedMs);
    }
    return { start: start, end: end };
  }

  function buildDayLabels(startMs, endMs) {
    var labels = [];
    var dayMs = 24 * 60 * 60 * 1000;
    var begin = new Date(startMs);
    begin.setHours(0, 0, 0, 0);
    var finish = new Date(endMs);
    finish.setHours(0, 0, 0, 0);
    for (var d = begin.getTime(); d <= finish.getTime() + dayMs; d += dayMs) {
      var date = new Date(d);
      labels.push(formatWeekday(date) + ' ' + pad(date.getDate()) + '/' + pad(date.getMonth() + 1));
    }
    return labels;
  }

  function renderTasks(items, visibleRange) {
    var range = visibleRange || getTimelineRange(items);
    if (!range.start || !range.end) {
      chart.innerHTML = '';
      return;
    }
    var totalMs = range.end - range.start || 1;
    chart.innerHTML = '';
    var sorted = items.slice().sort(function (a, b) {
      var startA = parseDate(a.start) || 0;
      var startB = parseDate(b.start) || 0;
      return startA - startB;
    });
    for (var i = 0; i < sorted.length; i++) {
      var task = sorted[i];
      var rawStart = parseDate(task.start);
      var rawEnd = parseDate(task.end);
      var row = document.createElement('div');
      row.className = 'sequenciamento-row';
      var label = document.createElement('div');
      label.className = 'sequenciamento-row-label';
      var labelContent = buildRowLabel(task);
      label.innerHTML =
        '<span class="resource-name">' +
        escapeHtml(labelContent.resource) +
        '</span>' +
        '<span class="resource-detail">' +
        escapeHtml(labelContent.detail) +
        '</span>';
      var barArea = document.createElement('div');
      barArea.className = 'sequenciamento-row-bar-area';
      row.appendChild(label);
      row.appendChild(barArea);
      if (!rawStart || !rawEnd) {
        chart.appendChild(row);
        continue;
      }
      var taskStart = Math.max(range.start, rawStart);
      var taskEnd = Math.min(range.end, rawEnd);
      if (taskEnd <= taskStart) {
        chart.appendChild(row);
        continue;
      }
      var left = ((taskStart - range.start) / totalMs) * 100;
      var width = ((taskEnd - taskStart) / totalMs) * 100;
      var bar = document.createElement('span');
      bar.className = 'sequenciamento-bar';
      bar.style.left = Math.max(0, Math.min(100, left)) + '%';
      bar.style.width = Math.max(1, Math.min(100, width)) + '%';
      bar.style.background = task.color || '#3B82F6';
      bar.dataset.count = task.tooltip && task.tooltip.Quant ? formatNumber(task.tooltip.Quant) : '';
      var tooltipText = buildTooltipText(task);
      var timelineLabel = formatTimelineRange(task.start, task.end);
      var fullTooltip = [timelineLabel, tooltipText].filter(Boolean).join(' · ');
      bar.setAttribute('title', fullTooltip);
      (function (barEl, tooltipValue) {
        barEl.addEventListener('mousemove', function (event) {
          showTooltip(event, tooltipValue);
        });
        barEl.addEventListener('mouseout', function () {
          hideTooltip();
        });
      })(bar, fullTooltip);
      barArea.appendChild(bar);
      chart.appendChild(row);
    }
  }

  function buildTasksFromSchedule(schedule) {
    var items = [];
    var colorMap = {
      setup: '#EA580C',
      produção: '#3B82F6',
      producao: '#3B82F6',
      pausa: '#F8B4D1',
      manutenção: '#8B5CF6',
      manutencao: '#8B5CF6',
    };
    for (var i = 0; i < schedule.length; i++) {
      var row = schedule[i];
      var start = buildDateTime(row.sch_data_inicio, row.sch_hora_inicio);
      if (!start) {
        continue;
      }
      var end = buildEndTime(row, start);
      if (!end) {
        continue;
      }
      var tipo = (row.sch_tipo || 'Produção').toString().trim();
      var tipoKey = tipo.toLowerCase();
      var name = row.sch_descricao || row.sch_sku || tipo;
      var task = {
        start: formatIso(start),
        end: formatIso(end),
        recurso: row.sch_recurso || row.sch_operador || 'Padrão',
        tipo: tipoKey,
        color: colorMap[tipoKey] || '#3B82F6',
        tooltip: {
          Seq: row.sch_sequencia || '',
          SKU: row.sch_sku || '',
          Quant: row.sch_quantidade || '',
          Tipo: tipo,
        },
        programacaoOp: getProgramLabel(),
        name: name,
      };
      items.push(task);
    }
    return items;
  }

  function buildRowLabel(task) {
    var resource = task.programacaoOp ? task.programacaoOp.toString().trim() : '';
    if (!resource && currentProgramacaoLabel) {
      resource = currentProgramacaoLabel;
    }
    if (!resource && currentProgramacao) {
      resource = (currentProgramacao.prg_numero_op || currentProgramacao.numero_op || currentProgramacao.prg_id || '')
        .toString()
        .trim();
    }
    var detailParts = [];
    if (task.tooltip && task.tooltip.Seq) {
      detailParts.push('Seq ' + task.tooltip.Seq);
    }
    if (task.name) {
      detailParts.push(task.name);
    }
    var detail = detailParts.length ? detailParts.join(' · ') : 'Operação';
    return { resource: resource, detail: detail };
  }

  function escapeHtml(text) {
    if (!text) {
      return '';
    }
    return text
      .toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function buildEndTime(row, start) {
    var candidate = parseFlexibleDate(row.sch_fim_producao);
    if (!candidate && row.sch_data_fim && row.sch_hora_fim) {
      candidate = buildDateTime(row.sch_data_fim, row.sch_hora_fim);
    }
    if (!candidate && row.sch_hora_fim) {
      candidate = buildDateTime(row.sch_data_inicio, row.sch_hora_fim);
    }
    if (!candidate) {
      var duration = parseInt(row.sch_duracao_minutos, 10) || 0;
      if (duration > 0) {
        candidate = new Date(start.getTime() + duration * 60 * 1000);
      }
    }
    if (!candidate) {
      candidate = new Date(start.getTime() + 30 * 60 * 1000);
    }
    return candidate;
  }

  function buildDateTime(dateStr, timeStr) {
    if (!dateStr) {
      return null;
    }
    var datePart = dateStr.toString().substring(0, 10);
    var hourPart = (timeStr || '00:00').toString().substring(0, 5);
    var candidate = new Date(datePart + 'T' + hourPart + ':00');
    if (isNaN(candidate.getTime())) {
      return null;
    }
    return candidate;
  }

  function parseFlexibleDate(value) {
    if (!value) {
      return null;
    }
    var normalized = value.toString().replace(' ', 'T');
    if (normalized.length === 10) {
      normalized += 'T00:00:00';
    }
    if (normalized.length === 16) {
      normalized += ':00';
    }
    var candidate = new Date(normalized);
    if (isNaN(candidate.getTime())) {
      return null;
    }
    return candidate;
  }

  function formatIso(date) {
    var y = date.getFullYear();
    var m = pad(date.getMonth() + 1);
    var d = pad(date.getDate());
    var h = pad(date.getHours());
    var min = pad(date.getMinutes());
    var s = pad(date.getSeconds());
    return y + '-' + m + '-' + d + 'T' + h + ':' + min + ':' + s;
  }

  function formatTimelineRange(startValue, endValue) {
    var start = parseDate(startValue);
    var end = parseDate(endValue);
    if (!start || !end) {
      return '';
    }
    var startDate = new Date(start);
    var endDate = new Date(end);
    return pad(startDate.getDate()) + '/' + pad(startDate.getMonth() + 1) + ' ' + pad(startDate.getHours()) + ':' + pad(startDate.getMinutes()) +
      ' → ' +
      pad(endDate.getDate()) + '/' + pad(endDate.getMonth() + 1) + ' ' + pad(endDate.getHours()) + ':' + pad(endDate.getMinutes());
  }

  function buildTooltipText(task) {
    var infoParts = [];
    if (task.tooltip) {
      for (var key in task.tooltip) {
        if (Object.prototype.hasOwnProperty.call(task.tooltip, key) && task.tooltip[key]) {
          if (key === 'Quant') {
            infoParts.push(key + ': ' + formatNumber(task.tooltip[key]));
          } else {
            infoParts.push(key + ': ' + task.tooltip[key]);
          }
        }
      }
    }
    if (task.name) {
      infoParts.unshift(task.name);
    }
    return infoParts.join(' / ');
  }

  function parseDate(value) {
    if (!value) {
      return null;
    }
    var date = new Date(value);
    return isNaN(date.getTime()) ? null : date.getTime();
  }

  function formatWeekday(date) {
    var weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return weekdays[date.getDay()];
  }

  function formatNumber(value) {
    var num = parseFloat((value || '').toString().replace(',', '.'));
    if (isNaN(num)) {
      return '';
    }
    return num.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function pad(num) {
    return String(num).padStart(2, '0');
  }

  function clearChart() {
    if (axis) {
      axis.innerHTML = '';
    }
    if (chart) {
      chart.innerHTML = '';
    }
  }

  function setStatus(text, state) {
    if (!statusArea) {
      return;
    }
    statusArea.textContent = text;
    var className = 'sequenciamento-status';
    if (state) {
      className += ' ' + state;
    }
    statusArea.className = className;
  }

  function showTooltip(event, text) {
    tooltip.textContent = text;
    tooltip.style.display = 'block';
    tooltip.style.left = event.pageX + 12 + 'px';
    tooltip.style.top = event.pageY + 12 + 'px';
  }

  function hideTooltip() {
    tooltip.style.display = 'none';
  }
});
