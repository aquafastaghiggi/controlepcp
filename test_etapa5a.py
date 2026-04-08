#!/usr/bin/env python3
"""
ETAPA 5A - DIAGNÓSTICO COMPLETO
Identificar causa da duplicidade e tamanho grande
"""

import urllib.request
import json
from datetime import datetime

url = 'http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=1'
response = urllib.request.urlopen(url)
data = json.loads(response.read().decode())

if not data.get('sucesso'):
    print(f'ERRO: {data.get("erro")}')
    exit(1)

print('=' * 90)
print('ETAPA 5A - DIAGNÓSTICO COMPLETO')
print('=' * 90)

timeline = data['timeline']
periodo = data['periodo']

# =============================================================================
# PROBLEMA 1: Quantas barras estão sendo criadas?
# =============================================================================
print('\n📊 PROBLEMA 1: CONTAGEM DE BARRAS')
print('-' * 90)

# Simular o código JavaScript
periodo_inicio = datetime.fromisoformat(periodo['inicio'])
periodo_fim = datetime.fromisoformat(periodo['fim'])

# Calcular daysInPeriod (como faz no JS)
days_in_period = (periodo_fim - periodo_inicio).days + 1

print(f'Período: {periodo["inicio"]} a {periodo["fim"]}')
print(f'daysInPeriod calculado: {days_in_period} dias')
print()

# Para cada item, contar quantas cópias da barra estão sendo criadas
barras_por_item = {}
total_barras_renderizadas = 0

for item in timeline:
    item_day_start = int(item['start'] / 24)
    item_day_end = int(item['end'] / 24)
    
    # Quantas colunas de dia esta barra cruza?
    dias_que_cruza = item_day_end - item_day_start + 1
    
    # Cada barra cruza N dias = renderizada N vezes (PROBLEMA!)
    # PREVISTO + REALIZADO = 2 barras por dia cruzado
    barras_neste_item = dias_que_cruza
    total_barras_renderizadas += barras_neste_item
    
    if dias_que_cruza > 1:
        barras_por_item[item['op']] = {
            'start': item['start'],
            'end': item['end'],
            'dia_inicio': item_day_start,
            'dia_fim': item_day_end,
            'dias_que_cruza': dias_que_cruza,
            'barras_renderizadas': barras_neste_item
        }

print(f'✓ Total de itens na API: {len(timeline)}')
print(f'✓ Total de BARRAS renderizadas (PREVISTO + REALIZADO): {total_barras_renderizadas * 2}')
print(f'  (Deve ser: {len(timeline) * 2} = {len(timeline)} items × 2 barras)')
print()

# Mostrar items que cruzam múltiplos dias
multi_day_items = [op for op, info in barras_por_item.items() if info['dias_que_cruza'] > 1]
print(f'⚠️  Items que cruzam múltiplos dias: {len(multi_day_items)}')
if multi_day_items:
    print('   Exemplos:')
    for op in multi_day_items[:5]:
        info = barras_por_item[op]
        print(f'   - OP {op}: Dia {info["dia_inicio"]} → Dia {info["dia_fim"]} ({info["dias_que_cruza"]} dias) = {info["barras_renderizadas"]} barras')

# =============================================================================
# PROBLEMA 2: Tamanho do Timeline
# =============================================================================
print('\n' + '=' * 90)
print('📐 PROBLEMA 2: TAMANHO DO TIMELINE')
print('-' * 90)

DAY_WIDTH_PX = 100
SIDEBAR_WIDTH = 250

total_width_timeline = days_in_period * DAY_WIDTH_PX
total_width_page = SIDEBAR_WIDTH + total_width_timeline

print(f'DAY_WIDTH_PX: {DAY_WIDTH_PX}px')
print(f'SIDEBAR_WIDTH: {SIDEBAR_WIDTH}px')
print(f'daysInPeriod: {days_in_period} dias')
print()
print(f'Cálculo de largura:')
print(f'  Timeline = {days_in_period} dias × {DAY_WIDTH_PX}px = {total_width_timeline}px')
print(f'  Página total = {SIDEBAR_WIDTH}px (sidebar) + {total_width_timeline}px (timeline)')
print(f'                = {total_width_page}px (~{total_width_page/1024:.2f}KB visual)')
print()

if total_width_page > 1600:
    print(f'⚠️  ALERTA: Largura em {total_width_page}px é MUITO GRANDE!')
    print(f'   Pode causar scroll horizontal excessivo')
    print(f'   Sugestão: Reduzir DAY_WIDTH_PX para ~{int(1600 / days_in_period)}px')
else:
    print(f'✓ Largura parece ok em telas wide')

# =============================================================================
# PROBLEMA 3: Distribuição por dia
# =============================================================================
print('\n' + '=' * 90)
print('📅 PROBLEMA 3: DISTRIBUIÇÃO POR DIA')
print('-' * 90)

dias_map = {}
for item in timeline:
    dia = item['dia']
    if dia not in dias_map:
        dias_map[dia] = {'count': 0, 'items': []}
    dias_map[dia]['count'] += 1
    dias_map[dia]['items'].append(item['op'])

print(f'\nDistribuição de items por dia:')
print(f'{"Dia":<5} {"Data":<12} {"Items":<6} {"OPs"}')
print('-' * 90)

for dia_idx in sorted(dias_map.keys()):
    sample_item = [i for i in timeline if i['dia'] == dia_idx][0]
    data_dia = sample_item['data']
    count = dias_map[dia_idx]['count']
    ops = ', '.join(dias_map[dia_idx]['items'][:3])
    if len(dias_map[dia_idx]['items']) > 3:
        ops += f' ... +{len(dias_map[dia_idx]["items"]) - 3}'
    
    print(f'{dia_idx:<5} {data_dia:<12} {count:<6} {ops}')

# =============================================================================
# DIAGNÓSTICO FINAL
# =============================================================================
print('\n' + '=' * 90)
print('🔍 DIAGNÓSTICO FINAL')
print('=' * 90)

print('\n✓ CAUSA DA DUPLICIDADE:')
print('  O código renderiza a mesma barra em CADA coluna de dia que ela cruza')
print('  Exemplo: OP 201039 vai de 7h (Dia 0) a 9.8h (Dia 0)')
print('  - renderTimelineRow_v2 cria coluna para cada dia (0 a 10)')
print('  - Para o Dia 0: cria barra PREVISTO + REALIZADO ✓')
print('  - Para os Dias 1-10: coluna vazia ✗')
print('  - Se outro item cruzar 2-3 dias: barra aparece 2-3 vezes')
print()

print('✓ CAUSA DO TAMANHO GRANDE:')
print(f'  daysInPeriod = {days_in_period} dias × {DAY_WIDTH_PX}px')
print(f'  Total = {total_width_page}px de largura')
print('  Precisa scroll horizontal para ver tudo')
print()

print('✓ PROBLEMA DO GANTT:')
print('  Barra que cruza de Dia 0 a Dia 2 aparece como:')
print('  - Barra em coluna Dia 0')
print('  - Barra em coluna Dia 1')
print('  - Barra em coluna Dia 2')
print('  EM VEZ DE UMA BARRA CONTÍNUA QUE ATRAVESSA')
print()

# =============================================================================
# RECOMENDAÇÃO
# =============================================================================
print('=' * 90)
print('✅ RECOMENDAÇÃO PARA ETAPA 5B')
print('=' * 90)

print('''
MUDANÇA ARQUITETURAL NECESSÁRIA:

1. Manter o header de dias (funciona bem ✓)

2. NOVO: Adicionar um container de barras com position: absolute
   que fica SOBRE o grid, não dentro das colunas

3. Renderizar CADA BARRA UMA VEZ:
   - Calcular left% baseado em horas TOTAIS (0-264h, não 0-24h por dia)
   - Calcular width% baseado em duração da barra
   - Deixar a barra "flutuar" sobre o grid
   - Barra aparece natural, cruzando dias

4. Grid lines: Mantém separação visual entre dias

EXEMPLO:
   start = 7h, end = 53h, total = 264h
   left = (7/264) × 100 = 2.65%
   width = ((53-7)/264) × 100 = 17.42%
   
   Resultado: Barra começa a 2.65% e ocupa 17.42%
              Automaticamente cruza do Dia 0 ao Dia 2 ✓

''')

print('=' * 90)
print(f'ETAPA 5A CONCLUÍDA')
print(f'Total de items: {len(timeline)}')
print(f'Days in period: {days_in_period}')
print(f'Timeline width: {total_width_timeline}px')
print(f'Multi-day items: {len(multi_day_items)}')
print('=' * 90)
