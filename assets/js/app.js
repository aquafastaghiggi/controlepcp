(function () {
    const bootstrap = window.PCP_BOOTSTRAP || { products: [], sampleProgram: [] };
    const productOptions = bootstrap.products || [];
    const sampleProgram = bootstrap.sampleProgram || [];

    const form = document.getElementById('simulation-form');
    const addRowButton = document.getElementById('add-row');
    const programBody = document.getElementById('program-body');
    const resultBody = document.getElementById('result-body');
    const resultStatus = document.getElementById('result-status');
    const resultSummary = document.getElementById('result-summary');

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

    const createRow = (item = {}) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="number" class="mini-input" name="sequence" min="1" value="${item.sequence || programBody.children.length + 1}" required></td>
            <td><select name="sku" required>${buildOptions(item.sku || '')}</select></td>
            <td><input type="number" name="quantity" min="1" step="1" value="${item.quantity || ''}" required></td>
            <td><input type="datetime-local" name="planned_start" value="${item.planned_start || ''}"></td>
            <td class="actions-cell"><button type="button" class="row-delete" aria-label="Remover item">Remover</button></td>
        `;

        tr.querySelector('.row-delete').addEventListener('click', () => {
            tr.remove();
            refreshSequences();
        });

        programBody.appendChild(tr);
    };

    const refreshSequences = () => {
        [...programBody.querySelectorAll('tr')].forEach((row, index) => {
            const input = row.querySelector('input[name="sequence"]');
            if (input) {
                input.value = index + 1;
            }
        });
    };

    const serializeForm = () => {
        const formData = new FormData(form);
        const items = [...programBody.querySelectorAll('tr')].map((row) => ({
            sequence: Number(row.querySelector('[name="sequence"]').value),
            sku: row.querySelector('[name="sku"]').value,
            quantity: Number(row.querySelector('[name="quantity"]').value),
            planned_start: row.querySelector('[name="planned_start"]').value,
        }));

        return {
            base_start: formData.get('base_start'),
            query_datetime: formData.get('query_datetime'),
            items,
        };
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

    const renderSummary = (result) => {
        const productionRows = result.rows.filter((row) => row.type === 'production');
        const totalQty = productionRows.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0);
        const calculatedRows = productionRows.filter((row) => row.status === 'Calculado').length;
        const lastEnd = productionRows.length ? productionRows[productionRows.length - 1].production_end : '';

        resultSummary.innerHTML = `
            <div class="summary-card"><span>Total de ordens</span><strong>${productionRows.length}</strong></div>
            <div class="summary-card"><span>Ordens calculadas</span><strong>${calculatedRows}</strong></div>
            <div class="summary-card"><span>Caixas programadas</span><strong>${formatNumber(totalQty)}</strong></div>
            <div class="summary-card"><span>Último fim previsto</span><strong>${lastEnd || '-'}</strong></div>
        `;
    };

    const renderRows = (rows) => {
        if (!rows.length) {
            resultBody.innerHTML = '<tr class="empty-state-row"><td colspan="12">Nenhuma linha retornada.</td></tr>';
            return;
        }

        resultBody.innerHTML = rows
            .map((row) => `
                <tr class="${row.type === 'setup' ? 'setup-row' : ''}">
                    <td>${row.type === 'setup' ? 'Setup' : 'Produção'}</td>
                    <td>${row.sequence ?? ''}</td>
                    <td>${row.description || row.sku}</td>
                    <td>${row.rate_per_hour ?? ''}</td>
                    <td>${formatNumber(row.quantity)}</td>
                    <td>${row.duration_label || ''}</td>
                    <td>${row.previous_sku || ''}</td>
                    <td>${row.date_start || ''}</td>
                    <td>${row.time_start || ''}</td>
                    <td>${row.time_end || ''}</td>
                    <td>${formatNumber(row.estimated_produced)}</td>
                    <td>${row.status || ''}</td>
                </tr>
            `)
            .join('');
    };

    const setStatus = (label, variant) => {
        resultStatus.textContent = label;
        resultStatus.dataset.variant = variant;
    };

    addRowButton.addEventListener('click', () => createRow());

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        setStatus('Calculando...', 'loading');

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

            renderSummary(result);
            renderRows(result.rows || []);
            setStatus(result.meta.errors.length ? 'Calculado com alertas' : 'Calculado', result.meta.errors.length ? 'warning' : 'success');
        } catch (error) {
            resultSummary.innerHTML = '';
            resultBody.innerHTML = `<tr class="empty-state-row"><td colspan="12">${error.message}</td></tr>`;
            setStatus('Erro no cálculo', 'danger');
        }
    });

    sampleProgram.forEach((item) => createRow(item));
})();
