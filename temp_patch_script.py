from pathlib import Path
path = Path('assets/js/app.js')
text = path.read_text()
old_read = """
    function readProgramRows() {
        return [...programBody.querySelectorAll('tr')].map((row, index) => ({
            sequence: Number(row.querySelector('[name=\"sequence\"]').value) || index + 1,
            sku: row.querySelector('[name=\"sku\"]').value,
            quantity: Number(row.querySelector('[name=\"quantity\"]').value) || 0,
            planned_start: index === 0 ? row.querySelector('[name=\"planned_start\"]').value : '',
        }));
    }
"""
new_read = """
    function readProgramRows() {
        return [...programBody.querySelectorAll('tr')].map((row, index) => ({
            sequence: Number(row.querySelector('[name=\"sequence\"]').value) || index + 1,
            op: row.querySelector('[name=\"op\"]').value || '',
            sku: row.querySelector('[name=\"sku\"]').value,
            quantity: Number(row.querySelector('[name=\"quantity\"]').value) || 0,
            planned_start: index === 0 ? row.querySelector('[name=\"planned_start\"]').value : '',
        }));
    }
"""
if old_read not in text:
    raise SystemExit('readProgramRows block not found')
text = text.replace(old_read, new_read, 1)
old_apply = """
        const parsedRows = rows.map((row) => {
            const rowScheduled = row.scheduledDate ? String(row.scheduledDate) : '';
            const plannedStart = rowScheduled
                ? (rowScheduled.includes('T') ? rowScheduled : rowScheduled + 'T00:00')
                : '';
            return {
                sequence: Number(row.sequence) || 0,
                sku: String(row.sku || '').trim(),
                quantity: Number(row.quantity) || 0,
                planned_start: plannedStart,
            };
        }).filter((item) => item.sku);
"""
new_apply = """
        const parsedRows = rows.map((row) => {
            const rowOp = String(row.op ?? row.OP ?? row.Op ?? '').trim();
            const rowScheduled = row.scheduledDate ? String(row.scheduledDate) : '';
            const plannedStart = rowScheduled
                ? (rowScheduled.includes('T') ? rowScheduled : rowScheduled + 'T00:00')
                : '';
            return {
                sequence: Number(row.sequence) || 0,
                op: rowOp,
                sku: String(row.sku || '').trim(),
                quantity: Number(row.quantity) || 0,
                planned_start: plannedStart,
            };
        }).filter((item) => item.sku);
"""
if old_apply not in text:
    raise SystemExit('applyProgramacaoSheet block not found')
text = text.replace(old_apply, new_apply, 1)
old_submit = """
        try {
            const response = await apiFetch('/controlepcp/api/calculate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    base_start: state.form.base_start,
                    query_datetime: state.form.query_datetime,
                    numero_op: form.querySelector('[name="numero_op"]')?.value || null,
                    production_efficiency: state.form.production_efficiency,
                    items: state.form.items.filter((item) => item.sku),
                    datasets: state.datasets,
                }),
            });
"""
new_submit = """
        try {
            const programItems = state.form.items.filter((item) => item.sku);
            const opPayload = programItems.find((item) => item.op)?.op || form.querySelector('[name="numero_op"]')?.value || null;
            const response = await apiFetch('/controlepcp/api/calculate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    base_start: state.form.base_start,
                    query_datetime: state.form.query_datetime,
                    numero_op: opPayload,
                    production_efficiency: state.form.production_efficiency,
                    items: programItems,
                    datasets: state.datasets,
                }),
            });
"""
if old_submit not in text:
    raise SystemExit('submit block not found')
text = text.replace(old_submit, new_submit, 1)
path.write_text(text)
