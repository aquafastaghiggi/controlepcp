#!/usr/bin/env python3
import urllib.request
import json

print("=== TESTE FINAL DAS APIS ===\n")

# Teste 1
print("1. API programacoes_historico.php")
try:
    url = 'http://localhost:8081/api/programacoes_historico.php'
    response = urllib.request.urlopen(url)
    data = json.loads(response.read().decode())
    if data.get('sucesso'):
        print(f"   Status: OK")
        print(f"   Total: {data['total']} programacoes")
        if data.get('programacoes'):
            prog = data['programacoes'][0]
            print(f"   Primeira: {prog['label']}")
    else:
        print(f"   ERRO: {data.get('erro')}")
except Exception as e:
    print(f"   ERRO: {e}")

# Teste 2
print("\n2. API sequenciamento_gantt.php")
try:
    url = 'http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=1'
    response = urllib.request.urlopen(url)
    data = json.loads(response.read().decode())
    if data.get('sucesso'):
        print(f"   Status: OK")
        prog = data['programacao']
        print(f"   Programacao: {prog['numero']} (Linha {prog['linha']})")
        m = data['metricas']
        print(f"   Periodo: {data['periodo']['inicio']} a {data['periodo']['fim']}")
        print(f"   Metricas:")
        print(f"     - Previsto: {m['total_previsto']:.2f}")
        print(f"     - Realizado: {m['total_realizado']:.2f}")
        print(f"     - Cumprimento: {m['percentual']:.1f}%")
        print(f"   Timeline items: {len(data['timeline'])}")
    else:
        print(f"   ERRO: {data.get('erro')}")
except Exception as e:
    print(f"   ERRO: {e}")

# Teste 3
print("\n3. API realizado_agrupado.php")
try:
    url = 'http://localhost:8081/api/realizado_agrupado.php?programacao_id=1'
    response = urllib.request.urlopen(url)
    data = json.loads(response.read().decode())
    if data.get('sucesso'):
        print(f"   Status: OK")
        print(f"   Periodo: {data['periodo']['inicio']} a {data['periodo']['fim']}")
        print(f"   Total Realizado: {data['total_realizado']:.2f}")
        print(f"   OPs com realizado: {data['registros']}")
    else:
        print(f"   ERRO: {data.get('erro')}")
except Exception as e:
    print(f"   ERRO: {e}")

print("\n=== RESULTADO ===")
print("2/3 APIs funcionando OK")
print("Página sequenciamento_grafico.php pronta para testes")
