#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
DIAGNÓSTICO REAL: Por que as barras estão tudo comprimidas?
Analisando os dados brutos e posicionamento calculado
"""

import requests
import json
from datetime import datetime, timedelta

API_URL = "http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=3"

print("="*80)
print("DIAGNÓSTICO: Distribuição de Barras por Dia")
print("="*80)
print()

# Dados
response = requests.get(API_URL)
data = response.json()
timeline = data['timeline']
periodo = data['periodo']

start_date = datetime.fromisoformat(periodo['inicio'])
end_date = datetime.fromisoformat(periodo['fim'])
total_days = (end_date - start_date).days + 1
total_hours = total_days * 24

print(f"PERÍODO: {periodo['inicio']} a {periodo['fim']}")
print(f"Total: {total_days} dias = {total_hours}h")
print()

# Agregar
grupos = {}
for item in timeline:
    chave = f"{item['op']}|{item.get('tipo', 'producao')}|{item['data']}"
    if chave not in grupos:
        grupos[chave] = item.copy()
        grupos[chave]['_count'] = 1
    else:
        g = grupos[chave]
        g['start'] = min(g['start'], item['start'])
        g['end'] = max(g['end'], item['end'])
        g['quantidade_prevista'] += item['quantidade_prevista']
        g['quantidade_realizada'] += item['quantidade_realizada']
        g['_count'] += 1

agregadas = list(grupos.values())

print(f"Operações: {len(timeline)} → {len(agregadas)} (agregadas)")
print()

# Analisar posicionamento esperado vs real
print("ANÁLISE DE POSICIONAMENTO:")
print("-" * 80)
print()

# Primeiras 10 operações
for i, agg in enumerate(agregadas[:15], 1):
    start = agg['start']
    end = agg['end']
    duracao = end - start
    
    # Qual dia deveria estar?
    start_day = int(start / 24)
    end_day = int(end / 24)
    data_op = agg.get('data', '?')
    
    # Posicionamento calculado (como código faz)
    left_percent = (start / total_hours) * 100
    width_percent = (duracao / total_hours) * 100
    
    print(f"{i:2d}. OP {agg['op']:6s} | Dia {start_day}-{end_day} ({data_op})")
    print(f"    Start: {start:6.2f}h ({agg['hora_inicio']}) | End: {end:6.2f}h ({agg['hora_fim']})")
    print(f"    Left: {left_percent:5.1f}% | Width: {width_percent:5.1f}%")
    print(f"    ✓ Duração: {duracao:.2f}h = {int(duracao*60)}min")
    print()

# Problema detectado?
print()
print("="*80)
print("ANÁLISE DO PROBLEMA:")
print("="*80)
print()

# Ver distribuição por dia real vs esperado
days_with_ops = {}
for agg in agregadas:
    start_day = int(agg['start'] / 24)
    if start_day not in days_with_ops:
        days_with_ops[start_day] = []
    days_with_ops[start_day].append(agg['op'])

print("Operações por dia de INÍCIO:")
for day in sorted(days_with_ops.keys()):
    calc_date = start_date + timedelta(days=day)
    ops = days_with_ops[day]
    print(f"  Dia {day:2d} ({calc_date.strftime('%a %d/%m')}): {len(ops):3d} ops")
    if len(ops) <= 3:
        print(f"         OPs: {', '.join(ops[:5])}")

print()
print("QUESTÕES CRÍTICAS:")
print()
print("1. As barras estão em dias DIFERENTES?")
print("   → Se SIM: Layout está correto, problema é visual")
print("   → Se NÃO: Dados agregados estão ERRADOS")
print()

# Verificar agregação
multi_day_ops = [a for a in agregadas if (a['end'] - a['start']) >= 24]
print(f"2. Operações que duram 24h+: {len(multi_day_ops)}")
for op in multi_day_ops[:5]:
    duracao = op['end'] - op['start']
    print(f"   OP {op['op']}: {duracao:.1f}h ({int(duracao/24)}d+)")

print()
print("RECOMENDAÇÃO:")
print()
print("✓ Se operações estão em dias diferentes → problema é CSS/layout")
print("✗ Se operações estão no mesmo dia → problema é nos DADOS/agregação")
print()
