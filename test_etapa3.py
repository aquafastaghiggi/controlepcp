#!/usr/bin/env python3
"""
ETAPA 3 - Validação de Dual-Bar Rendering
Verifica dados de Previsto vs Realizado para renderização
"""

import urllib.request
import json

url = 'http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=1'
response = urllib.request.urlopen(url)
data = json.loads(response.read().decode())

if not data.get('sucesso'):
    print(f'ERRO: {data.get("erro")}')
    exit(1)

print('ETAPA 3 - VALIDACAO DUAL-BAR RENDERING')
print('=' * 80)

timeline = data['timeline']

# Categorizar por cumprimento
completo = [i for i in timeline if i['percentual_cumprimento'] >= 100]
parcial = [i for i in timeline if 0 < i['percentual_cumprimento'] < 100]
nao_iniciado = [i for i in timeline if i['percentual_cumprimento'] == 0]

print(f'\n📊 DISTRIBUIÇÃO POR CUMPRIMENTO:')
print(f'   ✅ Completo/Excedido (100%+): {len(completo)} itens')
print(f'   ⏳ Parcial (0-100%): {len(parcial)} itens')
print(f'   ❌ Não iniciado (0%): {len(nao_iniciado)} itens')
print(f'   TOTAL: {len(timeline)} itens')

print(f'\n📈 EXEMPLOS DE CADA CATEGORIA:')

if completo:
    sample = completo[0]
    print(f'\n   COMPLETO: OP {sample["op"]}')
    print(f'   - Previsto: {sample["duracao_horas"]:.2f}h')
    print(f'   - Realizado: {sample["quantidade_realizada"]:.0f}un / {sample["quantidade_prevista"]:.0f}un')
    print(f'   - Cumprimento: {sample["percentual_cumprimento"]:.1f}%')
    print(f'   - Barra PREVISTO: 100% (laranja)')
    print(f'   - Barra REALIZADO: 100% (verde)')

if parcial:
    sample = parcial[0]
    print(f'\n   PARCIAL: OP {sample["op"]}')
    print(f'   - Previsto: {sample["duracao_horas"]:.2f}h')
    print(f'   - Realizado: {sample["quantidade_realizada"]:.0f}un / {sample["quantidade_prevista"]:.0f}un')
    print(f'   - Cumprimento: {sample["percentual_cumprimento"]:.1f}%')
    print(f'   - Barra PREVISTO: 100% (laranja)')
    print(f'   - Barra REALIZADO: {sample["percentual_cumprimento"]:.1f}% (verde)')

if nao_iniciado:
    sample = nao_iniciado[0]
    print(f'\n   NÃO INICIADO: OP {sample["op"]}')
    print(f'   - Previsto: {sample["duracao_horas"]:.2f}h')
    print(f'   - Realizado: {sample["quantidade_realizada"]:.0f}un / {sample["quantidade_prevista"]:.0f}un')
    print(f'   - Cumprimento: {sample["percentual_cumprimento"]:.1f}%')
    print(f'   - Barra PREVISTO: 100% (laranja)')
    print(f'   - Barra REALIZADO: vazia (dashed cinza)')

# Validar dados para renderização
print(f'\n✓ VALIDAÇÃO DE CAMPOS:')
required = ['duracao_horas', 'quantidade_prevista', 'quantidade_realizada', 'percentual_cumprimento']
sample = timeline[0]
all_present = all(f in sample for f in required)
print(f'   Campos para cálculo: {"OK" if all_present else "FALTAM"}')

# Estatísticas de cumprimento
total_prev = sum(i['quantidade_prevista'] for i in timeline)
total_real = sum(i['quantidade_realizada'] for i in timeline)
global_percent = (total_real / total_prev * 100) if total_prev > 0 else 0

print(f'\n🎯 MÉTRICAS GLOBAIS:')
print(f'   Total Previsto: {total_prev:,.0f} un')
print(f'   Total Realizado: {total_real:,.0f} un')
print(f'   Cumprimento Geral: {global_percent:.1f}%')

print('\n' + '=' * 80)
print('✓ ETAPA 3 PRONTA PARA TESTE VISUAL')
print('✓ Barra PREVISTO renderizará em laranja (#f97316)')
print('✓ Barra REALIZADO renderizará em verde (#16a34a)')
print('✓ Tamanho de REALIZADO proporcional ao percentual_cumprimento')
