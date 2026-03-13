# ControlePCP

Aplicacao web em `PHP + JavaScript` para simulacao e futura gestao de programacao de producao (PCP), com foco em calculo sequencial de ordens, setup entre SKU e respeito ao calendario produtivo.

## Objetivo do projeto

Este projeto esta sendo construido para evoluir de um prototipo funcional para um sistema interno de PCP com prioridade em:

- funcionalidade
- usabilidade
- clareza visual
- facilidade de operacao para usuarios com baixo nivel de familiaridade com TI
- codigo limpo, enxuto e de facil manutencao

Neste momento, o sistema ainda opera sem banco de dados. Os dados usados no MVP estao mockados apenas para permitir testes da logica e da interface.

## Diretriz importante

O `README` deve documentar a estrutura, a arquitetura e o funcionamento tecnico do projeto.

Ele nao deve servir como cadastro operacional permanente de:

- horarios de trabalho
- produtos
- matriz de setup
- dados de programacao

Esses dados sao temporarios nesta fase e, futuramente, virao do banco de dados.

## Estrutura atual da interface

A interface atual foi reorganizada para funcionar como base do sistema e nao apenas como simulador isolado.

Componentes principais:

- topo com logo reduzida e identidade visual da empresa
- menu Cadastros com acesso a Horarios de Trabalho, SKU (Produtos), Matrizes e Programacao de PCP
- secoes editaveis no proprio navegador, sem banco de dados nesta etapa
- tela de programacao separada da manutencao dos cadastros
- resultado operacional mais limpo, mantendo campos tecnicos apenas em segundo plano
## Stack atual

- `PHP` para backend e renderizacao inicial
- `JavaScript` para interacao da interface
- `HTML/CSS` para a camada visual
- `XAMPP` como ambiente local atual

## Estrutura de pastas

```text
controlepcp/
â”œâ”€â”€ api/
â”‚   â””â”€â”€ calculate.php
â”œâ”€â”€ assets/
â”‚   â”œâ”€â”€ css/
â”‚   â”‚   â””â”€â”€ app.css
â”‚   â”‚   â””â”€â”€ theme.css
â”‚   â””â”€â”€ js/
â”‚       â””â”€â”€ app.js
â”œâ”€â”€ src/
â”‚   â”œâ”€â”€ Data/
â”‚   â”‚   â””â”€â”€ MockData.php
â”‚   â”œâ”€â”€ Services/
â”‚   â”‚   â”œâ”€â”€ Scheduler.php
â”‚   â”‚   â””â”€â”€ WorkCalendar.php
â”‚   â”œâ”€â”€ Support/
â”‚   â”‚   â””â”€â”€ DateTimeHelper.php
â”‚   â””â”€â”€ bootstrap.php
â”œâ”€â”€ .gitignore
â”œâ”€â”€ index.php
â””â”€â”€ README.md
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

#### [src/Data/MockData.php](C:\xampp\htdocs\controlepcp\src\Data\MockData.php)

Armazena os dados mockados usados no MVP.

Importante:

- este arquivo e provisorio
- ele existe apenas para viabilizar testes sem banco
- o calendario produtivo atual do prototipo tambem esta definido neste arquivo
- o calendario do MVP esta preparado para segunda a sexta, com lista de feriados ainda vazia
- no futuro, devera ser substituido por acesso a banco de dados ou camada de repositorio
- atualmente tambem serve como carga inicial para os cadastros locais no frontend

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

### 1. Dados mockados

O arquivo [src/Data/MockData.php](C:\xampp\htdocs\controlepcp\src\Data\MockData.php) deve ser entendido como temporario.

Quando houver integracao com banco, o ideal e:

- criar uma camada de acesso a dados
- evitar que regras de negocio dependam de arrays fixos
- manter `Scheduler` desacoplado da origem dos dados

### 2. Persistencia local no navegador

Atualmente, o frontend salva localmente no navegador:

- data/hora base
- data/hora de consulta
- itens lancados
- ultimo resultado calculado

Essa persistencia existe apenas para melhorar a experiencia de uso durante o lancamento manual e nao substitui banco de dados.

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


