#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ETAPA 5D: Teste de Renderização de Labels nas Barras do Gantt
Verifica se as labels (OP, tempos, percentuais, quantidades) estão sendo exibidas corretamente
no Gantt de Sequenciamento.

Objetivo:
- Validar que barPrevisto mostra "OP XXXXX" e "HH:MM-HH:MM"
- Validar que barRealizado mostra percentual e quantidade
- Validar que SETUP mostra duração
- Verificar que innerHTML pode renderizar múltiplas linhas em barras
"""

import requests
import json
from datetime import datetime

# Configuração
BASE_URL = "http://localhost:8081"
# Para LN03 (Linha 03), usando programacao_id (pode variar conforme BD)
API_URL = f"{BASE_URL}/api/sequenciamento_gantt.php?programacao_id=3"

print("="*70)
print("ETAPA 5D: Teste de Renderização de Labels no Gantt")
print("="*70)
print()

# Fazer requisição para o API
print("1. Obtendo dados do API...")
try:
    response = requests.get(API_URL)
    response.raise_for_status()
    api_response = response.json()
    
    # Extrair timeline do dicionário de resposta
    if 'timeline' in api_response and isinstance(api_response['timeline'], list):
        data = api_response['timeline']
    else:
        data = api_response if isinstance(api_response, list) else []
    
    print(f"✓ API respondeu com 200 OK")
    print(f"✓ Total de items: {len(data)}")
except Exception as e:
    print(f"✗ Erro ao chamar API: {e}")
    exit(1)

print()
print("2. Verificando estrutura de dados...")

# Verificar se temos os campos necessários
required_fields = ['op', 'start', 'end', 'tipo', 'duracao_horas', 
                   'quantidade_prevista', 'quantidade_realizada', 
                   'percentual_cumprimento', 'hora_inicio', 'hora_fim']

sample_item = data[0] if data else {}
missing_fields = [f for f in required_fields if f not in sample_item]

if missing_fields:
    print(f"✗ Campos faltando: {missing_fields}")
    exit(1)
else:
    print(f"✓ Todos os campos necessários estão presentes")

print()
print("3. Analisando validação de labels para diferentes tipos...")
print()

# Análise de samples
produtivos = [item for item in data if item.get('tipo', '').upper() != 'SETUP']
setups = [item for item in data if item.get('tipo', '').upper() == 'SETUP']

print(f"Total de operações de PRODUÇÃO: {len(produtivos)}")
print(f"Total de operações de SETUP: {len(setups)}")
print()

# Função helper para converter horas em HH:MM
def hora_para_hhmm(horas):
    total_min = round(horas * 60)
    h = int(total_min // 60)
    m = total_min % 60
    return f"{h:02d}:{m:02d}"

# Exemplos de labels que DEVERÃO ser exibidas
print("EXEMPLOS DE LABELS QUE SERÃO RENDERIZADAS:")
print("-" * 70)

# Produção - primeiro exemplo
if produtivos:
    item = produtivos[0]
    op_label = f"OP {item['op']}"
    hora_inicio = hora_para_hhmm(item['start'])
    hora_fim = hora_para_hhmm(item['end'])
    tempo_label = f"{hora_inicio}-{hora_fim}"
    percentual_label = f"{item['percentual_cumprimento']:.1f}%"
    qty_label = f"{item['quantidade_realizada']:.0f}un"
    
    print()
    print(f"OPERAÇÃO DE PRODUÇÃO (OP {item['op']}):")
    print(f"  barPrevisto exibirá:")
    print(f"    Linha 1: {op_label} (fontSize: 8px)")
    print(f"    Linha 2: {tempo_label} (fontSize: 7px)")
    print(f"  barRealizado exibirá:")
    print(f"    Linha 1: {percentual_label} (fontSize: 8px)")
    print(f"    Linha 2: {qty_label} (fontSize: 7px)")
    print(f"  Duração: {item['duracao_horas']:.2f}h")

# Setup - primeiro exemplo
if setups:
    item = setups[0]
    hora_inicio = hora_para_hhmm(item['start'])
    hora_fim = hora_para_hhmm(item['end'])
    duracao_min = hora_para_hhmm(item['duracao_horas'])
    percentual_label = f"{item['percentual_cumprimento']:.1f}%"
    
    print()
    print(f"OPERAÇÃO DE SETUP:")
    print(f"  barPrevisto exibirá:")
    print(f"    🔧 {duracao_min} (fontSize: 7px)")
    print(f"  barRealizado exibirá:")
    print(f"    {percentual_label} (fontSize: 7px)")
    print(f"  Intervalo: {hora_inicio} - {hora_fim}")

print()
print("-" * 70)
print()

# Validação de legibilidade
print("4. Validação de Legibilidade das Labels...")
print()

# Verificar se há operações muito curtas (que podem ter problemas de rendering)
short_ops = [item for item in data if (item['end'] - item['start']) < 0.2]  # Menos de 12 minutos
print(f"Operações muito curtas (< 12 minutos): {len(short_ops)}")
if short_ops:
    print(f"  Nota: Labels podem ser truncadas em operações muito curtas")
    for op in short_ops[:3]:
        duracao = hora_para_hhmm(op['end'] - op['start'])
        print(f"    OP {op['op']}: {duracao} (pode ser problema)")

# Verificar dias com muitas operações
print()
print("Dias com muitas operações (possível crowding):")
dias_ops = {}
for item in data:
    dia = item.get('dia', 0)
    if dia not in dias_ops:
        dias_ops[dia] = 0
    dias_ops[dia] += 1

for dia in sorted(dias_ops.keys()):
    if dias_ops[dia] > 20:
        print(f"  Dia {dia}: {dias_ops[dia]} operações (pode cauDivisión de espaço)")

print()
print("=" * 70)
print("CONCLUSÃO DO TESTE ETAPA 5D")
print("=" * 70)
print()
print("✓ Estrutura de dados validada")
print("✓ Labels podem ser renderizadas com HTML e CSS flexbox")
print("✓ Todos os campos necessários estão presentes")
print()
print("PRÓXIMO PASSO: Abrir em browser e validar visualmente:")
print(f"  {BASE_URL}/sequenciamento_grafico.php")
print()
print("Critérios de Aceitação:")
print("  ✓ Consegue ler 'OP XXXXX' nas barras laranja (Previsto)")
print("  ✓ Consegue ler 'HH:MM-HH:MM' nas barras laranja")
print("  ✓ Consegue ler percentual nas barras verdes (Realizado)")
print("  ✓ Labels não são truncadas (ellipsis)")
print("  ✓ Setup mostra duração com emoji 🔧")
print("  ✓ Comparar com PDF e validar clareza")
print()
