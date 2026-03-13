# ControlePCP

MVP web para simulação de programação de produção com cálculo sequencial de setup e produção, respeitando calendário útil.

## Objetivo

Este projeto nasceu para validar, sem banco de dados neste primeiro momento, a regra central de PCP definida para a linha de produção.

O foco atual é:

- simular a sequência de produção
- calcular início e fim de cada ordem
- aplicar setup entre SKU anterior e SKU atual
- respeitar horários válidos de trabalho
- exibir uma interface simples para usuários com baixo nível de familiaridade com TI

## Estado atual

Hoje o sistema funciona como um MVP local em `PHP + JavaScript`, com dados mockados em memória.

Nesta fase:

- não há integração com MySQL
- os cadastros são fixos em arquivo PHP
- a lógica de cálculo já está separada da interface
- a aplicação já pode simular uma programação simples e retornar a tabela de resultado

## Tecnologias

- `PHP` no backend
- `JavaScript` no frontend
- `HTML/CSS` para a interface
- `XAMPP` como ambiente local esperado

## Estrutura do projeto

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

## Como o sistema funciona hoje

### 1. Entrada da simulação

Na tela principal, o usuário informa:

- `data/hora base`
- `data/hora de consulta` para produzido estimado
- sequência de itens da programação
- SKU
- quantidade programada
- início informado, quando necessário

### 2. Dados mockados

Os cadastros atuais estão em [src/Data/MockData.php](C:\xampp\htdocs\controlepcp\src\Data\MockData.php):

- horários de trabalho
- produtos da linha 2
- matriz de setup
- exemplo inicial de programação

### 3. Cálculo

O cálculo principal está em [src/Services/Scheduler.php](C:\xampp\htdocs\controlepcp\src\Services\Scheduler.php).

Ele faz:

- ordenação por sequência
- busca da taxa do SKU
- cálculo do tempo de produção
- busca do setup entre itens consecutivos
- aplicação do calendário útil
- cálculo do início e fim das atividades
- cálculo do produzido estimado

### 4. Calendário útil

As regras de calendário ficam em [src/Services/WorkCalendar.php](C:\xampp\htdocs\controlepcp\src\Services\WorkCalendar.php).

Responsabilidades:

- encontrar o próximo horário válido
- somar minutos úteis sem contar períodos bloqueados
- calcular minutos úteis transcorridos entre duas datas

## Regra funcional consolidada

O comportamento validado até aqui para o MVP é:

1. A programação é processada por sequência.
2. O primeiro item começa na data/hora base, ajustada para o próximo horário válido.
3. Cada SKU usa sua taxa de produção em caixas por hora.
4. O tempo de produção é calculado por `quantidade / taxa`.
5. Para itens após o primeiro, o sistema identifica o SKU anterior.
6. O setup é buscado na matriz `SKU anterior x SKU atual`.
7. O setup começa no fim da produção anterior.
8. O setup também respeita o calendário útil.
9. Ao terminar o setup, o sistema recalcula o próximo horário válido para o início da produção.
10. A produção do item seguinte pode começar depois do fim técnico do setup.
11. Se a atividade ultrapassar o fim do intervalo, ela pausa e continua na próxima janela útil.
12. O produzido estimado depende da data/hora de consulta.

## Cadastro atual considerado no MVP

### Horários de trabalho

- `07:05 - 11:30`
- `13:27 - 17:45`
- `17:45 - 22:00`
- `23:00 - 03:00`

No estado atual do MVP, esses horários estão sendo considerados para todos os dias.

### Produtos

Cadastros atuais da linha 2:

- `AGUA SANITARIA 5L` = `200 cx/h`
- `ALVEJANTE S/ CLORO 3L` = `180 cx/h`
- `DESINFETANTE CAMPOS LAVANDA 5L` = `200 cx/h`
- `DESINFETANTE ENERGIA 5L` = `200 cx/h`
- `DESINFETANTE FL. DE EUCALIPTO 5L` = `200 cx/h`
- `DESINFETANTE HARMONIA NATURAL 5L` = `200 cx/h`
- `DESINFETANTE JARDIM FLORIDO 5L` = `200 cx/h`
- `DESINFETANTE MARINE 5L` = `200 cx/h`
- `DESINFETANTE PAIXAO 5L` = `200 cx/h`

### Setup

Regra atual mockada:

- `00:20` para produto igual
- `00:20` para troca entre desinfetantes
- `00:30` para trocas envolvendo `AGUA SANITARIA 5L`
- `00:30` para trocas envolvendo `ALVEJANTE S/ CLORO 3L`

## Como executar localmente

1. Colocar o projeto dentro do `htdocs` do XAMPP.
2. Garantir que o Apache esteja ativo.
3. Abrir no navegador:

```text
http://localhost/controlepcp/
```

## Próximas evoluções esperadas

- transformar os cadastros mockados em cadastros editáveis na interface
- aproximar o layout do modelo real da operação
- refinar a regra de calendário conforme os testes manuais
- preparar a modelagem para MySQL
- adicionar autenticação ou perfis, se necessário
- separar melhor documentação técnica e documentação de uso

## Regra de documentação deste projeto

Este arquivo deve ser atualizado conforme a evolução do sistema.

Sempre que houver mudança relevante em:

- regra do algoritmo
- estrutura dos cadastros
- fluxo da interface
- integração com banco
- implantação no servidor

esta documentação deve ser revisada para continuar refletindo o estado real do projeto.
