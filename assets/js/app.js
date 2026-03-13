(function () {
    const bootstrap = window.PCP_BOOTSTRAP || { products: [], sampleProgram: [] };
    const productOptions = bootstrap.products || [];
    const sampleProgram = bootstrap.sampleProgram || [];
    const STORAGE_KEY = 'controlepcp.simulation.v2';
    const LEGACY_STORAGE_KEY = 'controlepcp.simulation.v1';
    const STATE_VERSION = 2;

    const form = document.getElementById('simulation-form');
    const addRowButton = document.getElementById('add-row');
    const clearButton = document.getElementById('clear-simulation');
    const programBody = document.getElementById('program-body');
    const resultBody = document.getElementById('result-body');
    const resultStatus = document.getElementById('result-status');
    const resultSummary = document.getElementById('result-summary');
    const baseStartInput = form.querySelector('[name="base_start"]');
    const queryDateTimeInput = form.querySelector('[name="query_datetime"]');
    const entryTableWrap = document.querySelector('.entry-table-wrap');

    let currentResult = null;

    const buildOptions = (selectedValue) => {
        const placeholder = '<option value="">Selecione</option>';
        const options = productOptions
            .map((product) => {
                const selected = product.sku === selectedValue ? 'selected' : '';
                return `<option value="${product.sku}" ${selected}>${product.sku}</option>`;
            })
            .join('');

        return placeholder + options;
    };

    const setStatus = (label, variant) => {
        resultStatus.textContent = label;
        resultStatus.dataset.variant = variant;
    };

    const formatNumber = (value) => {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        return new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(value);
    };

    const readRows = () => {
        return [...programBody.querySelectorAll('tr')].map((row, index) => ({
            sequence: Number(row.querySelector('[name="sequence"]').value),
            sku: row.querySelector('[name="sku"]').value,
            quantity: Number(row.querySelector('[name="quantity"]').value),
            planned_start: index === 0 ? row.querySelector('[name="planned_start"]').value : '',
        }));
    };

    const saveState = () => {
        const payload = {
            version: STATE_VERSION,
            form: {
                base_start: baseStartInput.value,
                query_datetime: queryDateTimeInput.value,
                items: readRows(),
            },
            result: currentResult,
        };

        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    };

    const parseStoredValue = (raw) => {
        try {
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    };

    const loadState = () => {
        const currentState = parseStoredValue(window.localStorage.getItem(STORAGE_KEY));

        if (currentState) {
            return currentState;
        }

        const legacyState = parseStoredValue(window.localStorage.getItem(LEGACY_STORAGE_KEY));

        if (!legacyState) {
            return null;
        }

        return {
            version: 1,
            form: legacyState.form ?? null,
            result: null,
        };
    };

    const clearState = () => {
        window.localStorage.removeItem(STORAGE_KEY);
        window.localStorage.removeItem(LEGACY_STORAGE_KEY);
    };

    const hasCurrentMemoryFormat = (result) => {
        if (!result || !Array.isArray(result.rows)) {
            return false;
        }

        return result.rows.every((row) => {
            if (!row || !row.calculation_memory) {
                return true;
            }

            const hasLegacySegmentFormat = /usado\s+\d{2}:\d{2}-\d{2}:\d{2}\s+=\s+\d{2}:\d{2}\s+de\s+\d{2}:\d{2}/.test(row.calculation_memory);
            const hasCurrentTotal = row.calculation_memory.includes('total usado =');

            return !hasLegacySegmentFormat && hasCurrentTotal;
        });
    };

    const scrollToRow = (row) => {
        if (!row) {
            return;
        }

        row.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
        });

        const quantityInput = row.querySelector('input[name="quantity"]');
        quantityInput?.focus();
    };

    const scrollToBottomOfEntries = () => {
        if (!entryTableWrap) {
            return;
        }

        entryTableWrap.scrollTo({
            top: entryTableWrap.scrollHeight,
            behavior: 'smooth',
        });
    };

    const updatePlannedStartState = () => {
        [...programBody.querySelectorAll('tr')].forEach((row, index) => {
            const plannedStartInput = row.querySelector('input[name="planned_start"]');

            if (!plannedStartInput) {
                return;
            }

            const isFirstRow = index === 0;
            plannedStartInput.disabled = !isFirstRow;

            if (isFirstRow) {
                plannedStartInput.title = 'Somente o primeiro item usa início informado.';
            } else {
                plannedStartInput.value = '';
                plannedStartInput.title = 'As próximas linhas são calculadas automaticamente.';
            }
        });
    };

    const attachRowListeners = (tr) => {
        tr.querySelector('.row-delete').addEventListener('click', () => {
            tr.remove();
            if (!programBody.children.length) {
                createRow();
            }
            refreshSequences();
            saveState();
        });

        tr.querySelectorAll('input, select').forEach((field) => {
            field.addEventListener('change', saveState);
            field.addEventListener('input', saveState);
        });
    };

    const createRow = (item = {}, options = {}) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="number" class="mini-input" name="sequence" min="1" value="${item.sequence || programBody.children.length + 1}" required></td>
            <td><select name="sku" required>${buildOptions(item.sku || '')}</select></td>
            <td><input type="number" name="quantity" min="1" step="1" value="${item.quantity || ''}" required></td>
            <td><input type="datetime-local" name="planned_start" value="${item.planned_start || ''}"></td>
            <td class="actions-cell"><button type="button" class="row-delete" aria-label="Remover item">Remover</button></td>
        `;

        programBody.appendChild(tr);
        attachRowListeners(tr);
        updatePlannedStartState();

        if (options.scrollToNewRow) {
            window.requestAnimationFrame(() => {
                scrollToBottomOfEntries();
                scrollToRow(tr);
            });
        }

        return tr;
    };

    const refreshSequences = () => {
        [...programBody.querySelectorAll('tr')].forEach((row, index) => {
            const input = row.querySelector('input[name="sequence"]');
            if (input) {
                input.value = index + 1;
            }
        });

        updatePlannedStartState();
    };

    const serializeForm = () => {
        const formData = new FormData(form);

        return {
            base_start: formData.get('base_start'),
            query_datetime: formData.get('query_datetime'),
            items: readRows().filter((item) => item.sku),
        };
    };

    const renderSummary = (result) => {
        const productionRows = result.rows.filter((row) => row.type === 'production');
        const totalQty = productionRows.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0);
        const calculatedRows = productionRows.filter((row) => row.status === 'Calculado').length;

        resultSummary.innerHTML = `
            <div class="summary-card"><span>Total de ordens</span><strong>${productionRows.length}</strong></div>
            <div class="summary-card"><span>Ordens calculadas</span><strong>${calculatedRows}</strong></div>
            <div class="summary-card"><span>Caixas programadas</span><strong>${formatNumber(totalQty)}</strong></div>
        `;
    };

    const renderRows = (rows) => {
        if (!rows.length) {
            resetResultArea(false);
            return;
        }

        resultBody.innerHTML = rows
            .map((row) => `
                <tr class="${row.type === 'setup' ? 'setup-row' : ''}">
                    <td>${row.type === 'setup' ? 'Setup' : ''}</td>
                    <td>${row.sequence ?? ''}</td>
                    <td>${row.description || row.sku}</td>
                    <td>${row.rate_per_hour ?? ''}</td>
                    <td>${formatNumber(row.quantity)}</td>
                    <td>${row.duration_label || ''}</td>
                    <td>${row.date_start || ''}</td>
                    <td>${row.time_start || ''}</td>
                    <td class="memory-cell">${row.calculation_memory || ''}</td>
                    <td>${row.time_end || ''}</td>
                </tr>
            `)
            .join('');
    };

    const renderResult = (result, persist = true) => {
        currentResult = result;
        renderSummary(result);
        renderRows(result.rows || []);
        setStatus(result.meta.errors.length ? 'Calculado com alertas' : 'Calculado', result.meta.errors.length ? 'warning' : 'success');

        if (persist) {
            saveState();
        }
    };

    const resetResultArea = (persist = true) => {
        currentResult = null;
        resultSummary.innerHTML = '';
        resultBody.innerHTML = '<tr class="empty-state-row"><td colspan="10">Nenhuma simulação calculada ainda.</td></tr>';
        setStatus('Aguardando cálculo', 'idle');

        if (persist) {
            saveState();
        }
    };

    const clearSimulation = () => {
        programBody.innerHTML = '';
        createRow({}, { scrollToNewRow: false });
        resetResultArea(false);
        clearState();
    };

    const restoreForm = (savedForm) => {
        if (!savedForm) {
            return false;
        }

        baseStartInput.value = savedForm.base_start || baseStartInput.value;
        queryDateTimeInput.value = savedForm.query_datetime || queryDateTimeInput.value;

        programBody.innerHTML = '';

        const items = Array.isArray(savedForm.items) && savedForm.items.length
            ? savedForm.items
            : [{}];

        items.forEach((item) => createRow(item, { scrollToNewRow: false }));
        refreshSequences();

        return true;
    };

    addRowButton.addEventListener('click', () => {
        createRow({}, { scrollToNewRow: true });
        refreshSequences();
        saveState();
    });

    clearButton.addEventListener('click', clearSimulation);

    baseStartInput.addEventListener('change', saveState);
    baseStartInput.addEventListener('input', saveState);
    queryDateTimeInput.addEventListener('change', saveState);
    queryDateTimeInput.addEventListener('input', saveState);
    window.addEventListener('beforeunload', saveState);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        setStatus('Calculando...', 'loading');
        saveState();

        try {
            const response = await fetch('/controlepcp/api/calculate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(serializeForm()),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Falha ao calcular.');
            }

            renderResult(result);
        } catch (error) {
            currentResult = null;
            resultSummary.innerHTML = '';
            resultBody.innerHTML = `<tr class="empty-state-row"><td colspan="10">${error.message}</td></tr>`;
            setStatus('Erro no cálculo', 'danger');
            saveState();
        }
    });

    const restoredState = loadState();

    if (!restoreForm(restoredState?.form)) {
        if (sampleProgram.length) {
            sampleProgram.forEach((item) => createRow(item, { scrollToNewRow: false }));
        } else {
            createRow({}, { scrollToNewRow: false });
        }
    }

    refreshSequences();

    if (restoredState?.version === STATE_VERSION && hasCurrentMemoryFormat(restoredState?.result)) {
        renderResult(restoredState.result, false);
    } else {
        resetResultArea(false);
        saveState();
    }
})();
