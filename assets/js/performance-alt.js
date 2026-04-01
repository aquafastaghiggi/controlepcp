(function () {
  'use strict';

  function renderPerformanceAlternative(detail) {
    const container = document.getElementById('performance-alt');
    const body = document.getElementById('performance-alt-body');
    if (!container || !body) return;

    const diffDaysLocal = (start, end) => {
      if (!start || !end || Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 0;
      const s0 = new Date(start.getFullYear(), start.getMonth(), start.getDate());
      const e0 = new Date(end.getFullYear(), end.getMonth(), end.getDate());
      return Math.max(0, Math.round((e0.getTime() - s0.getTime()) / 86400000));
    };

    const formatDateOnlyPtShort = (dateObj) => {
      if (!dateObj || Number.isNaN(dateObj.getTime())) return '--/--';
      return `${String(dateObj.getDate()).padStart(2, '0')}/${String(dateObj.getMonth() + 1).padStart(2, '0')}`;
    };

    const toMinutes = (hhmm) => {
      const parts = String(hhmm || '').split(':');
      const h = Number(parts[0] || 0);
      const m = Number(parts[1] || 0);
      return (h * 60) + m;
    };

    const minutesOfDay = (dateObj) => {
      if (!dateObj || Number.isNaN(dateObj.getTime())) return 0;
      return (dateObj.getHours() * 60) + dateObj.getMinutes();
    };

    const computeSpanWithinShift = (itemStart, itemEnd, shiftStart, shiftEnd) => {
      let shiftStartMin = toMinutes(shiftStart);
      let shiftEndMin = toMinutes(shiftEnd);
      const crossesMidnight = shiftEndMin <= shiftStartMin;
      if (crossesMidnight) shiftEndMin += 1440;

      let startMin = minutesOfDay(itemStart);
      let endMin = minutesOfDay(itemEnd);
      if (crossesMidnight) {
        if (startMin < shiftStartMin) startMin += 1440;
        if (endMin < shiftStartMin) endMin += 1440;
      } else if (endMin < startMin) {
        endMin += 1440;
      }

      const span = Math.max(1, shiftEndMin - shiftStartMin);
      const left = Math.min(1, Math.max(0, (startMin - shiftStartMin) / span));
      const right = Math.min(1, Math.max(0, (endMin - shiftStartMin) / span));
      const width = Math.max(0.02, right - left);

      return {
        leftPct: (left * 100),
        widthPct: (width * 100),
      };
    };

    const showProd = document.getElementById('performance-alt-filter-prod')?.checked ?? true;
    const showSetup = document.getElementById('performance-alt-filter-setup')?.checked ?? true;
    const filterPredicate = (row) => {
      const isSetup = String(row?.sch_tipo || '').trim().toLowerCase() === 'setup';
      if (isSetup) return showSetup;
      return showProd;
    };

    const scheduleRows = Array.isArray(detail?.schedule)
      ? detail.schedule.map((row, idx) => ({ ...row, _altIdx: idx }))
      : [];
    if (!scheduleRows.length) {
      body.innerHTML = '<div class="muted">Nenhum dado disponível para este layout alternativo.</div>';
      container.classList.remove('hidden');
      return;
    }

    const calendarIntervals = (typeof getAppState === 'function' ? getAppState() : null)?.datasets?.calendar?.intervals || [];
    const opMap = typeof buildPerformanceOpMap === 'function' ? buildPerformanceOpMap(detail) : new Map();

    const altItemByIdx = new Map();
    const flatAltItems = [];

    const grouped = scheduleRows
      .filter(filterPredicate)
      .reduce((map, row) => {
        const start = typeof parseLocalDateTime === 'function' ? parseLocalDateTime(row?.sch_data_inicio, row?.sch_hora_inicio) : null;
        if (!start) return map;

        const dateKey = typeof toDateKeyLocal === 'function' ? toDateKeyLocal(start) : '';
        if (!dateKey) return map;
        if (!map[dateKey]) map[dateKey] = [];

        const seqKey = String(row?.sch_sequencia ?? '').trim();
        const isSetup = String(row?.sch_tipo || '').trim().toLowerCase() === 'setup';
        const end = (typeof parseLocalDateTime === 'function' ? parseLocalDateTime(row?.sch_data_inicio, row?.sch_hora_fim) : null)
          || (typeof parseLocalDateTimeFromSql === 'function' ? parseLocalDateTimeFromSql(row?.sch_fim_producao) : null)
          || start;

        const item = {
          start,
          end,
          label: row?.sch_sequencia ? `${row.sch_sequencia} - ${row.sch_descricao}` : (row.sch_descricao || 'Produção'),
          isSetup,
          seq: seqKey,
          op: seqKey ? (opMap.get(seqKey) || '') : '',
          idx: row._altIdx,
          row,
        };
        map[dateKey].push(item);
        flatAltItems.push(item);
        return map;
      }, {});

    Object.values(grouped).forEach((items) => {
      (items || []).forEach((item) => {
        if (item && item.idx !== undefined && item.idx !== null) {
          altItemByIdx.set(String(item.idx), item);
        }
      });
    });

    const itemsBySeq = new Map();
    flatAltItems.forEach((item) => {
      const seq = String(item?.seq || '').trim();
      if (!seq) return;
      if (!itemsBySeq.has(seq)) itemsBySeq.set(seq, []);
      itemsBySeq.get(seq).push(item);
    });
    itemsBySeq.forEach((arr) => arr.sort((a, b) => (a.start?.getTime?.() || 0) - (b.start?.getTime?.() || 0)));

    const isWithinShift = (item, shift) => {
      if (!shift.start || !shift.end) return true;
      const [startHour, startMin] = shift.start.split(':').map((n) => Number(n));
      const [endHour, endMin] = shift.end.split(':').map((n) => Number(n));
      const itemStart = item.start;
      const itemTotal = (itemStart.getHours() * 60) + itemStart.getMinutes();
      const shiftStartTotal = (startHour * 60) + (startMin || 0);
      const shiftEndTotal = (endHour * 60) + (endMin || 0);
      return itemTotal >= shiftStartTotal && itemTotal <= shiftEndTotal;
    };

    const stripSeqPrefix = (value) => String(value || '').replace(/^\s*\d+\s*-\s*/g, '').trim();
    const toMin = (start, end) => {
      if (!start || !end) return 0;
      return Math.max(0, Math.round((end.getTime() - start.getTime()) / 60000));
    };

    const rowsHtml = Object.entries(grouped)
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([dateKey, items]) => {
        const displayDate = typeof formatDateKeyPt === 'function' ? formatDateKeyPt(dateKey) : dateKey;
        const weekday = new Date(`${dateKey}T00:00`).getDay();
        const shifts = calendarIntervals
          .filter((interval) => (interval.days || []).includes(weekday))
          .map((interval) => ({
            label: interval.name || interval.shift || 'Turno',
            start: interval.start,
            end: interval.end,
          }));

        const dayHtml = shifts
          .map((shift) => {
            const shiftItems = items.filter((it) => isWithinShift(it, shift));
            if (!shiftItems.length) return '';

            const groups = shiftItems.reduce((map, it) => {
              const seqKey = String(it?.seq || '').trim() || `__no_seq__${String(it?.idx ?? '')}`;
              if (!map[seqKey]) map[seqKey] = [];
              map[seqKey].push(it);
              return map;
            }, {});

            const groupHtml = Object.entries(groups)
              .map(([seqKey, groupItems]) => {
                const ordered = [...groupItems].sort((a, b) => (a.start?.getTime?.() || 0) - (b.start?.getTime?.() || 0));
                const start = ordered[0]?.start || null;
                const end = ordered.reduce((max, it) => (it.end?.getTime?.() || 0) > (max?.getTime?.() || 0) ? it.end : max, ordered[0]?.end || null);

                const prodMin = ordered.reduce((sum, it) => sum + (!it.isSetup ? toMin(it.start, it.end) : 0), 0);
                const setupMin = ordered.reduce((sum, it) => sum + (it.isSetup ? toMin(it.start, it.end) : 0), 0);
                const setupCount = ordered.filter((it) => it.isSetup).length;

                const op = String(ordered.find((it) => String(it?.op || '').trim())?.op || '').trim();
                const prodLabel = ordered.find((it) => !it.isSetup)?.label || ordered[0]?.label || '';
                const productName = stripSeqPrefix(prodLabel) || '-';

                const headerTitle = `${op ? `OP ${op} / ` : ''}Seq ${seqKey} – ${productName}`;
                const opDayDiff = diffDaysLocal(start, end);
                const startMeta = start
                  ? (opDayDiff > 0 ? `${formatDateOnlyPtShort(start)} ${formatTimeOnlyPt(start)}` : formatTimeOnlyPt(start))
                  : '--:--';
                const endMeta = end
                  ? (opDayDiff > 0 ? `${formatDateOnlyPtShort(end)} ${formatTimeOnlyPt(end)}` : formatTimeOnlyPt(end))
                  : '--:--';
                const daySpanText = opDayDiff > 0 ? ` • ${opDayDiff + 1} dias` : '';
                const headerMeta = `${startMeta} → ${endMeta}${daySpanText} • Prod ${formatDurationMinutesToHHMM(prodMin)} • Setup ${formatDurationMinutesToHHMM(setupMin)} (${setupCount})`;
                const multiDayBadge = opDayDiff > 0 ? '<span class="performance-alt-badge is-multiday">Multi-dia</span>' : '';

                const itemHtml = ordered.map((it) => {
                  const startTime = formatTimeOnlyPt(it.start);
                  const endTime = formatTimeOnlyPt(it.end);
                  const dayDiff = diffDaysLocal(it.start, it.end);
                  const endSuffix = dayDiff > 0 ? ` <span class="performance-alt-item-multiday">(+${dayDiff}d)</span>` : '';
                  const endPinText = dayDiff > 0 ? `${endTime}+${dayDiff}d` : endTime;

                  const span = computeSpanWithinShift(it.start, it.end, shift.start, shift.end);
                  const leftPct = Math.round(span.leftPct * 100) / 100;
                  const widthPct = Math.round(span.widthPct * 100) / 100;
                  const clampPct = (value) => Math.min(98, Math.max(2, value));
                  const endPct = clampPct(leftPct + widthPct);
                  const pinStartPct = clampPct(leftPct);

                  const duration = Number(it?.row?.sch_duracao_minutos) || 0;
                  const qty = it?.row?.sch_quantidade ? `Qty ${it.row.sch_quantidade}` : '';
                  const title = `${it.label}\n${formatDateTimeShortPt(it.start)} → ${formatDateTimeShortPt(it.end)}\n${qty}${qty ? ' • ' : ''}${duration ? `${Math.floor(duration / 60)}h${duration % 60}m` : ''}`;

                  const barTextRaw = it.isSetup ? 'Setup' : stripSeqPrefix(it.label);
                  const mode = (() => {
                    if (!barTextRaw) return 'none';
                    if (it.isSetup) {
                      if (widthPct >= 10) return 'always';
                      if (widthPct >= 6) return 'hover';
                      return 'none';
                    }
                    if (widthPct >= 18) return 'always';
                    if (widthPct >= 8) return 'hover';
                    return 'none';
                  })();
                  const barTextHtml = mode !== 'none'
                    ? `<span class="performance-alt-item-bar-text" data-mode="${escapeHtml(mode)}">${escapeHtml(barTextRaw)}</span>`
                    : '';

                  return `
                    <div class="performance-alt-item ${it.isSetup ? 'is-setup' : ''}" data-alt-idx="${escapeHtml(String(it.idx))}" data-seq="${escapeHtml(it.seq || '')}" data-op="${escapeHtml(it.op || '')}" data-bar-left="${leftPct}" data-bar-width="${widthPct}" title="${escapeHtml(title)}">
                      <span class="performance-alt-item-bar ${it.isSetup ? 'is-setup' : 'is-prod'}" style="left:${leftPct}%;width:${widthPct}%">${barTextHtml}</span>
                      <span class="performance-alt-pin is-start" style="left:${pinStartPct}%" aria-hidden="true">${escapeHtml(startTime)}</span>
                      <span class="performance-alt-pin is-end" style="left:${endPct}%" aria-hidden="true">${escapeHtml(endPinText)}</span>
                      <span class="performance-alt-item-label">${escapeHtml(it.label)}</span>
                      <span class="performance-alt-item-time">${escapeHtml(startTime)} ↔ ${escapeHtml(endTime)}${endSuffix}</span>
                    </div>
                  `;
                }).join('');

                return `
                  <div class="performance-alt-op" data-seq="${escapeHtml(seqKey)}">
                    <div class="performance-alt-op-header">
                      <div class="performance-alt-op-title" title="${escapeHtml(headerTitle)}">${escapeHtml(headerTitle)} ${multiDayBadge}</div>
                      <div class="performance-alt-op-meta">${escapeHtml(headerMeta)}</div>
                    </div>
                    <div class="performance-alt-op-items">${itemHtml}</div>
                  </div>
                `;
              })
              .join('');

            return `
              <div class="performance-alt-shift">
                <div class="performance-alt-shift-label">${escapeHtml(shift.label)} (${escapeHtml(shift.start)} - ${escapeHtml(shift.end)})</div>
                <div class="performance-alt-shift-items">${groupHtml}</div>
              </div>
            `;
          })
          .join('') || '<div class="performance-alt-no-shift">Sem items nos turnos definidos.</div>';

        return `
          <div class="performance-alt-day">
            <div class="performance-alt-day-header">${escapeHtml(displayDate)}</div>
            <div class="performance-alt-day-items">${dayHtml}</div>
          </div>
        `;
      })
      .join('');

    const summaryShell = '<div id="performance-alt-summary" class="performance-alt-summary muted">Clique em um item para ver detalhes.</div>';
    if (!rowsHtml) {
      body.innerHTML = summaryShell + '<div class="muted">Nenhum evento válido encontrado.</div>';
    } else {
      body.innerHTML = summaryShell + rowsHtml;
    }

    window.__performanceAltState = { itemByIdx: altItemByIdx, itemsBySeq };
    updatePerformanceAltSummary();

    const totals = scheduleRows.reduce((sum, row) => {
      const dur = Number(row?.sch_duracao_minutos) || 0;
      if (String(row?.sch_tipo || '').trim().toLowerCase() === 'setup') {
        sum.setup += dur;
        sum.setupCount += 1;
      } else {
        sum.prod += dur;
        sum.prodCount += 1;
      }
      return sum;
    }, { prod: 0, setup: 0, prodCount: 0, setupCount: 0 });

    const indicator = document.getElementById('performance-alt-indicator');
    if (indicator) {
      indicator.innerHTML = `
        <div class="performance-alt-indicator-item">
          <div>Produção total</div>
          <strong>${Math.floor(totals.prod / 60)}h ${totals.prod % 60}m</strong>
          <small>${totals.prodCount} itens</small>
        </div>
        <div class="performance-alt-indicator-item">
          <div>Setups</div>
          <strong>${Math.floor(totals.setup / 60)}h ${totals.setup % 60}m</strong>
          <small>${totals.setupCount} itens</small>
        </div>
      `;
    }

    container.classList.remove('hidden');
    highlightAlternativeSelection(window.__performanceGanttState?.selectedByContainer?.['performance-gantt-a']);
    renderPerformanceAltConnectors();

    if (container.dataset.altListener !== '1') {
      container.dataset.altListener = '1';
      container.addEventListener('click', (event) => {
        const item = event.target.closest('.performance-alt-item[data-alt-idx]');
        if (item) {
          if (typeof setPerformanceGanttSelection === 'function') {
            setPerformanceGanttSelection('performance-gantt-a', item.dataset.altIdx);
          }
        }
      });
    }

    document.getElementById('performance-alt-filter-prod')?.addEventListener('change', () => renderPerformanceAlternative(detail));
    document.getElementById('performance-alt-filter-setup')?.addEventListener('change', () => renderPerformanceAlternative(detail));
    container.addEventListener('mouseover', (event) => {
      const hover = event.target.closest('.performance-alt-item[data-alt-idx]');
      body.querySelectorAll('.performance-alt-item').forEach((el) => el.classList.remove('is-hover'));
      if (hover) hover.classList.add('is-hover');
    });
    container.addEventListener('mouseleave', () => {
      body.querySelectorAll('.performance-alt-item').forEach((el) => el.classList.remove('is-hover'));
    });

    if (container.dataset.altResizeListener !== '1') {
      container.dataset.altResizeListener = '1';
      let resizeTimer = null;
      window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => renderPerformanceAltConnectors(), 150);
      });
    }
  }

  function renderPerformanceAltConnectors() {
    const body = document.getElementById('performance-alt-body');
    if (!body) return;

    const SVG_NS = 'http://www.w3.org/2000/svg';
    const clampPx = (value, min, max) => Math.min(max, Math.max(min, value));

    body.querySelectorAll('.performance-alt-shift-items').forEach((shiftEl) => {
      shiftEl.querySelectorAll('svg.performance-alt-connectors').forEach((el) => el.remove());

      const items = Array.from(shiftEl.querySelectorAll('.performance-alt-item'));
      if (items.length < 2) return;

      const connectors = [];
      for (let i = 0; i < items.length; i += 1) {
        const from = items[i];
        if (!from.classList.contains('is-setup')) continue;
        const seq = String(from.dataset.seq || '').trim();
        if (!seq) continue;
        const to = items.slice(i + 1).find((next) => !next.classList.contains('is-setup') && String(next.dataset.seq || '').trim() === seq);
        if (!to) continue;
        connectors.push({ from, to });
      }
      if (!connectors.length) return;

      const width = shiftEl.clientWidth;
      const height = shiftEl.scrollHeight;
      if (!width || !height) return;

      const svg = document.createElementNS(SVG_NS, 'svg');
      svg.classList.add('performance-alt-connectors');
      svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
      svg.setAttribute('preserveAspectRatio', 'none');

      const defs = document.createElementNS(SVG_NS, 'defs');
      const marker = document.createElementNS(SVG_NS, 'marker');
      marker.setAttribute('id', 'perf-alt-arrow');
      marker.setAttribute('markerWidth', '8');
      marker.setAttribute('markerHeight', '8');
      marker.setAttribute('refX', '7');
      marker.setAttribute('refY', '4');
      marker.setAttribute('orient', 'auto');
      const arrow = document.createElementNS(SVG_NS, 'path');
      arrow.setAttribute('d', 'M0,0 L8,4 L0,8 Z');
      arrow.setAttribute('fill', 'rgba(100, 116, 139, 0.65)');
      marker.appendChild(arrow);
      defs.appendChild(marker);
      svg.appendChild(defs);

      connectors.forEach(({ from, to }) => {
        const fromLeft = Number(from.dataset.barLeft) || 0;
        const fromWidth = Number(from.dataset.barWidth) || 0;
        const toLeft = Number(to.dataset.barLeft) || 0;

        const x1 = clampPx(((fromLeft + fromWidth) / 100) * width, 0, width);
        let x2 = clampPx((toLeft / 100) * width, 0, width);
        if (x2 < x1 + 10) x2 = Math.min(width, x1 + 10);

        const y1 = clampPx(from.offsetTop + (from.offsetHeight / 2), 0, height);
        const y2 = clampPx(to.offsetTop + (to.offsetHeight / 2), 0, height);

        const path = document.createElementNS(SVG_NS, 'path');
        const c1x = x1 + 18;
        const c2x = x2 - 18;
        path.setAttribute('d', `M ${x1} ${y1} C ${c1x} ${y1}, ${c2x} ${y2}, ${x2} ${y2}`);
        path.setAttribute('class', 'performance-alt-connector');
        path.setAttribute('marker-end', 'url(#perf-alt-arrow)');
        svg.appendChild(path);
      });

      shiftEl.appendChild(svg);
    });
  }

  function updatePerformanceAltFocus(selectedEl) {
    if (!selectedEl) return;

    const leftPct = Number(selectedEl.dataset.barLeft);
    const widthPct = Number(selectedEl.dataset.barWidth);
    if (!Number.isFinite(leftPct) || !Number.isFinite(widthPct)) return;

    const clampPct = (value) => Math.min(100, Math.max(0, value));
    const endPct = clampPct(leftPct + widthPct);
    const shiftItems = selectedEl.closest('.performance-alt-shift-items');
    if (!shiftItems) return;

    const buildLine = (posPct, label, variant) => {
      const line = document.createElement('div');
      line.className = `performance-alt-focus-line ${variant || ''}`.trim();
      line.style.left = `${posPct}%`;
      line.innerHTML = `<span>${escapeHtml(label)}</span>`;
      return line;
    };

    shiftItems.appendChild(buildLine(clampPct(leftPct), 'Início', 'is-start'));
    shiftItems.appendChild(buildLine(endPct, 'Fim', 'is-end'));
  }

  function highlightAlternativeSelection(selectedIdx) {
    const body = document.getElementById('performance-alt-body');
    if (!body) return;

    const container = document.getElementById('performance-alt');
    if (container) {
      container.querySelectorAll('.performance-alt-focus-line').forEach((el) => el.remove());
    }

    let selectedEl = null;
    body.querySelectorAll('.performance-alt-item').forEach((el) => {
      const isSelected = el.dataset.altIdx && selectedIdx && String(el.dataset.altIdx) === String(selectedIdx);
      el.classList.toggle('is-selected', Boolean(isSelected));
      if (isSelected) selectedEl = el;
    });

    body.classList.toggle('has-selection', Boolean(selectedEl));
    updatePerformanceAltFocus(selectedEl);
  }

  function updatePerformanceAltSummary() {
    const el = document.getElementById('performance-alt-summary');
    if (!el) return;

    const selectedIdx = window.__performanceGanttState?.selectedByContainer?.['performance-gantt-a'];
    const item = selectedIdx !== undefined && selectedIdx !== null
      ? window.__performanceAltState?.itemByIdx?.get(String(selectedIdx))
      : null;

    if (!item) {
      el.classList.add('muted');
      el.innerHTML = 'Clique em um item para ver detalhes.';
      return;
    }

    const seqKey = String(item?.seq || '').trim();
    const seqItems = seqKey
      ? (window.__performanceAltState?.itemsBySeq?.get(seqKey) || [])
      : [];

    const op = String(item?.op || (seqItems.find((x) => x?.op)?.op) || '').trim() || '-';
    const tipo = item.isSetup ? 'Setup' : 'Produção';

    const range = seqItems.length
      ? {
          start: seqItems[0].start,
          end: seqItems.reduce((max, x) => (x.end?.getTime?.() || 0) > (max?.getTime?.() || 0) ? x.end : max, seqItems[0].end),
        }
      : { start: item.start, end: item.end };

    const diffDaysLocal = (start, end) => {
      if (!start || !end || Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 0;
      const s0 = new Date(start.getFullYear(), start.getMonth(), start.getDate());
      const e0 = new Date(end.getFullYear(), end.getMonth(), end.getDate());
      return Math.max(0, Math.round((e0.getTime() - s0.getTime()) / 86400000));
    };
    const dayDiff = diffDaysLocal(range.start, range.end);
    const endLabel = dayDiff > 0
      ? `${formatDateTimeShortPt(range.end)} (+${dayDiff}d)`
      : formatDateTimeShortPt(range.end);

    const totalProdMin = seqItems.reduce((sum, x) => sum + (!x.isSetup ? Math.max(0, Math.round((x.end - x.start) / 60000)) : 0), 0);
    const totalSetupMin = seqItems.reduce((sum, x) => sum + (x.isSetup ? Math.max(0, Math.round((x.end - x.start) / 60000)) : 0), 0);
    const setupCount = seqItems.filter((x) => x.isSetup).length;
    const totalMin = Math.max(1, totalProdMin + totalSetupMin);
    const prodPct = Math.round((totalProdMin / totalMin) * 1000) / 10;
    const setupPct = Math.round((totalSetupMin / totalMin) * 1000) / 10;

    el.classList.remove('muted');
    el.innerHTML = `
      <div><span>OP</span><strong>${escapeHtml(op)}</strong></div>
      <div><span>Seq</span><strong>${escapeHtml(seqKey || '-')}</strong></div>
      <div><span>Tipo</span><strong>${escapeHtml(tipo)}</strong></div>
      <div><span>Item</span><strong title="${escapeHtml(item.label)}">${escapeHtml(item.label)}</strong></div>
      <div><span>Início</span><strong>${escapeHtml(formatDateTimeShortPt(range.start))}</strong></div>
      <div><span>Fim</span><strong>${escapeHtml(endLabel)}</strong></div>
      <div><span>Dias</span><strong>${escapeHtml(String(dayDiff + 1))}</strong></div>
      <div><span>Setup</span><strong>${escapeHtml(formatDurationMinutesToHHMM(totalSetupMin))} (${escapeHtml(String(setupCount))})</strong></div>
      <div><span>Produção</span><strong>${escapeHtml(formatDurationMinutesToHHMM(totalProdMin))}</strong></div>
      <div><span>Total</span><strong>${escapeHtml(formatDurationMinutesToHHMM(totalProdMin + totalSetupMin))}</strong></div>
      <div class="performance-alt-mini" aria-hidden="true" title="Setup ${escapeHtml(String(setupPct))}% • Produção ${escapeHtml(String(prodPct))}%">
        <div class="performance-alt-mini-bar">
          <div class="is-setup" style="width:${setupPct}%"></div>
          <div class="is-prod" style="width:${prodPct}%"></div>
        </div>
        <div class="performance-alt-mini-legend">
          <span class="is-setup">Setup</span>
          <span class="is-prod">Produção</span>
        </div>
      </div>
    `;
  }

  window.renderPerformanceAlternative = renderPerformanceAlternative;
  window.renderPerformanceAltConnectors = renderPerformanceAltConnectors;
  window.highlightAlternativeSelection = highlightAlternativeSelection;
  window.updatePerformanceAltSummary = updatePerformanceAltSummary;
})();

