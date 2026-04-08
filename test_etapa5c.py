#!/usr/bin/env python3
"""
ETAPA 5C - DIAGNÓSTICO DE CLAREZA VISUAL
Comparar PDF (verdade) com gráfico (realidade)
"""

print('=' * 100)
print('ETAPA 5C - DIAGNÓSTICO DE CLAREZA VISUAL')
print('=' * 100)

# ============================================================================
# DADOS DO PDF (Histórico de Programação - Linha 03)
# ============================================================================
pdf_data = [
    {'op': '201808', 'seq': 39, 'descricao': 'Desinfetante Aquafast Cha Branco 2l', 'inicio': 'Sex 27/03 07:05', 'fim': 'Sex 27/03 08:24', 'duracao': '01:19'},
    {'op': '201808', 'tipo': 'Setup', 'inicio': 'Sex 27/03 08:24', 'fim': 'Sex 27/03 08:44', 'duracao': '00:20'},
    {'op': '200942', 'seq': 40, 'descricao': 'Desinfetante Ito Marine 2l', 'inicio': 'Sex 27/03 08:44', 'fim': 'Sex 27/03 08:51', 'duracao': '00:07'},
    {'op': '200942', 'tipo': 'Setup', 'inicio': 'Sex 27/03 08:51', 'fim': 'Sex 27/03 09:01', 'duracao': '00:10'},
    {'op': '201799', 'seq': 41, 'descricao': 'Desinfetante Aquafast Marine 2l', 'inicio': 'Sex 27/03 09:01', 'fim': 'Sex 27/03 13:29', 'duracao': '02:31'},
    {'op': '201799', 'tipo': 'Setup', 'inicio': 'Sex 27/03 13:29', 'fim': 'Sex 27/03 13:39', 'duracao': '00:10'},
    {'op': '200917', 'seq': 42, 'descricao': 'Desinfetante Ito Harmonia Natural 2l', 'inicio': 'Sex 27/03 13:39', 'fim': 'Sex 27/03 13:46', 'duracao': '00:07'},
    {'op': '200917', 'tipo': 'Setup', 'inicio': 'Sex 27/03 13:46', 'fim': 'Sex 27/03 13:56', 'duracao': '00:10'},
    {'op': '201797', 'seq': 43, 'descricao': 'Desinfetante Aquafast Harmonia Natural 2l', 'inicio': 'Sex 27/03 13:56', 'fim': 'Sex 27/03 16:27', 'duracao': '02:31'},
    {'op': '201797', 'tipo': 'Setup', 'inicio': 'Sex 27/03 16:27', 'fim': 'Sex 27/03 16:37', 'duracao': '00:10'},
    {'op': '201804', 'seq': 44, 'descricao': 'Desinfetante Aquafast Paixao 2l', 'inicio': 'Sex 27/03 16:37', 'fim': 'Seg 30/03 11:13', 'duracao': '05:16'},
    {'op': '201804', 'tipo': 'Setup', 'inicio': 'Seg 30/03 11:13', 'fim': 'Seg 30/03 11:23', 'duracao': '00:10'},
]

print('\n📋 DADOS DO PDF - Linha 03 (Primeiras 12 operações):')
print('-' * 100)
print(f'{"OP":<10} {"Tipo":<15} {"Horário Início":<20} {"Horário Fim":<20} {"Duração":<10}')
print('-' * 100)

for item in pdf_data:
    op = item.get('op', '')
    tipo = 'Produção' if 'descricao' in item else 'SETUP'
    inicio = item.get('inicio', '')
    fim = item.get('fim', '')
    duracao = item.get('duracao', '')
    print(f'{op:<10} {tipo:<15} {inicio:<20} {fim:<20} {duracao:<10}')

# ============================================================================
# ANÁLISE DO GRÁFICO ATUAL
# ============================================================================
print('\n' + '=' * 100)
print('🔍 ANÁLISE DO PROBLEMA - O QUE ESTÁ FALTANDO NO GRÁFICO')
print('=' * 100)

print('''
NO PDF (MuitoClaro):
  ✓ Cada OP tem um número: 201808, 200942, 201799, etc.
  ✓ Cada OP tem um horário de INÍCIO: Sex 27/03 07:05
  ✓ Cada OP tem um horário de FIM: Sex 27/03 08:24
  ✓ Setup aparece separado com seu próprio tempo: 00:20, 00:10, etc.
  ✓ Sequência é visível: sabe exatamente quando uma termina e outra começa
  ✓ Pode ver a duração real de cada operação
  ✓ Pode ver qual dia e qual hora começa

NO GRÁFICO ATUAL (Confuso):
  ✗ Não mostra o número da OP
  ✗ Não mostra a hora de INÍCIO
  ✗ Não mostra a hora de FIM
  ✗ Barra é apenas um retângulo colorido
  ✗ Setup é apenas uma barra mais colorida
  ✗ Não consegue ler qual operação é qual
  ✗ Não consegue saber exatamente quando começa/termina
  ✗ Grid com horas (0h, 6h, 12h, 18h) não ajuda a ler
  ✗ Invisível para um leitor de PCP

CONCLUSÃO:
  O gráfico precisa de LABELS e INFORMAÇÕES CLARAS
  Não é "falta de barras", é "falta de TEXTO"
''')

# ============================================================================
# SOLUÇÃO PROPOSTA
# ============================================================================
print('\n' + '=' * 100)
print('💡 SOLUÇÃO PROPOSTA - ADICIONAR LABELS E INFORMAÇÕES')
print('=' * 100)

print('''
ETAPA 5D - ADD LABELS AO GRÁFICO

O que adicionar a CADA BARRA:

1. LABEL PRINCIPAL (dentro ou perto da barra):
   - Mostrar: "OP 201808"
   - Ou: "OP 201808\n07:05-08:24"
   - Tamanho: Legível

2. TIPO DIFERENCIADO:
   - Setup: Mostrar "SETUP" ou "🔧" com duração
   - Produção: Mostrar "OP XXXXX" com duração

3. HORAS VISÍVEIS:
   - Hora início: 07:05
   - Hora fim: 08:24
   - Duração: 01:19

4. MELHOR CONTRASTE:
   - Texto branco em fundo colorido
   - Fonte maior e bold
   - Truncar se necessário, mas mostrar o máximo

EXEMPLO DE COMO FICA:

Dia 0 (Sex 27/03)
┌─────────────────────────────────────────────────┐
│ ┌─────────────┐                                 │
│ │ OP 201808   │   ← Barra laranja com label     │
│ │ 07:05-08:24 │                                 │
│ └─────────────┘                                 │
│   ┌───┐                                         │
│   │🔧 │   ← Setup pequeno                      │
│   │08:44│                                       │
│   └───┘                                         │
│ ┌──────────┐                                    │
│ │OP 200942 │  ← Próxima OP                    │
│ │08:44-0851│                                    │
│ └──────────┘                                    │
└─────────────────────────────────────────────────┘

VANTAGENS:
  ✓ Leitor vê imediatamente qual OP é
  ✓ Vê o horário de início e fim
  ✓ Vê setup vs produção claramente
  ✓ Compara com PDF e faz sentido
  ✓ Serve para o PCP mesmo que offline

IMPLEMENTAÇÃO:
  - Modificar renderTimelineRow_v2() para adicionar TEXT content às barras
  - Usar fontSize pequeno mas legível (9-10px)
  - Truncar texto se barra for muito pequena
  - Manter as cores, apenas adicionar informação
''')

# ============================================================================
# MAPEAMENTO PDF → GRÁFICO
# ============================================================================
print('\n' + '=' * 100)
print('🗺️  MAPEAMENTO: O QUE CADA OP DEVERIA MOSTRAR NO GRÁFICO')
print('=' * 100)

print(f'\n{"OP":<10} {"Deveria mostrar":<50} {"Visível?":<10}')
print('-' * 100)

visibilidade = [
    ('201808', 'OP 201808 | 07:05-08:24 (01:19)', '❌ NÃO'),
    ('201808', 'SETUP | 08:24-08:44 (00:20)', '❌ NÃO'),
    ('200942', 'OP 200942 | 08:44-08:51 (00:07)', '❌ NÃO'),
    ('200942', 'SETUP | 08:51-09:01 (00:10)', '❌ NÃO'),
    ('201799', 'OP 201799 | 09:01-13:29 (02:31)', '❌ NÃO'),
    ('201799', 'SETUP | 13:29-13:39 (00:10)', '❌ NÃO'),
    ('200917', 'OP 200917 | 13:39-13:46 (00:07)', '❌ NÃO'),
    ('200917', 'SETUP | 13:46-13:56 (00:10)', '❌ NÃO'),
    ('201797', 'OP 201797 | 13:56-16:27 (02:31)', '❌ NÃO'),
    ('201797', 'SETUP | 16:27-16:37 (00:10)', '❌ NÃO'),
    ('201804', 'OP 201804 | 16:37-11:13 próx (05:16)', '❌ NÃO'),
    ('201804', 'SETUP | 11:13-11:23 (00:10)', '❌ NÃO'),
]

for op, should_show, visible in visibilidade:
    print(f'{op:<10} {should_show:<50} {visible:<10}')

# ============================================================================
# CÓDIGO NECESSÁRIO
# ============================================================================
print('\n' + '=' * 100)
print('✅ PRÓXIMA ETAPA (5D) - IMPLEMENTAÇÃO')
print('=' * 100)

print('''
MODIFICAÇÕES NECESSÁRIAS:

1. renderTimelineRow_v2():
   - Já está renderizando barras com position: absolute ✓
   - Precisa ADICIONAR conteúdo de texto com info

2. Cada barra (PREVISTO + REALIZADO) precisa de:
   ```javascript
   // Para PREVISTO (laranja):
   barPrevisto.textContent = `OP ${item.op}`;  // ← Adicionar info
   
   // Para REALIZADO (verde):
   barRealizado.textContent = `${item.percentual_cumprimento.toFixed(0)}%`;  // ← Já tem
   ```

3. Adicionar informação de HORA:
   ```javascript
   const horaInicio = this.hourToTime(item.start);
   const horaFim = this.hourToTime(item.end);
   barPrevisto.title = `OP ${item.op}: ${horaInicio} - ${horaFim}`;  // Tooltip
   ```

4. Para SETUP, mostrar claramente:
   ```javascript
   if (item.tipo.toUpperCase() === 'SETUP') {
       barPrevisto.textContent = `🔧 SETUP\n${item.duracao_horas.toFixed(2)}h`;
   }
   ```

RESULTADO ESPERADO:
  Você olha o gráfico e vê: "OP 201808 | 07:05-08:24"
  Depois olha o PDF e vê: "Sex 27/03 07:05 até Sex 27/03 08:24"
  E faz SENTIDO, porque os horários coincidem!
''')

print('\n' + '=' * 100)
print('ETAPA 5C - DIAGNÓSTICO CONCLUÍDO')
print('=' * 100)
print('''
PROBLEMA IDENTIFICADO: Falta de labels e informações nas barras
SOLUÇÃO: Adicionar texto com OP, horas de início/fim e duração
PRÓXIMA ETAPA: 5D - Implementar labels nas barras

Pronto para executar ETAPA 5D?
''')
