#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Validação: Os dados SEM agregação - cada linha é uma operação real
"""

import requests
import json
from datetime import datetime

API_URL = "http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=3"

response = requests.get(API_URL)
data = response.json()
timeline = data['timeline']

print("="*80)
print("DADOS ORIGINAIS (SEM AGREGAÇÃO)")
print("="*80)
print()

# Agrupar por OP para ver duplicatas
ops_agrupadas = {}
for item in timeline:
    op = item['op']
    if op not in ops_agrupadas:
        ops_agrupadas[op] = []
    ops_agrupadas[op].append(item)

# Mostrar as primeiras 5 OPs e seus items
for i, (op, items) in enumerate(list(ops_agrupadas.items())[:5], 1):
    print(f"OP {op}: {len(items)} linhas")
    for j, item in enumerate(items[:3], 1):
        print(f"  #{j} | {item['data']} {item['hora_inicio']}-{item['hora_fim']} | {item['quantidade_prevista']:.0f}un | {item['quantidade_realizada']:.0f}un")
    if len(items) > 3:
        print(f"  ... ({len(items)-3} mais)")
    print()

print()
print("QUESTÃO: Cada linha é uma OPERAÇÃO DIFERENTE ou DUPLICAÇÃO?")
print()

# Checar: tem OPs que repetem?
ops_com_multiplos = {op: items for op, items in ops_agrupadas.items() if len(items) > 1}
print(f"OPs com múltiplas linhas: {len(ops_com_multiplos)}")
print(f"Total: {len(timeline)} linhas")
print()

if ops_com_multiplos:
    print("Exemplos de OPs duplicadas:")
    for op, items in list(ops_com_multiplos.items())[:3]:
        print(f"\n  OP {op}:")
        for item in items[:2]:
            print(f"    • {item['data']} {item['hora_inicio']}-{item['hora_fim']} qty={item['quantidade_prevista']:.0f}")
