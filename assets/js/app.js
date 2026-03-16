(function () {
    const bootstrap = window.PCP_BOOTSTRAP || { datasets: {}, sampleProgram: [] };
    const STORAGE_KEY = 'controlepcp.system.v3';
    const DEFAULT_SECTION = 'section-home';
    const DEFAULT_INTERVAL_DAYS = [1, 2, 3, 4, 5];

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
    const importProductsButton = document.getElementById('import-products');
    const productsImportFile = document.getElementById('products-import-file');
    const clearProductsButton = document.getElementById('clear-products');
    const addProductButton = document.getElementById('add-product');
    const importMatrixButton = document.getElementById('import-matrix');
    const matrixImportFile = document.getElementById('matrix-import-file');
    const clearMatrixButton = document.getElementById('clear-matrix');
    const addMatrixRowButton = document.getElementById('add-matrix-row');
    const navLinks = document.querySelectorAll('[data-target]');

    const baseStartInput = form.querySelector('[name="base_start"]');
    const queryDateTimeInput = form.querySelector('[name="query_datetime"]');
    const holidayDateInput = document.getElementById('holiday-date');
    const holidayNameInput = document.getElementById('holiday-name');
    const addHolidayButton = document.getElementById('add-holiday');
    const holidayPreview = document.getElementById('holiday-preview');
    const appToast = document.getElementById('app-toast');

    const programBody = document.getElementById('program-body');
    const calendarBody = document.getElementById('calendar-body');
    const productsBody = document.getElementById('products-body');
    const matrixBody = document.getElementById('matrix-body');
    const matrixLineNav = document.getElementById('matrix-line-nav');
    const resultPanel = document.getElementById('result-panel');
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
        activeSection: DEFAULT_SECTION,
        activeMatrixLine: '',
    };
    let toastTimer = null;

    function showToast(message, variant = 'success') {
        if (!appToast) {
            return;
        }

        window.clearTimeout(toastTimer);
        appToast.textContent = message;
        appToast.dataset.variant = variant;
        appToast.classList.add('is-visible');

        toastTimer = window.setTimeout(() => {
            appToast.classList.remove('is-visible');
        }, 2400);
    }

    function normalizeInterval(interval) {
        const days = Array.isArray(interval?.days) && interval.days.length
            ? interval.days.map(Number).filter((value) => value >= 1 && value <= 7)
            : DEFAULT_INTERVAL_DAYS.slice();

        return {
            start: interval?.start || '07:10',
            end: interval?.end || '11:28',
            days,
        };
    }

    function normalizeDatasets(raw) {
        const calendar = raw.calendar || {};
        const intervals = Array.isArray(calendar.intervals) ? calendar.intervals : [];

        return {
            calendar: {
                line: calendar.line || 'L2',
                working_days: Array.isArray(calendar.working_days) && calendar.working_days.length
                    ? calendar.working_days.map(Number)
                    : DEFAULT_INTERVAL_DAYS.slice(),
                holidays: Array.isArray(calendar.holidays) ? calendar.holidays : [],
                intervals: intervals.length ? intervals.map(normalizeInterval) : [normalizeInterval({})],
            },
            products: raw.products || {},
            setup_matrix: raw.setup_matrix || {},
            setup_matrix_sections: Array.isArray(raw.setup_matrix_sections) ? raw.setup_matrix_sections : [],
        };
    }

    function hasCurrentResultFormat(result) {
        if (!result || !Array.isArray(result.rows) || !result.meta) {
            return false;
        }

        return result.rows.every((row) => typeof row === 'object' && row !== null && 'production_end' in row);
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
            state.result = hasCurrentResultFormat(parsed.result) ? parsed.result : null;
        } catch (error) {
            state.result = null;
        }
    }

    function readProgramRows() {
        return [...programBody.querySelectorAll('tr')].map((row, index) => ({
            sequence: Number(row.querySelector('[name="sequence"]').value) || index + 1,
            sku: row.querySelector('[name="sku"]').value,
            quantity: Number(row.querySelector('[name="quantity"]').value) || 0,
            planned_start: index === 0 ? row.querySelector('[name="planned_start"]').value : '',
        }));
    }

    function saveState() {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify({
            datasets: state.datasets,
            form: {
                base_start: baseStartInput.value,
                query_datetime: queryDateTimeInput.value,
                items: readProgramRows(),
            },
            result: state.result,
        }));
    }

    function formatNumber(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        return new Intl.NumberFormat('pt-BR').format(Number(value));
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

    function flattenMatrixSections(sections) {
        const rows = [];

        (sections || []).forEach((section) => {
            const line = String(section?.line || 'SEM LINHA').trim() || 'SEM LINHA';
            (section?.rows || []).forEach((row) => {
                rows.push({
                    line,
                    from: row.from,
                    to: row.to,
                    duration: row.duration,
                });
            });
        });

        return rows;
    }

    function buildMatrixSections(rows) {
        const sections = [];
        const byLine = new Map();

        (rows || []).forEach((row) => {
            const from = String(row.from || '').trim();
            const to = String(row.to || '').trim();
            const duration = String(row.duration || '').trim();
            const line = String(row.line || 'SEM LINHA').trim() || 'SEM LINHA';

            if (!from || !to || !duration) {
                return;
            }

            if (!byLine.has(line)) {
                const section = { line, rows: [] };
                byLine.set(line, section);
                sections.push(section);
            }

            byLine.get(line).rows.push({ from, to, duration, line });
        });

        return sections;
    }

    function getMatrixRowsWithLine() {
        const sectionRows = flattenMatrixSections(state.datasets.setup_matrix_sections || []);
        if (sectionRows.length) {
            return sectionRows;
        }

        return flattenMatrix(state.datasets.setup_matrix).map((row) => ({
            ...row,
            line: 'SEM LINHA',
        }));
    }

    function syncMatrixState(rows) {
        state.datasets.setup_matrix = buildMatrix(rows);
        state.datasets.setup_matrix_sections = buildMatrixSections(rows);
    }

    function defaultMatrixLineLabel() {
        const value = String(state.datasets.calendar.line || '').trim();
        if (!value) {
            return 'SEM LINHA';
        }

        const digits = value.replace(/\D+/g, '');
        return digits ? `LINHA ${digits}` : value.toUpperCase();
    }

    function getMatrixLines(rows = getMatrixRowsWithLine()) {
        return [...new Set(rows.map((row) => row.line || 'SEM LINHA'))]
            .sort((left, right) => left.localeCompare(right, 'pt-BR', { numeric: true }));
    }

    function renderMatrixLineNav(lines) {
        if (!matrixLineNav) {
            return;
        }

        if (!lines.length) {
            matrixLineNav.innerHTML = '';
            return;
        }

        if (!lines.includes(state.activeMatrixLine)) {
            state.activeMatrixLine = lines[0];
        }

        matrixLineNav.innerHTML = lines.map((line) => `
            <button type="button" class="matrix-line-tab ${line === state.activeMatrixLine ? 'is-current' : ''}" data-matrix-line="${line}">${line}</button>
        `).join('');

        matrixLineNav.querySelectorAll('[data-matrix-line]').forEach((button) => {
            button.addEventListener('click', () => {
                state.activeMatrixLine = button.dataset.matrixLine || '';
                renderMatrix();
            });
        });
    }

    function productOptions(selectedValue) {
        const entries = Object.entries(state.datasets.products || {});
        const options = ['<option value="">Selecione</option>'];
        const hasSelectedValue = entries.some(([sku]) => sku === selectedValue);

        if (selectedValue && !hasSelectedValue) {
            options.push(`<option value="${selectedValue}" selected>${selectedValue}</option>`);
        }

        entries.forEach(([sku]) => {
            options.push(`<option value="${sku}" ${sku === selectedValue ? 'selected' : ''}>${sku}</option>`);
        });

        return options.join('');
    }

    function setStatus(label, variant) {
        resultStatus.textContent = label;
        resultStatus.dataset.variant = variant;
    }

    function toggleResultPanel(visible) {
        resultPanel.classList.toggle('is-hidden', !visible);
    }

    function activateSection(sectionId) {
        const targetId = document.getElementById(sectionId) ? sectionId : DEFAULT_SECTION;
        state.activeSection = targetId;

        document.querySelectorAll('.app-section').forEach((section) => {
            section.classList.toggle('is-active', section.id === targetId);
        });

        document.querySelectorAll('.nav-shortcut, .home-card').forEach((button) => {
            button.classList.toggle('is-current', button.dataset.target === targetId);
        });
    }

    function intervalDaySelector(index, selectedDays) {
        return `
            <div class="weekday-inline-list">
                ${weekdays.map((day) => `
                    <label class="weekday-inline-item ${selectedDays.includes(day.value) ? 'is-selected' : ''}">
                        <input type="checkbox" data-calendar-index="${index}" data-day-value="${day.value}" ${selectedDays.includes(day.value) ? 'checked' : ''}>
                        <span>${day.label}</span>
                    </label>
                `).join('')}
            </div>
        `;
    }

    function normalizeHolidayList(values) {
        return values
            .map((value) => {
                if (typeof value === 'string') {
                    return { date: value.trim(), name: '' };
                }

                return {
                    date: String(value?.date || '').trim(),
                    name: String(value?.name || '').trim(),
                };
            })
            .filter((value) => value.date)
            .sort((left, right) => left.date.localeCompare(right.date))
            .filter((value, index, list) => index === list.findIndex((item) => item.date === value.date));
    }

    function syncHolidayState(values) {
        state.datasets.calendar.holidays = normalizeHolidayList(values);
        renderHolidayPreview();
        saveState();
    }

    function formatHolidayLabel(value) {
        const parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
            return value;
        }

        const [year, month, day] = parts;
        const date = new Date(year, month - 1, day);
        return new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            weekday: 'long',
        }).format(date);
    }

    function renderHolidayPreview() {
        const holidays = state.datasets.calendar.holidays || [];

        if (!holidays.length) {
            holidayPreview.innerHTML = '<div class="holiday-empty">Nenhum feriado lancado.</div>';
            return;
        }

        holidayPreview.innerHTML = holidays.map((holiday) => `
            <div class="holiday-card">
                <div class="holiday-card-head">
                    <strong>${holiday.name || 'Feriado sem nome'}</strong>
                    <button type="button" class="holiday-remove" data-holiday-remove="${holiday.date}">Excluir</button>
                </div>
                <span>${formatHolidayLabel(holiday.date)}</span>
            </div>
        `).join('');

        holidayPreview.querySelectorAll('[data-holiday-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                const holidayDate = button.dataset.holidayRemove;
                syncHolidayState((state.datasets.calendar.holidays || []).filter((item) => item.date !== holidayDate));
                showToast('Registro removido.');
            });
        });
    }
    function renderCalendar() {
        renderHolidayPreview();
        calendarBody.innerHTML = state.datasets.calendar.intervals.map((interval, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${intervalDaySelector(index, interval.days)}</td>
                <td><input type="time" data-calendar-index="${index}" data-field="start" value="${interval.start}" required></td>
                <td><input type="time" data-calendar-index="${index}" data-field="end" value="${interval.end}" required></td>
                <td class="actions-cell"><button type="button" class="row-delete" data-remove-interval="${index}">Remover</button></td>
            </tr>
        `).join('');

        calendarBody.querySelectorAll('[data-field]').forEach((input) => {
            input.addEventListener('change', () => {
                const index = Number(input.dataset.calendarIndex);
                const field = input.dataset.field;
                state.datasets.calendar.intervals[index][field] = input.value;
                saveState();
            });
        });

        calendarBody.querySelectorAll('[data-day-value]').forEach((input) => {
            input.addEventListener('change', () => {
                const index = Number(input.dataset.calendarIndex);
                const selected = [...calendarBody.querySelectorAll(`[data-calendar-index="${index}"][data-day-value]:checked`)]
                    .map((element) => Number(element.dataset.dayValue));

                state.datasets.calendar.intervals[index].days = selected.length ? selected : DEFAULT_INTERVAL_DAYS.slice();
                saveState();
            });
        });

        calendarBody.querySelectorAll('[data-remove-interval]').forEach((button) => {
            button.addEventListener('click', () => {
                state.datasets.calendar.intervals.splice(Number(button.dataset.removeInterval), 1);
                if (!state.datasets.calendar.intervals.length) {
                    state.datasets.calendar.intervals.push(normalizeInterval({}));
                }
                renderCalendar();
                saveState();
                showToast('Registro removido.');
            });
        });
    }

    function remapSkuReferences(fromSku, toSku) {
        state.form.items = state.form.items.map((item) => ({
            ...item,
            sku: item.sku === fromSku ? toSku : item.sku,
        }));

        const rows = getMatrixRowsWithLine().map((row) => ({
            ...row,
            from: row.from === fromSku ? toSku : row.from,
            to: row.to === fromSku ? toSku : row.to,
        }));

        syncMatrixState(rows);
    }

    function removeMatrixReferences(sku) {
        const rows = getMatrixRowsWithLine().filter((row) => row.from !== sku && row.to !== sku);
        syncMatrixState(rows);
    }

    function pruneCatalogReferences(availableSkus = Object.keys(state.datasets.products || {})) {
        const allowed = new Set(availableSkus);

        state.form.items = state.form.items.map((item) => ({
            ...item,
            sku: allowed.has(item.sku) ? item.sku : '',
        }));

        const rows = getMatrixRowsWithLine()
            .filter((row) => allowed.has(row.from) && allowed.has(row.to));

        syncMatrixState(rows);
    }

    function renderProducts() {
        const rows = Object.entries(state.datasets.products || {});
        productsBody.innerHTML = rows.map(([sku, product]) => `
            <tr>
                <td><input type="text" data-product-sku="${sku}" data-field="sku" value="${sku}"></td>
                <td><input type="text" data-product-sku="${sku}" data-field="description" value="${product.description || ''}"></td>
                <td><input type="text" data-product-sku="${sku}" data-field="line" value="${product.line || 'L2'}"></td>
                <td><input type="number" data-product-sku="${sku}" data-field="rate_per_hour" min="0" step="0.01" value="${product.rate_per_hour ?? ''}"></td>
                <td><input type="text" data-product-sku="${sku}" data-field="unit" value="${product.unit || 'cx'}"></td>
                <td class="actions-cell"><button type="button" class="row-delete" data-remove-product="${sku}">Remover</button></td>
            </tr>
        `).join('');

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
                showToast('Registro removido.');
            });
        });
    }

    function renderMatrix() {
        const allRows = getMatrixRowsWithLine();
        const lines = getMatrixLines(allRows);
        renderMatrixLineNav(lines);

        if (!lines.length) {
            matrixBody.innerHTML = '<tr class="empty-state-row"><td colspan="4">Nenhuma matriz cadastrada ainda.</td></tr>';
            return;
        }

        const activeLine = state.activeMatrixLine || lines[0];
        const rows = allRows.filter((row) => row.line === activeLine);

        matrixBody.innerHTML = rows.map((row, index) => `
            <tr data-matrix-row="1" data-line="${row.line}">
                <td><select data-matrix-index="${index}" data-field="from">${productOptions(row.from)}</select></td>
                <td><select data-matrix-index="${index}" data-field="to">${productOptions(row.to)}</select></td>
                <td><input type="text" data-matrix-index="${index}" data-field="duration" value="${row.duration}"></td>
                <td class="actions-cell"><button type="button" class="row-delete" data-remove-matrix="${index}">Remover</button></td>
            </tr>`
        ).join('');

        const readCurrentRows = () => {
            const preservedRows = allRows.filter((row) => row.line !== activeLine);
            const currentLineRows = [...matrixBody.querySelectorAll('tr[data-matrix-row="1"]')].map((row) => ({
                line: row.dataset.line || activeLine || 'SEM LINHA',
                from: row.querySelector('[data-field="from"]').value,
                to: row.querySelector('[data-field="to"]').value,
                duration: row.querySelector('[data-field="duration"]').value,
            }));

            return [...preservedRows, ...currentLineRows];
        };

        matrixBody.querySelectorAll('select, input').forEach((field) => {
            field.addEventListener('change', () => {
                syncMatrixState(readCurrentRows());
                saveState();
            });
        });

        matrixBody.querySelectorAll('[data-remove-matrix]').forEach((button) => {
            button.addEventListener('click', () => {
                const rowsNow = readCurrentRows();
                rowsNow.splice(Number(button.dataset.removeMatrix), 1);
                syncMatrixState(rowsNow);
                renderMatrix();
                saveState();
                showToast('Registro removido.');
            });
        });
    }

    function renderProgram() {
        programBody.innerHTML = '';
        const items = state.form.items.length ? state.form.items : [{}];

        items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="number" class="mini-input" name="sequence" value="${item.sequence || index + 1}" min="1" required></td>
                <td><select name="sku" required>${productOptions(item.sku || '')}</select></td>
                <td><input type="number" name="quantity" min="1" step="1" value="${item.quantity || ''}" required></td>
                <td><input type="datetime-local" name="planned_start" value="${item.planned_start || ''}"></td>
                <td class="actions-cell"><button type="button" class="row-delete">Remover</button></td>
            `;
            programBody.appendChild(row);

            const plannedStart = row.querySelector('[name="planned_start"]');
            plannedStart.disabled = index !== 0;
            if (index !== 0) {
                plannedStart.value = '';
            }

            row.querySelector('.row-delete').addEventListener('click', () => {
                state.form.items.splice(index, 1);
                if (!state.form.items.length) {
                    state.form.items = [{}];
                }
                renderProgram();
                saveState();
            });

            row.querySelectorAll('input, select').forEach((field) => {
                field.addEventListener('change', syncProgramState);
                field.addEventListener('input', syncProgramState);
            });
        });
    }

    function syncProgramState() {
        state.form.items = readProgramRows();
        saveState();
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
        toggleResultPanel(true);

        if (persist) {
            saveState();
        }
    }

    function resetResultArea(persist = true) {
        state.result = null;
        resultSummary.innerHTML = '';
        resultBody.innerHTML = '<tr class="empty-state-row"><td colspan="10">Nenhuma simulacao calculada ainda.</td></tr>';
        setStatus('Aguardando calculo', 'idle');
        toggleResultPanel(false);

        if (persist) {
            saveState();
        }
    }

    function renderAllDatasetTables() {
        renderCalendar();
        renderProducts();
        renderMatrix();
    }

    addIntervalButton.addEventListener('click', () => {
        state.datasets.calendar.intervals.push(normalizeInterval({}));
        renderCalendar();
        saveState();
        showToast('Registro salvo.');
    });

    importProductsButton.addEventListener('click', () => {
        productsImportFile?.click();
    });

    productsImportFile.addEventListener('change', async () => {
        const [file] = productsImportFile.files || [];
        if (!file) {
            return;
        }

        if (!file.name.toLowerCase().endsWith('.xlsx')) {
            showToast('Use um arquivo Excel no formato .xlsx.', 'danger');
            productsImportFile.value = '';
            return;
        }

        try {
            if (!window.PCPXlsxImport?.parseProducts) {
                throw new Error('Importacao de Excel indisponivel neste navegador.');
            }

            const imported = await window.PCPXlsxImport.parseProducts(file, state.datasets.calendar.line || 'L2');
            state.datasets.products = imported.products || {};
            pruneCatalogReferences(Object.keys(state.datasets.products));
            renderAllDatasetTables();
            renderProgram();
            saveState();
            showToast(Number(imported.count || 0) + ' produtos importados.');
        } catch (error) {
            showToast(error.message || 'Falha ao importar o arquivo.', 'danger');
        } finally {
            productsImportFile.value = '';
        }
    });

    clearProductsButton.addEventListener('click', () => {
        if (!window.confirm('Deseja realmente limpar a base de produtos?')) {
            return;
        }

        state.datasets.products = {};
        pruneCatalogReferences([]);
        renderAllDatasetTables();
        renderProgram();
        saveState();
        showToast('Base de produtos limpa.');
    });

    addProductButton.addEventListener('click', () => {
        const nextSku = 'NOVO SKU ' + (Object.keys(state.datasets.products).length + 1);
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
        showToast('Registro salvo.');
    });

    importMatrixButton.addEventListener('click', () => {
        matrixImportFile?.click();
    });

    matrixImportFile.addEventListener('change', async () => {
        const [file] = matrixImportFile.files || [];
        if (!file) {
            return;
        }

        if (!file.name.toLowerCase().endsWith('.xlsx')) {
            showToast('Use um arquivo Excel no formato .xlsx.', 'danger');
            matrixImportFile.value = '';
            return;
        }

        try {
            if (!window.PCPXlsxImport?.parseMatrix) {
                throw new Error('Importacao de matriz indisponivel neste navegador.');
            }

            const imported = await window.PCPXlsxImport.parseMatrix(file);
            syncMatrixState(imported.rows || []);
            state.activeMatrixLine = getMatrixLines(imported.rows || [])[0] || '';
            renderMatrix();
            saveState();
            showToast(Number(imported.count || 0) + ' setups importados.');
        } catch (error) {
            showToast(error.message || 'Falha ao importar a matriz.', 'danger');
        } finally {
            matrixImportFile.value = '';
        }
    });

    clearMatrixButton.addEventListener('click', () => {
        if (!window.confirm('Deseja realmente limpar a base de matrizes?')) {
            return;
        }

        state.datasets.setup_matrix = {};
        state.datasets.setup_matrix_sections = [];
        state.activeMatrixLine = '';
        renderMatrix();
        saveState();
        showToast('Base de matrizes limpa.');
    });
    addMatrixRowButton.addEventListener('click', () => {
        const firstSku = Object.keys(state.datasets.products)[0] || '';
        const rows = getMatrixRowsWithLine();
        const targetLine = state.activeMatrixLine || defaultMatrixLineLabel();
        rows.push({ line: targetLine, from: firstSku, to: firstSku, duration: '00:20' });
        state.activeMatrixLine = targetLine;
        syncMatrixState(rows);
        renderMatrix();
        saveState();
        showToast('Registro salvo.');
    });

    addHolidayButton.addEventListener('click', () => {
        const holidayDate = holidayDateInput.value;
        const holidayName = holidayNameInput.value.trim();

        if (!holidayDate) {
            holidayDateInput.focus();
            return;
        }

        if (!holidayName) {
            holidayNameInput.focus();
            return;
        }

        syncHolidayState([...(state.datasets.calendar.holidays || []), { date: holidayDate, name: holidayName }]);
        holidayDateInput.value = '';
        holidayNameInput.value = '';
        showToast('Registro salvo.');
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
        renderProgram();
        resetResultArea(false);
        saveState();
    });

    navLinks.forEach((button) => {
        button.addEventListener('click', () => {
            activateSection(button.dataset.target);
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        state.form.items = readProgramRows();
        state.form.base_start = baseStartInput.value;
        state.form.query_datetime = queryDateTimeInput.value;
        toggleResultPanel(true);
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
            setStatus('Erro no calculo', 'danger');
            toggleResultPanel(true);
            state.result = null;
            saveState();
        }
    });

    loadState();
    renderAllDatasetTables();
    renderProgram();
    baseStartInput.value = state.form.base_start || baseStartInput.value;
    queryDateTimeInput.value = state.form.query_datetime || queryDateTimeInput.value;
    activateSection(DEFAULT_SECTION);
    resetResultArea(false);

    window.addEventListener('beforeunload', saveState);
})();
















