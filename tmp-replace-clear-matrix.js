const fs=require('fs');
const path='assets/js/app.js';
let text=fs.readFileSync(path,'utf8');
const old=`
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
`;
const nw=`
    clearMatrixButton.addEventListener('click', async () => {
        if (!window.confirm('Deseja realmente limpar a base de matrizes?')) {
            return;
        }

        try {
            const response = await apiFetch('/controlepcp/api/matrices.php', { method: 'DELETE' });
            let errorBody = null;
            try {
                errorBody = await response.clone().json();
            } catch {}
            if (!response.ok) {
                throw new Error(errorBody?.message || 'Erro ao limpar matrizes.');
            }

            state.datasets.setup_matrix = {};
            state.datasets.setup_matrix_sections = [];
            state.activeMatrixLine = '';
            renderMatrix();
            showToast('Base de matrizes limpa.', 'success');
            await fetchDatasets();
        } catch (error) {
            showToast(error.message || 'Erro ao limpar matrizes.', 'danger');
        }
    });
`;
if(!text.includes(old)){
    throw new Error('old block missing');
}
text=text.replace(old,nw);
fs.writeFileSync(path,text,'utf8');
