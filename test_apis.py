#!/usr/bin/env python3
import urllib.request
import json

print('=== TESTE API 1: programacoes_historico.php ===\n')
try:
    response = urllib.request.urlopen('http://localhost:8081/api/programacoes_historico.php')
    data = json.loads(response.read().decode())
    if data.get('sucesso'):
        print(f"Status: OK")
        print(f"Total programacoes: {data.get('total', 0)}")
        if data.get('programacoes'):
            prog = data['programacoes'][0]
            print(f"Primeira: {prog['label']}")
            print(f"  - ID: {prog['id']}")
            print(f"  - Linha: {prog['linha']}")
            print(f"  - Total Linhas: {prog['total_linhas']}")
            print(f"  - Producoes: {prog['producoes']}")
            print(f"  - Setups: {prog['setups']}")
            print(f"  - Qtd Total: {prog['quantidade_total']}")
    else:
        print(f"ERRO: {data.get('erro')}")
except Exception as e:
    print(f"ERRO: {e}")
    import traceback
    traceback.print_exc()

print('\n=== TESTE API 2: sequenciamento_gantt.php ===\n')
try:
    response = urllib.request.urlopen('http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=1')
    data = json.loads(response.read().decode())
    if data.get('sucesso'):
        print(f"Status: OK")
        print(f"Programacao: {data['programacao']['numero']} (Linha {data['programacao']['linha']})")
        print(f"Periodo: {data['periodo']['inicio']} a {data['periodo']['fim']}")
        print(f"Metricas:")
        m = data['metricas']
        print(f"  - Total Linhas: {m['total_linhas']}")
        print(f"  - Producoes: {m['producoes']}")
        print(f"  - Setups: {m['setups']}")
        print(f"  - Previsto: {m['total_previsto']:.2f}")
        print(f"  - Realizado: {m['total_realizado']:.2f}")
        print(f"  - Diferenca: {m['diferenca']:.2f}")
        print(f"  - Cumprimento: {m['percentual']:.1f}%")
        print(f"Timeline items: {len(data['timeline'])}")
        if data['timeline']:
            item = data['timeline'][0]
            print(f"\nPrimeiro item:")
            print(f"  - OP: {item['op']}")
            print(f"  - Nome: {item['nome']}")
            print(f"  - Status: {item['status']}")
            print(f"  - Qtd Prevista: {item['quantidade_prevista']}")
            print(f"  - Qtd Realizada: {item['quantidade_realizada']}")
    else:
        print(f"ERRO: {data.get('erro')}")
        import json
        print("DEBUG:", json.dumps(data, indent=2))
except Exception as e:
    print(f"ERRO: {e}")
    import traceback
    traceback.print_exc()

print('\n=== TESTE API 3: realizado_agrupado.php ===\n')
try:
    response = urllib.request.urlopen('http://localhost:8081/api/realizado_agrupado.php?programacao_id=1')
    data = json.loads(response.read().decode())
    if data.get('sucesso'):
        print(f"Status: OK")
        print(f"Programacao ID: {data['programacao_id']}")
        print(f"Periodo: {data['periodo']['inicio']} a {data['periodo']['fim']}")
        print(f"Total Realizado: {data['total_realizado']:.2f}")
        print(f"Registros: {data['registros']}")
        if data.get('realizado'):
            item = data['realizado'][0]
            print(f"\nPrimeiro item:")
            print(f"  - OP: {item['ordem_op']}")
            print(f"  - Quantidade: {item['quantidade']:.2f}")
    else:
        print(f"ERRO: {data.get('erro')}")
except Exception as e:
    print(f"ERRO: {e}")
    import traceback
    traceback.print_exc()
