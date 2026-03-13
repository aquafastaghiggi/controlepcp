(function () {
    const bootstrap = window.PCP_BOOTSTRAP || { datasets: {}, sampleProgram: [] };
    const STORAGE_KEY = 'controlepcp.system.v1';

    const weekdays = [
        { value: 1, label: 'Seg' },
        { value: 2, label: 'Ter' },
        { value: 3, label: 'Qua' },
        { value: 4, label: 'Qui' },
        { value: 5, label: 'Sex' },
        { value: 6, label: 'Sab' },
        { value: 7, label: 'Dom' },
    ];

    const form = document.getElementById('simulation-form');
    const addRowButton = document.getElementById('add-row');
    const clearButton = document.getElementById('clear-simulation');
    const addIntervalButton = document.getElementById('add-interval');
    const addProductButton = document.getElementById('add-product');
    const addMatrixRowButton = document.getElementById('add-matrix-row');
    const navLinks = document.querySelectorAll('[data-target]');

    const baseStartInput = form.querySelector('[name="base_start"]');
    const queryDateTimeInput = form.querySelector('[name="query_datetime"]');
    const holidayInput = document.getElementById('holiday-input');
    const weekdayGroup = document.getElementById('weekday-group');

    const programBody = document.getElementById('program-body');
    const calendarBody = document.getElementById('calendar-body');
    const productsBody = document.getElementById('products-body');
    const matrixBody = document.getElementById('matrix-body');
    const resultBody = document.getElementById('result-body');
    const resultStatus = document.getElementById('result-status');
    const resultSummary = document.getElementById('result-summary');
    const entryTableWrap = document.querySelector('.entry-table-wrap');

    const defaultDatasets = JSON.parse(JSON.stringify(bootstrap.datasets || {}));
    const defaultProgram = JSON.parse(JSON.stringify(bootstrap.sampleProgram || []));

    const state = {
        datasets: normalizeDatasets(defaultDatasets),
        form: {
            base_start: baseStartInput.value,
            query_datetime: queryDateTimeInput.value,
            items: defaultProgram.length ? defaultProgram : [{}],
        },
        result: null,
        activeSection: 'section-program',
    };

    function normalizeDatasets(raw) {
        const calendar = raw.calendar || {};
        const intervals = Array.isArray(calendar.intervals) ? calendar.intervals : [];
        const products = raw.products || {};
        const setupMatrix = raw.setup_matrix || {};

        return {
            calendar: {
                line: calendar.line || 'L2',
                working_days: Array.isArray(calendar.working_days) && calendar.working_days.length ? calendar.working_days : [1, 2, 3, 4, 5],
                holidays: Array.isArray(calendar.holidays) ? calendar.holidays : [],
                intervals: intervals.length ? intervals : [{ start: '07:10', end: '11:28' }],
            },
            products,
            setup_matrix: setupMatrix,
        };
    }

    function loadState() {
        try {
            const raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }

            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return;
            }

            state.datasets = normalizeDatasets(parsed.datasets || defaultDatasets);
            state.form = {
                base_start: parsed.form?.base_start || state.form.base_start,
                query_datetime: parsed.form?.query_datetime || state.form.query_datetime,
                items: Array.isArray(parsed.form?.items) && parsed.form.items.length ? parsed.form.items : state.form.items,
            };
            state.result = parsed.result || null;
            state.activeSection = parsed.activeSection || state.activeSection;
        } catch (error) {
            // keep defaults
        }
    }

    function hasCurrentMemoryFormat(result) {
        if (!result || !Array.isArray(result.rows)) {
            return false;
        }

        return result.rows.every((row) => typeof row === 'object' && row !== null && 'production_end' in row);
    }
    function saveState() {
        const payload = {
            datasets: state.datasets,
            form: {
                base_start: baseStartInput.value,
                query_datetime: queryDateTimeInput.value,
                items: readProgramRows(),
            },
            result: state.result,
            activeSection: state.activeSection,
        };

        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    }

    function clearState() {
        window.localStorage.removeItem(STORAGE_KEY);
    }

    function flattenMatrix(matrix) {
        const rows = [];

        Object.entries(matrix || {}).forEach(([from, targets]) => {
            Object.entries(targets || {}).forEach(([to, duration]) => {
                rows.push({ from, to, duration });
            });
        });

        return rows.sort((left, right) => `${left.from}-${left.to}`.localeCompare(`${right.from}-${right.to}`));
    }

    function buildMatrix(rows) {
        const matrix = {};

        rows.forEach((row) => {
            const from = String(row.from || '').trim();
            const to = String(row.to || '').trim();
            const duration = String(row.duration || '').trim();

            if (!from || !to || !duration) {
                return;
            }

            if (!matrix[from]) {
                matrix[from] = {};
            }

            matrix[from][to] = duration;
        });

        return matrix;
    }

    function productOptions(selectedValue) {
        const entries = Object.entries(state.datasets.products || {});
        const options = ['<option value="">Selecione</option>'];

        entries.forEach(([sku]) => {
            options.push(`<option value="${sku}" ${sku === selectedValue ? 'selected' : ''}>${sku}</option>`);
        });

        return options.join('');
    }

    function setStatus(label, variant) {
        resultStatus.textContent = label;
        resultStatus.dataset.variant = variant;
    }

    function activateSection(sectionId) {
        state.activeSection = sectionId;
        document.querySelectorAll('.app-section').forEach((section) => {
            section.classList.toggle('is-active', section.id === sectionId);
        });
        document.querySelectorAll('.nav-link, .nav-shortcut').forEach((button) => {
            button.classList.toggle('is-current', button.dataset.target === sectionId);
        });
        saveState();
    }

    function renderWeekdays() {
        weekdayGroup.innerHTML = weekdays
            .map((day) => `
                <label class="weekday-pill">
                    <input type="checkbox" value="${day.value}" ${state.datasets.calendar.working_days.includes(day.value) ? 'checked' : ''}>
                    <span>${day.label}</span>
                </label>
            `)
            .join('');

        weekdayGroup.querySelectorAll('input').forEach((input) => {
            input.addEventListener('change', () => {
                state.datasets.calendar.working_days = [...weekdayGroup.querySelectorAll('input:checked')].map((element) => Number(element.value));
                saveState();
            });
        });
    }

    function renderCalendar() {
        holidayInput.value = state.datasets.calendar.holidays.join('\n');
        calendarBody.innerHTML = state.datasets.calendar.intervals
            .map((interval, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td><input type="time" data-calendar-index="${index}" data-field="start" value="${interval.start}" required></td>
                    <td><input type="time" data-calendar-index="${index}" data-field="end" value="${interval.end}" required></td>
                    <td class="actions-cell"><button type="button" class="row-delete" data-remove-interval="${index}">Remover</button></td>
                </tr>
            `)
            .join('');

        calendarBody.querySelectorAll('input').forEach((input) => {
            input.addEventListener('change', () => {
                const index = Number(input.dataset.calendarIndex);
                const field = input.dataset.field;
                state.datasets.calendar.intervals[index][field] = input.value;
                saveState();
            });
        });

        calendarBody.querySelectorAll('[data-remove-interval]').forEach((button) => {
            button.addEventListener('click', () => {
                state.datasets.calendar.intervals.splice(Number(button.dataset.removeInterval), 1);
                if (!state.datasets.calendar.intervals.length) {
                    state.datasets.calendar.intervals.push({ start: '07:10', end: '11:28' });
                }
                renderCalendar();
                saveState();
            });
        });
    }

    function renderProducts() {
        const rows = Object.entries(state.datasets.products || {});
        productsBody.innerHTML = rows
            .map(([sku, product]) => `
                <tr>
                    <td><input type="text" data-product-sku="${sku}" data-field="sku" value="${sku}"></td>
                    <td><input type="text" data-product-sku="${sku}" data-field="description" value="${product.description || ''}"></td>
                    <td><input type="text" data-product-sku="${sku}" data-field="line" value="${product.line || 'L2'}"></td>
                    <td><input type="number" data-product-sku="${sku}" data-field="rate_per_hour" min="0" step="0.01" value="${product.rate_per_hour ?? ''}"></td>
                    <td><input type="text" data-product-sku="${sku}" data-field="unit" value="${product.unit || 'cx'}"></td>
                    <td class="actions-cell"><button type="button" class="row-delete" data-remove-product="${sku}">Remover</button></td>
                </tr>
            `)
            .join('');

        productsBody.querySelectorAll('input').forEach((input) => {
            input.addEventListener('change', () => {
                const originalSku = input.dataset.productSku;
                const field = input.dataset.field;

                if (field === 'sku') {
                    const newSku = input.value.trim();
                    if (!newSku || newSku === originalSku) {
                        renderProducts();
                        return;
                    }

                    state.datasets.products[newSku] = { ...state.datasets.products[originalSku] };
                    delete state.datasets.products[originalSku];
                    remapSkuReferences(originalSku, newSku);
                    renderAllDatasetTables();
                    renderProgram();
                    saveState();
                    return;
                }

                const target = state.datasets.products[originalSku];
                if (!target) {
                    return;
                }

                target[field] = field === 'rate_per_hour' ? Number(input.value) : input.value;
                saveState();
            });
        });

        productsBody.querySelectorAll('[data-remove-product]').forEach((button) => {
            button.addEventListener('click', () => {
                const sku = button.dataset.removeProduct;
                delete state.datasets.products[sku];
                remapSkuReferences(sku, '');
                removeMatrixReferences(sku);
                renderAllDatasetTables();
                renderProgram();
                saveState();
            });
        });
    }

    function renderMatrix() {
        const rows = flattenMatrix(state.datasets.setup_matrix);
        matrixBody.innerHTML = rows
            .map((row, index) => `
                <tr>
                    <td><select data-matrix-index="${index}" data-field="from">${productOptions(row.from)}</select></td>
                    <td><select data-matrix-index="${index}" data-field="to">${productOptions(row.to)}</select></td>
                    <td><input type="text" data-matrix-index="${index}" data-field="duration" value="${row.duration}"></td>
                    <td class="actions-cell"><button type="button" class="row-delete" data-remove-matrix="${index}">Remover</button></td>
                </tr>
            `)
            .join('');

        const bindMatrixState = () => {
            const currentRows = [...matrixBody.querySelectorAll('tr')].map((row) => ({
                from: row.querySelector('[data-field="from"]').value,
                to: row.querySelector('[data-field="to"]').value,
                duration: row.querySelector('[data-field="duration"]').value,
            }));
            state.datasets.setup_matrix = buildMatrix(currentRows);
        };

        matrixBody.querySelectorAll('select, input').forEach((field) => {
            field.addEventListener('change', () => {
                bindMatrixState();
                saveState();
            });
        });

        matrixBody.querySelectorAll('[data-remove-matrix]').forEach((button) => {
            button.addEventListener('click', () => {
                const rowsNow = [...matrixBody.querySelectorAll('tr')].map((row) => ({
                    from: row.querySelector('[data-field="from"]').value,
                    to: row.querySelector('[data-field="to"]').value,
                    duration: row.querySelector('[data-field="duration"]').value,
                }));
                rowsNow.splice(Number(button.dataset.removeMatrix), 1);
                state.datasets.setup_matrix = buildMatrix(rowsNow);
                renderMatrix();
                saveState();
            });
        });
    }

    function remapSkuReferences(fromSku, toSku) {
        state.form.items = state.form.items.map((item) => ({
            ...item,
            sku: item.sku === fromSku ? toSku : item.sku,
        }));

        const rows = flattenMatrix(state.datasets.setup_matrix).map((row) => ({
            from: row.from === fromSku ? toSku : row.from,
            to: row.to === fromSku ? toSku : row.to,
            duration: row.duration,
        }));
        state.datasets.setup_matrix = buildMatrix(rows);
    }

    function removeMatrixReferences(sku) {
        const rows = flattenMatrix(state.datasets.setup_matrix).filter((row) => row.from !== sku && row.to !== sku);
        state.datasets.setup_matrix = buildMatrix(rows);
    }

    function renderProgram() {
        programBody.innerHTML = '';
        const items = state.form.items.length ? state.form.items : [{}];
        items.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="number" class="mini-input" name="sequence" value="${item.sequence || index + 1}" min="1" required></td>
                <td><select name="sku" required>${productOptions(item.sku || '')}</select></td>
                <td><input type="number" name="quantity" min="1" step="1" value="${item.quantity || ''}" required></td>
                <td><input type="datetime-local" name="planned_start" value="${item.planned_start || ''}"></td>
                <td class="actions-cell"><button type="button" class="row-delete">Remover</button></td>
            `;
            programBody.appendChild(tr);

            const plannedStart = tr.querySelector('[name="planned_start"]');
            plannedStart.disabled = index !== 0;
            if (index !== 0) {
                plannedStart.value = '';
            }

            tr.querySelector('.row-delete').addEventListener('click', () => {
                state.form.items.splice(index, 1);
                if (!state.form.items.length) {
                    state.form.items = [{}];
                }
                renderProgram();
                saveState();
            });

            tr.querySelectorAll('input, select').forEach((field) => {
                field.addEventListener('change', syncProgramState);
                field.addEventListener('input', syncProgramState);
            });
        });
    }

    function syncProgramState() {
        state.form.items = readProgramRows();
        saveState();
    }

    function readProgramRows() {
        return [...programBody.querySelectorAll('tr')].map((row, index) => ({
            sequence: Number(row.querySelector('[name="sequence"]').value),
            sku: row.querySelector('[name="sku"]').value,
            quantity: Number(row.querySelector('[name="quantity"]').value),
            planned_start: index === 0 ? row.querySelector('[name="planned_start"]').value : '',
        }));
    }

    function renderSummary(result) {
        const productionRows = result.rows.filter((row) => row.type === 'production');
        const totalQty = productionRows.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0);
        const calculatedRows = productionRows.filter((row) => row.status === 'Calculado').length;

        resultSummary.innerHTML = `
            <div class="summary-card"><span>Total de ordens</span><strong>${productionRows.length}</strong></div>
            <div class="summary-card"><span>Ordens calculadas</span><strong>${calculatedRows}</strong></div>
            <div class="summary-card"><span>Caixas programadas</span><strong>${formatNumber(totalQty)}</strong></div>
        `;
    }

    function parsePtBrDateTime(value) {
        if (!value || !value.includes(' ')) {
            return null;
        }

        const [datePart, timePart] = value.split(' ');
        const [day, month, year] = datePart.split('/').map(Number);
        const [hour, minute] = timePart.split(':').map(Number);
        return new Date(year, month - 1, day, hour || 0, minute || 0);
    }

    function formatEndMeta(row) {
        const endDate = parsePtBrDateTime(row.production_end);

        if (!endDate) {
            return '';
        }

        const dateLabel = new Intl.DateTimeFormat('pt-BR').format(endDate);
        const weekdayLabel = new Intl.DateTimeFormat('pt-BR', { weekday: 'long' }).format(endDate);

        return `<span class="end-meta">${dateLabel}<small>${weekdayLabel}</small></span>`;
    }

    function renderRows(rows) {
        if (!rows.length) {
            resetResultArea(false);
            return;
        }

        resultBody.innerHTML = rows.map((row) => `
            <tr class="${row.type === 'setup' ? 'setup-row' : ''}">
                <td>${row.type === 'setup' ? 'Setup' : ''}</td>
                <td>${row.sequence ?? ''}</td>
                <td>${row.description || row.sku}</td>
                <td>${row.rate_per_hour ?? ''}</td>
                <td>${formatNumber(row.quantity)}</td>
                <td>${row.duration_label || ''}</td>
                <td>${row.date_start || ''}</td>
                <td>${row.time_start || ''}</td>
                <td class="is-hidden-column">${row.calculation_memory || ''}</td>
                <td>${row.time_end || ''}${formatEndMeta(row)}</td>
            </tr>
        `).join('');
    }

    function renderResult(result, persist = true) {
        state.result = result;
        renderSummary(result);
        renderRows(result.rows || []);
        setStatus(result.meta.errors.length ? 'Calculado com alertas' : 'Calculado', result.meta.errors.length ? 'warning' : 'success');
        if (persist) {
            saveState();
        }
    }

    function resetResultArea(persist = true) {
        state.result = null;
        resultSummary.innerHTML = '';
        resultBody.innerHTML = '<tr class="empty-state-row"><td colspan="10">Nenhuma simulaÃ§Ã£o calculada ainda.</td></tr>';
        setStatus('Aguardando cÃ¡lculo', 'idle');
        if (persist) {
            saveState();
        }
    }

    function renderAllDatasetTables() {
        renderWeekdays();
        renderCalendar();
        renderProducts();
        renderMatrix();
    }

    addIntervalButton.addEventListener('click', () => {
        state.datasets.calendar.intervals.push({ start: '07:10', end: '11:28' });
        renderCalendar();
        saveState();
    });

    addProductButton.addEventListener('click', () => {
        const nextSku = `NOVO SKU ${Object.keys(state.datasets.products).length + 1}`;
        state.datasets.products[nextSku] = {
            description: 'Novo produto',
            line: state.datasets.calendar.line || 'L2',
            rate_per_hour: 0,
            unit: 'cx',
        };
        renderProducts();
        renderMatrix();
        renderProgram();
        saveState();
    });

    addMatrixRowButton.addEventListener('click', () => {
        const firstSku = Object.keys(state.datasets.products)[0] || '';
        const rows = flattenMatrix(state.datasets.setup_matrix);
        rows.push({ from: firstSku, to: firstSku, duration: '00:20' });
        state.datasets.setup_matrix = buildMatrix(rows);
        renderMatrix();
        saveState();
    });

    holidayInput.addEventListener('change', () => {
        state.datasets.calendar.holidays = holidayInput.value
            .split(/\r?\n/)
            .map((value) => value.trim())
            .filter(Boolean);
        saveState();
    });

    baseStartInput.addEventListener('change', () => {
        state.form.base_start = baseStartInput.value;
        saveState();
    });

    queryDateTimeInput.addEventListener('change', () => {
        state.form.query_datetime = queryDateTimeInput.value;
        saveState();
    });

    addRowButton.addEventListener('click', () => {
        state.form.items.push({
            sequence: state.form.items.length + 1,
            sku: '',
            quantity: '',
            planned_start: '',
        });
        renderProgram();
        saveState();
        window.requestAnimationFrame(() => {
            entryTableWrap?.scrollTo({ top: entryTableWrap.scrollHeight, behavior: 'smooth' });
        });
    });

    clearButton.addEventListener('click', () => {
        state.form.items = [{}];
        state.result = null;
        renderProgram();
        resetResultArea(false);
        saveState();
    });

    navLinks.forEach((button) => {
        button.addEventListener('click', () => {
            activateSection(button.dataset.target);
            const parentDetails = button.closest('details');
            if (parentDetails) {
                parentDetails.open = false;
            }
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        state.form.items = readProgramRows();
        state.form.base_start = baseStartInput.value;
        state.form.query_datetime = queryDateTimeInput.value;
        setStatus('Calculando...', 'loading');
        saveState();

        try {
            const response = await fetch('/controlepcp/api/calculate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    base_start: state.form.base_start,
                    query_datetime: state.form.query_datetime,
                    items: state.form.items.filter((item) => item.sku),
                    datasets: state.datasets,
                }),
            });

            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || 'Falha ao calcular.');
            }

            renderResult(result);
            activateSection('section-program');
        } catch (error) {
            resultSummary.innerHTML = '';
            resultBody.innerHTML = `<tr class="empty-state-row"><td colspan="10">${error.message}</td></tr>`;
            setStatus('Erro no cÃ¡lculo', 'danger');
            state.result = null;
            saveState();
        }
    });

    loadState();
    renderAllDatasetTables();
    renderProgram();
    baseStartInput.value = state.form.base_start || baseStartInput.value;
    queryDateTimeInput.value = state.form.query_datetime || queryDateTimeInput.value;
    activateSection(state.activeSection);

    if (hasCurrentMemoryFormat(state.result)) {
        renderResult(state.result, false);
    } else {
        resetResultArea(false);
    }

    window.addEventListener('beforeunload', saveState);
})();

