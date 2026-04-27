# Python Gantt Paralelo

Base inicial da nova solução paralela do gantt, separada do legado PHP.

## Estrutura

- `app/main.py`: app FastAPI e ponto de entrada
- `app/config.py`: configuração do ambiente
- `app/db.py`: base de conexão com o banco
- `app/api/`: rotas HTTP
- `app/repositories/`: acesso a dados
- `app/services/`: regras de domínio
- `app/templates/` e `app/static/`: suporte para interface futura

## Como executar

1. Criar e ativar um ambiente virtual Python
2. Instalar dependências:

```bash
pip install -r python_gantt/requirements.txt
```

3. Subir a aplicação:

```bash
uvicorn python_gantt.app.main:app --reload
```

4. Abrir:

- `http://127.0.0.1:8000/`
- `http://127.0.0.1:8000/api/v1/health`
- `http://127.0.0.1:8000/api/v1/gantt/view`
- `http://127.0.0.1:8000/api/v1/gantt/programacoes`
- `http://127.0.0.1:8000/api/v1/gantt/programacoes/1`

## Visual paralela

- A interface nova fica em `GET /api/v1/gantt/view`
- Para abrir uma programação específica: `GET /api/v1/gantt/view?programacao_id=1`
- A tela consome o JSON real do módulo Python e não depende de `gantt.php` nem `gantt2.php`

## Atalhos Windows

- `python_gantt_setup.cmd`: cria a `.venv` e instala as dependências
- `python_gantt_run.cmd`: sobe somente o backend Python em `0.0.0.0:8000`
- `open_gantt_senior.cmd`: faz o bootstrap, sobe o backend e abre `gantt_senior.php`

Uso recomendado:

1. Execute `open_gantt_senior.cmd`
2. Aguarde o backend responder
3. Abra ou recarregue `http://192.168.8.123:8081/gantt_senior.php` se necessário
