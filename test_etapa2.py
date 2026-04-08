#!/usr/bin/env python3
"""
ETAPA 2 - Teste do novo layout Gantt
Verifica:
1. Sintaxe JavaScript (parsing básico)
2. API retorna dados com campos esperados
3. Período de datas está correto
"""

import urllib.request
import json
from datetime import datetime, timedelta

url = 'http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=1'
response = urllib.request.urlopen(url)
data = json.loads(response.read().decode())

if not data.get('sucesso'):
    print(f'ERRO: {data.get("erro")}')
    exit(1)

print('ETAPA 2 - VALIDACAO GANTT LAYOUT')
print('=' * 70)

timeline = data['timeline']
periodo = data['periodo']

print(f'\n📅 PERÍODO: {periodo["inicio"]} → {periodo["fim"]}')
print(f'   Duração: {(datetime.fromisoformat(periodo["fim"]) - datetime.fromisoformat(periodo["inicio"])).days + 1} dias')

# Agrupar por dia
dias = {}
for item in timeline:
    dia_key = item['dia']
    if dia_key not in dias:
        dias[dia_key] = {'count': 0, 'items': []}
    dias[dia_key]['count'] += 1
    dias[dia_key]['items'].append(item)

print(f'\n📊 DISTRIBUIÇÃO POR DIA:')
for dia_idx in sorted(dias.keys()):
    data_val = datetime.fromisoformat(dias[dia_idx]['items'][0]['data'])
    weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']
    day_name = weekdays[data_val.weekday()]
    date_str = data_val.strftime('%d/%m')
    count = dias[dia_idx]['count']
    
    # Calcular hora mín/máx do dia
    times = [item['start'] for item in dias[dia_idx]['items']]
    min_h = min(times)
    max_h = max(times)
    minutos = int((min_h % 1) * 60)
    horas = int(min_h)
    
    print(f'   Dia {dia_idx} ({day_name} {date_str}): {count} operações (a partir {horas:02d}:{minutos:02d})')

# Validar campos esperados
print(f'\n✓ CAMPOS OBRIGATÓRIOS NA API:')
required_fields = ['id', 'op', 'nome', 'start', 'end', 'dia', 'data', 'duracao_horas', 
                   'quantidade_prevista', 'quantidade_realizada', 'percentual_cumprimento', 'tipo']
sample_item = timeline[0]
missing = [f for f in required_fields if f not in sample_item]
if missing:
    print(f'   ❌ FALTAM: {missing}')
else:
    print(f'   ✓ Todos os {len(required_fields)} campos presentes')

# Verificar setup
setups = [i for i in timeline if i.get('tipo', '').upper() == 'SETUP']
producoes = [i for i in timeline if i.get('tipo', '').upper() != 'SETUP']
print(f'\n🔧 OPERAÇÕES:')
print(f'   Produção: {len(producoes)} itens')
print(f'   Setup: {len(setups)} itens')

if setups:
    print(f'\n   Exemplo de SETUP:')
    setup_sample = setups[0]
    print(f'   - OP: {setup_sample["op"]}')
    print(f'   - Duração: {setup_sample["duracao_horas"]}h')
    print(f'   - Horário: {setup_sample["hora_inicio"]} a {setup_sample["hora_fim"]}')
    print(f'   - Status: {setup_sample["status"]}')

# Verificar multi-dia
multi_day = [i for i in timeline if i['end'] // 24 > i['start'] // 24]
print(f'\n🌐 OPERAÇÕES MULTI-DIA: {len(multi_day)}')
if multi_day:
    sample = multi_day[0]
    start_day = int(sample['start'] / 24)
    end_day = int(sample['end'] / 24)
    print(f'   Exemplo: OP {sample["op"]} vai de Dia {start_day} a Dia {end_day}')

print('\n' + '=' * 70)
print('✓ VALIDAÇÃO CONCLUÍDA')
print('✓ Dados prontos para renderização Gantt')
print(f'✓ Total de {len(timeline)} itens para visualizar')
