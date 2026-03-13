# ControlePCP

Aplicação web em `PHP + JavaScript` para simulação e futura gestão de programação de produção (PCP), com foco em cálculo sequencial de ordens, setup entre SKU e respeito ao calendário produtivo.

## Objetivo do projeto

Este projeto está sendo construído para evoluir de um protótipo funcional para um sistema interno de PCP com prioridade em:

- funcionalidade
- usabilidade
- clareza visual
- facilidade de operação para usuários com baixo nível de familiaridade com TI
- código limpo, enxuto e de fácil manutenção

Neste momento, o sistema ainda opera sem banco de dados. Os dados usados no MVP estão mockados apenas para permitir testes da lógica e da interface.

## Diretriz importante

O `README` deve documentar a estrutura, a arquitetura e o funcionamento técnico do projeto.

Ele não deve servir como cadastro operacional permanente de:

- horários de trabalho
- produtos
- matriz de setup
- dados de programação

Esses dados são temporários nesta fase e, futuramente, virão do banco de dados.

## Stack atual

- `PHP` para backend e renderização inicial
- `JavaScript` para interação da interface
- `HTML/CSS` para a camada visual
- `XAMPP` como ambiente local atual

## Estrutura de pastas

```text
controlepcp/
├── api/
│   └── calculate.php
├── assets/
│   ├── css/
│   │   └── app.css
│   └── js/
│       └── app.js
├── src/
│   ├── Data/
│   │   └── MockData.php
│   ├── Services/
│   │   ├── Scheduler.php
│   │   └── WorkCalendar.php
│   ├── Support/
│   │   └── DateTimeHelper.php
│   └── bootstrap.php
├── .gitignore
├── index.php
└── README.md
```

## Papel de cada pasta e arquivo

### Raiz do projeto

#### [index.php](C:\xampp\htdocs\controlepcp\index.php)

Ponto de entrada da aplicação web.

Responsabilidades:

- carregar o bootstrap
- obter os dados iniciais do MVP
- montar a tela principal
- injetar dados iniciais para o frontend

#### [README.md](C:\xampp\htdocs\controlepcp\README.md)

Documentação principal do projeto.

Responsabilidades:

- explicar a arquitetura
- registrar a organização da aplicação
- orientar futuras manutenções

#### [.gitignore](C:\xampp\htdocs\controlepcp\.gitignore)

Define arquivos e pastas locais que não devem ser versionados.

### Pasta `api/`

#### [api/calculate.php](C:\xampp\htdocs\controlepcp\api\calculate.php)

Endpoint HTTP responsável por receber os dados da programação e devolver o resultado calculado em JSON.

Responsabilidades:

- receber o payload enviado pelo frontend
- validar os dados mínimos da requisição
- instanciar o motor de cálculo
- retornar a resposta em formato JSON

### Pasta `assets/`

Contém os arquivos estáticos da interface.

#### [assets/css/app.css](C:\xampp\htdocs\controlepcp\assets\css\app.css)

Arquivo principal de estilos da aplicação.

Responsabilidades:

- definir aparência visual
- garantir legibilidade
- manter uma interface simples e amigável
- suportar responsividade básica

#### [assets/js/app.js](C:\xampp\htdocs\controlepcp\assets\js\app.js)

Script principal do frontend.

Responsabilidades:

- montar dinamicamente as linhas da programação
- serializar o formulário
- enviar os dados para a API
- renderizar a tabela de resultado
- atualizar resumos e status da simulação

### Pasta `src/`

Contém o código PHP da aplicação.

#### [src/bootstrap.php](C:\xampp\htdocs\controlepcp\src\bootstrap.php)

Responsável pelo carregamento automático das classes do projeto.

Função:

- registrar o autoload das classes no namespace `App\`

### Pasta `src/Data/`

Agrupa fontes de dados usadas pela aplicação.

#### [src/Data/MockData.php](C:\xampp\htdocs\controlepcp\src\Data\MockData.php)

Armazena os dados mockados usados no MVP.

Importante:

- este arquivo é provisório
- ele existe apenas para viabilizar testes sem banco
- no futuro, deverá ser substituído por acesso a banco de dados ou camada de repositório

### Pasta `src/Services/`

Contém as regras de negócio principais.

#### [src/Services/Scheduler.php](C:\xampp\htdocs\controlepcp\src\Services\Scheduler.php)

Motor principal do cálculo de PCP.

Responsabilidades:

- ordenar a programação
- validar SKU e taxa produtiva
- calcular tempo de produção
- buscar setup entre itens consecutivos
- calcular início e fim das atividades
- calcular produzido estimado
- montar a saída final da simulação

#### [src/Services/WorkCalendar.php](C:\xampp\htdocs\controlepcp\src\Services\WorkCalendar.php)

Serviço de calendário produtivo.

Responsabilidades:

- encontrar o próximo horário válido
- somar minutos úteis
- calcular tempo útil transcorrido entre duas datas
- suportar intervalos que atravessam a meia-noite

### Pasta `src/Support/`

Contém utilitários e funções de apoio.

#### [src/Support/DateTimeHelper.php](C:\xampp\htdocs\controlepcp\src\Support\DateTimeHelper.php)

Funções auxiliares para manipulação de datas, horas e durações.

Responsabilidades:

- converter entradas de data/hora
- converter durações em minutos
- formatar datas e horários para saída
- somar minutos a uma data

## Fluxo atual da aplicação

1. O usuário acessa `index.php`.
2. A tela principal é montada com base nos dados disponíveis no MVP.
3. O frontend monta a sequência de produção e envia os dados para `api/calculate.php`.
4. O endpoint chama o serviço [Scheduler.php](C:\xampp\htdocs\controlepcp\src\Services\Scheduler.php).
5. O `Scheduler` usa [WorkCalendar.php](C:\xampp\htdocs\controlepcp\src\Services\WorkCalendar.php) para respeitar o calendário útil.
6. O resultado volta em JSON.
7. O frontend renderiza a tabela com produção e setup.

## Regra funcional atual do motor

O motor foi estruturado para seguir esta linha:

1. A programação é processada por sequência.
2. O primeiro item usa a data/hora base informada, ajustada para um horário válido.
3. Cada item usa sua taxa de produção para calcular a duração.
4. Itens seguintes dependem do fim da produção anterior.
5. O setup entre SKU anterior e SKU atual é aplicado antes da próxima produção.
6. Setup e produção consomem apenas tempo útil do calendário.
7. Ao fim do setup, o sistema recalcula o próximo horário válido para iniciar a produção seguinte.
8. O sistema calcula também o produzido estimado para uma data/hora de consulta.

## Observações para manutenção futura

### 1. Dados mockados

O arquivo [src/Data/MockData.php](C:\xampp\htdocs\controlepcp\src\Data\MockData.php) deve ser entendido como temporário.

Quando houver integração com banco, o ideal é:

- criar uma camada de acesso a dados
- evitar que regras de negócio dependam de arrays fixos
- manter `Scheduler` desacoplado da origem dos dados

### 2. Separação de responsabilidades

Para manter o projeto organizado:

- regras de negócio ficam em `src/Services`
- dados e fontes temporárias ficam em `src/Data`
- helpers genéricos ficam em `src/Support`
- interface fica em `assets`
- endpoints ficam em `api`

### 3. Evolução esperada

A tendência natural do projeto é evoluir para:

- cadastros reais em banco
- telas de cadastro
- autenticação e perfis, se necessário
- separação maior entre backend web e regras de domínio
- documentação complementar em `docs/`

## Como executar localmente

1. Colocar o projeto em `C:\xampp\htdocs\controlepcp`.
2. Iniciar o Apache no XAMPP.
3. Acessar:

```text
http://localhost/controlepcp/
```

## Regra de atualização da documentação

Este arquivo deve ser revisado sempre que houver mudança relevante em:

- estrutura de pastas
- responsabilidades de arquivos
- arquitetura da aplicação
- regra do motor de cálculo
- integração com banco
- estratégia de deploy

Se a documentação ficar diferente do sistema real, ela perde valor para manutenção. Por isso, manter este arquivo atualizado faz parte do desenvolvimento do projeto.
