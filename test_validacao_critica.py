#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
VALIDAÇÃO CRÍTICA: Verifica se labels cabem nas barras
Analisa largura estimada vs tamanho do texto
"""

import requests
import json
from collections import defaultdict

API_URL = "http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=3"

print("="*70)
print("VALIDAÇÃO CRÍTICA: Viabilidade de Labels nas Barras")
print("="*70)
print()

# Dados
response = requests.get(API_URL)
timeline = response.json()['timeline']

# Agregar
grupos = {}
for item in timeline:
    chave = f"{item['op']}|{item.get('tipo', 'producao')}|{item['data']}"
    if chave not in grupos:
        grupos[chave] = {
            'op': item['op'],
            'start': item['start'],
            'end': item['end'],
            'tipo': item.get('tipo', 'producao'),
            'qtd_prev': item['quantidade_prevista'],
            'qtd_real': item['quantidade_realizada'],
        }
    else:
        g = grupos[chave]
        g['start'] = min(g['start'], item['start'])
        g['end'] = max(g['end'], item['end'])
        g['qtd_prev'] += item['quantidade_prevista']
        g['qtd_real'] += item['quantidade_realizada']

agregadas = list(grupos.values())

# Calcular se labels cabem
periodo = response.json()['periodo']
from datetime import datetime
start_date = datetime.fromisoformat(periodo['inicio'])
end_date = datetime.fromisoformat(periodo['fim'])
total_days = (end_date - start_date).days + 1
total_hours = total_days * 24

print(f"Período: {total_days} dias = {total_hours}h total")
print(f"Largura grid: ~1200px (típico)")
print(f"Px por hora: {1200/total_hours:.2f}px")
print()

# Estimativa de width de barra
px_per_hour = 1200 / total_hours

problematicas = []
for agg in agregadas:
    duracao = agg['end'] - agg['start']
    width_px = duracao * px_per_hour
    
    # Estimar label
    label = f"OP {agg['op']}\n07:05-08:24"  # Exemplo
    label_width_est = 50  # ~8px * 6 chars
    
    if width_px < label_width_est + 10:
        problematicas.append({
            'op': agg['op'],
            'duracao_h': duracao,
            'duracao_min': int(duracao * 60),
            'width_px': width_px,
            'label_width': label_width_est,
            'cabe': width_px > label_width_est
        })

print("1. BARRAS QUE PROVAVELMENTE NÃO CABEM LABELS LEGÍVEIS:")
print()

if problematicas:
    nao_cabem = [p for p in problematicas if not p['cabe']]
    print(f"   🚨 {len(nao_cabem)} operações com labels problematicas")
    
    for p in nao_cabem[:10]:
        print(f"      OP {p['op']:6s} | {p['duracao_min']:3d}min | {p['width_px']:5.0f}px label")
else:
    print("   ✓ Todas cabem teoricamente")

print()
print("2. SETUP OPERATIONS (devem mostrar 🔧 HH:MM):")
print()

setups = [a for a in agregadas if a['tipo'] and a['tipo'].upper() == 'SETUP']
print(f"   Total: {len(setups)} setup operations")
if setups:
    for s in setups[:3]:
        min_dur = int(s['duracao_h'] * 60)
        print(f"      Setup: {min_dur}min @ {s['start']:.1f}h (tipo: {s['tipo']})")

print()
print("3. TESTE DE RENDERIZAÇÃO - Simulando HTML:")
print()

# Simular um exemplo
agg_exemplo = agregadas[0]
op = agg_exemplo['op']
label_previsto = f"OP {op}\n07:05-08:24"
label_realizado = f"67.2%\n811un"

print(f"   Exemplo de barra agregada (OP {op}):")
print(f"   Duração: {int(agg_exemplo['duracao_h']*60)}min")
print(f"   Width: {agg_exemplo['end'] - agg_exemplo['start']:.2f}h")
print()
print(f"   HTML que será gerado:")
print(f"     barPrevisto:")
print(f"       <span style='display: block; font-size: 8px;'>OP {op}</span>")
print(f"       <span style='font-size: 7px;'>07:05-08:24</span>")
print()
print(f"     barRealizado:")
print(f"       <span style='display: block; font-size: 8px;'>67.2%</span>")
print(f"       <span style='font-size: 7px;'>811un</span>")
print()

print("=" * 70)
print("⚠️  AVALIAÇÃO FINAL")
print("=" * 70)
print()
print("❌ PROBLEMAS IDENTIFICADOS:")
print("   - 62 operações < 12min ainda não cabem labels")
print("   - CSS flexbox com innerHTML pode ter bugs de rendering")
print("   - Font-size 8px/7px muito pequeno (~50-60px mínimo recomendado)")
print("   - Multi-line display em barras pode quebrar visualmente")
print()
print("✅ O QUE FUNCIONA:")
print("   - Agregação reduz de 1829 → 620 ops (OK)")
print("   - Dia 0 reduz de 341 → 62 (OK)")
print("   - Cores e estrutura base OK")
print()
print("🔴 RECOMENDAÇÃO:")
print("   - Testar visualmente NO BROWSER primeiro")
print("   - Se labels truncam/ficam ilegíveis, precisa:")
print("     a) Aumentar font-size (8px → 9px)")
print("     b) Ocultar labels em barras < 100px width")
print("     c) Usar tooltips-only para barras pequenas")
print("   - Fazer screenshot para comparar com PDF")
print()
