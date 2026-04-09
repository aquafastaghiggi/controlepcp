# ControlePCP - Teste 3

Aplicacao web em `PHP + JavaScript` para simulacao e futura gestao de programacao de producao (PCP), com foco em calculo sequencial de ordens, setup entre SKU e respeito ao calendario produtivo.

## Objetivo do projeto

Este projeto esta sendo construido para evoluir de um prototipo funcional para um sistema interno de PCP com prioridade em:

- funcionalidade
- usabilidade
- clareza visual
- facilidade de operacao para usuarios com baixo nivel de familiaridade com TI
- codigo limpo, enxuto e de facil manutencao

O sistema persiste todos os dados em um banco de dados MySQL. Os cadastros de horas, produtos e matriz sao carregados e salvos diretamente no banco.

## Diretriz importante

O `README` deve documentar a estrutura, a arquitetura e o funcionamento tecnico do projeto.

Ele nao deve servir como cadastro operacional permanente de:

- horarios de trabalho
- produtos
- matriz de setup
- dados de programacao

Esses dados sao temporarios nesta fase e, futuramente, virao do banco de dados.

## Estrutura atual da interface

A interface atual foi reorganizada para funcionar como base do sistema e nao apenas como simulador isolado. Ao abrir o projeto, a home mostra apenas o painel inicial e os modulos sao abertos sob demanda.

Componentes principais:

- topo com logo reduzida e atalho unico para o Painel Inicial
- painel inicial como ponto central de navegacao para Horarios de Trabalho, SKU (Produtos), Matrizes e Programacao de PCP
- secoes editaveis no proprio navegador, com dados persistidos no banco (sem localStorage como fonte prim?ria)
- tela de programacao separada da manutencao dos cadastros
- resultado operacional exibido apenas apos a acao de calcular programacao, mantendo campos tecnicos em segundo plano
## Stack atual

- `PHP` para backend e renderizacao inicial
- `JavaScript` para interacao da interface
- `HTML/CSS` para a camada visual
- `XAMPP` como ambiente local atual

## Sandbox e publicacao

Padrao de trabalho (sandbox -> aprovacao -> publicacao com backup/rollback):

- `docs/SANDBOX_E_PUBLICACAO.md`

## Estrutura de pastas

```text
controlepcp/
?"o?"??"? api/
?",   ?""?"??"? calculate.php
?"o?"??"? assets/
?",   ?"o?"??"? css/
?",   ?",   ?""?"??"? app.css
?",   ?",   ?""?"??"? theme.css
?",   ?""?"??"? js/
?",       ?""?"??"? app.js
?"o?"??"? src/
?",   ?"o?"??"? Data/
?",   ?"o?"??"? Services/
?",   ?",   ?"o?"??"? Scheduler.php
?",   ?",   ?""?"??"? WorkCalendar.php
?",   ?"o?"??"? Support/
?",   ?",   ?""?"??"? DateTimeHelper.php
?",   ?""?"??"? bootstrap.php
?"o?"??"? .gitignore
?"o?"??"? index.php
?""?"??"? README.md
```

## Papel de cada pasta e arquivo

### Raiz do projeto

#### [index.php](C:\xampp\htdocs\controlepcp\index.php)

Ponto de entrada da aplicacao web.

Responsabilidades:

- carregar o bootstrap
- obter os dados iniciais do MVP
- montar a tela principal
- injetar dados iniciais para o frontend

#### [README.md](C:\xampp\htdocs\controlepcp\README.md)

Documentacao principal do projeto.

Responsabilidades:

- explicar a arquitetura
- registrar a organizacao da aplicacao
- orientar futuras manutencoes

#### [.gitignore](C:\xampp\htdocs\controlepcp\.gitignore)

Define arquivos e pastas locais que nao devem ser versionados.

### Pasta `api/`

#### [api/calculate.php](C:\xampp\htdocs\controlepcp\api\calculate.php)

Endpoint HTTP responsavel por receber os dados da programacao e devolver o resultado calculado em JSON.

Responsabilidades:

- receber o payload enviado pelo frontend
- validar os dados minimos da requisicao
- instanciar o motor de calculo
- retornar a resposta em formato JSON

### Pasta `assets/`

Contem os arquivos estaticos da interface.

#### [assets/css/app.css](C:\xampp\htdocs\controlepcp\assets\css\app.css)

Arquivo principal de estilos da aplicacao.

Responsabilidades:

- definir aparencia visual
- garantir legibilidade
- manter uma interface simples e amigavel
- suportar responsividade basica
- manter a grade de lancamento com rolagem propria para listas maiores

#### [assets/css/theme.css](C:\xampp\htdocs\controlepcp\assets\css\theme.css)

Camada complementar de identidade visual do projeto.

Responsabilidades:

- aplicar a paleta da marca com base na logo da empresa
- ajustar o topo com a logo
- simplificar a tela removendo cards auxiliares do MVP
- refinar a responsividade da interface principal

#### [assets/js/app.js](C:\xampp\htdocs\controlepcp\assets\js\app.js)

Script principal do frontend.

Responsabilidades:

- controlar a navegacao entre as secoes de Cadastros e Programacao de PCP
- manter os cadastros de horarios, SKU e matriz em armazenamento local para testes sem banco
- montar dinamicamente as linhas da programacao
- serializar o formulario
- enviar os dados para a API
- renderizar a tabela de resultado em formato simplificado para operacao
- manter a memoria textual do calculo disponivel no payload, ainda que oculta na tela operacional
- persistir localmente os dados digitados e o ultimo resultado para sobreviver a atualizacao da pagina
- rolar automaticamente para o ultimo item criado na grade de lancamento
- atualizar resumos e status da simulacao
- limpar os itens lancados e o resultado quando o usuario usar a acao de limpar programacao
- permitir inicio informado apenas no primeiro item da sequencia

### Pasta `src/`

Contem o codigo PHP da aplicacao.

#### [src/bootstrap.php](C:\xampp\htdocs\controlepcp\src\bootstrap.php)

Responsavel pelo carregamento automatico das classes do projeto.

Funcao:

- registrar o autoload das classes no namespace `App\`

### Pasta `src/Data/`

Agrupa fontes de dados usadas pela aplicacao.

### Pasta `src/Services/`

Contem as regras de negocio principais.

#### [src/Services/Scheduler.php](C:\xampp\htdocs\controlepcp\src\Services\Scheduler.php)

Motor principal do calculo de PCP.

Responsabilidades:

- ordenar a programacao
- validar SKU e taxa produtiva
- calcular tempo de producao
- buscar setup entre itens consecutivos
- calcular inicio e fim das atividades
- gerar a memoria textual dos blocos consumidos em cada atividade
- calcular produzido estimado
- montar a saida final da simulacao

#### [src/Services/WorkCalendar.php](C:\xampp\htdocs\controlepcp\src\Services\WorkCalendar.php)

Servico de calendario produtivo.

Responsabilidades:

- encontrar o proximo horario valido
- somar minutos uteis
- montar o plano detalhado dos blocos de tempo consumidos
- calcular tempo util transcorrido entre duas datas
- suportar intervalos que atravessam a meia-noite
- ignorar sabados, domingos e feriados configurados

### Pasta `src/Support/`

Contem utilitarios e funcoes de apoio.

#### [src/Support/DateTimeHelper.php](C:\xampp\htdocs\controlepcp\src\Support\DateTimeHelper.php)

Funcoes auxiliares para manipulacao de datas, horas e duracoes.

Responsabilidades:

- converter entradas de data/hora
- converter duracoes em minutos
- formatar datas e horarios para saida
- somar minutos a uma data

## Fluxo atual da aplicacao

### Reset de dados

A interface possui um bot?o?o "Resetar dados" que restaura o banco para o estado inicial (seed do schema), apagando as altera??es em produtos, calend??rio e matriz.


1. O usuario acessa `index.php`.
2. A tela principal e montada com logo reduzida, menu superior e secoes de cadastro operando localmente.
3. O frontend restaura automaticamente o ultimo lancamento salvo no navegador, quando existir.
4. O frontend permite editar Horarios de Trabalho, SKU e Matrizes em memoria local, monta a sequencia de producao usando inicio informado apenas no primeiro item e envia tudo para `api/calculate.php`.
5. O endpoint chama o servico [Scheduler.php](C:\xampp\htdocs\controlepcp\src\Services\Scheduler.php).
6. O `Scheduler` usa [WorkCalendar.php](C:\xampp\htdocs\controlepcp\src\Services\WorkCalendar.php) para respeitar o calendario util e gerar a memoria dos blocos consumidos.
7. O resultado volta em JSON.
8. O frontend renderiza a tabela com producao, setup e a memoria textual do calculo.
9. O estado atual permanece salvo localmente ate que o usuario limpe a programacao.

## Regra funcional atual do motor

O motor foi estruturado para seguir esta linha:

1. A programacao e processada por sequencia.
2. O primeiro item usa a data/hora base informada, ajustada para um horario valido.
3. Cada item usa sua taxa de producao para calcular a duracao.
4. Itens seguintes nao recebem data manual e dependem do fim da producao anterior.
5. O setup entre SKU anterior e SKU atual e aplicado antes da proxima producao.
6. Setup e producao consomem apenas tempo util do calendario.
7. O calendario atual do MVP considera apenas segunda a sexta, com lista de feriados preparada para uso futuro.
8. Ao fim do setup, o sistema recalcula o proximo horario valido para iniciar a producao seguinte.
9. O sistema gera uma memoria textual dos intervalos consumidos para facilitar a conferencia operacional.
10. O sistema calcula tambem o produzido estimado para uma data/hora de consulta.

## Observacoes para manutencao futura

### 1. Persist?ncia de dados em banco

O sistema persiste todos os dados em MySQL, incluindo:

- calend?rio produtivo (intervalos, dias ?teis e feriados)
- produtos (SKUs)
- matriz de setup
- execu??es de c?lculo (programas e linhas de resultado)

O frontend n?o depende de armazenamento local para a opera??o; ele carrega e grava dados via APIs REST que acessam o banco.

### 2. Persist?ncia local no navegador

N?o h? mais persist?ncia em `localStorage`. A fonte ?nica de verdade ? o banco de dados e as APIs do backend.

### 3. Separacao de responsabilidades

Para manter o projeto organizado:

- regras de negocio ficam em `src/Services`
- dados e fontes temporarias ficam em `src/Data`
- helpers genericos ficam em `src/Support`
- interface fica em `assets`
- endpoints ficam em `api`

### 4. Evolucao esperada

A tendencia natural do projeto e evoluir para:

- cadastros reais em banco
- telas de cadastro
- autenticacao e perfis, se necessario
- separacao maior entre backend web e regras de dominio
- documentacao complementar em `docs/`

## Como executar localmente

1. Colocar o projeto em `C:\xampp\htdocs\controlepcp`.
2. Iniciar o Apache no XAMPP.
3. Acessar:

```text
http://localhost/controlepcp/
```

## Regra de atualizacao da documentacao

Este arquivo deve ser revisado sempre que houver mudanca relevante em:

- estrutura de pastas
- responsabilidades de arquivos
- arquitetura da aplicacao
- regra do motor de calculo
- integracao com banco
- estrategia de deploy

Se a documentacao ficar diferente do sistema real, ela perde valor para manutencao. Por isso, manter este arquivo atualizado faz parte do desenvolvimento do projeto.






## Atualizacao recente

- o cadastro de produtos agora aceita importacao de base via arquivo Excel .xlsx
- a leitura do arquivo e feita no frontend pelo script assets/js/xlsx-import.js, sem dependencia de banco nesta etapa
- o frontend mantem os dados importados em armazenamento local no navegador
- na importacao de produtos, o nome e normalizado para ignorar o trecho de embalagem a partir de Cx
- a acao Limpar produtos pede confirmacao e remove tambem referencias invalidas em programacao e matrizes


- o cadastro de matrizes agora aceita importacao de planilha .xlsx no formato por blocos LINHA XX, convertendo tempos do Excel para HH:MM
- o cadastro de matrizes agora mostra um botao vermelho de inconsistencias, comparando origem e destino da matriz com a base atual de produtos
- ao clicar no alerta, o sistema lista os registros com origem e/ou destino sem produto correspondente, permitindo revisar impactos no calculo de setup
- o cadastro de matrizes tambem mostra um botao azul de registros validados, indicando quantos setups conseguiram casar corretamente com origem e destino existentes no cadastro de produtos

## Fluxo atual da aplicacao

1. O usuario acessa `index.php`.
2. A pagina carrega os dados (calendario, produtos, matriz) via `GET /api/datasets.php`.
3. O usuario edita os cadastros e o sistema persiste via `POST /api/datasets.php`.
4. O botao `Resetar dados` restaura o banco ao estado inicial (seed do schema).
5. O calculo de programacao grava historico em `prg_programas`, `prg_itens` e `sch_linhas`.
