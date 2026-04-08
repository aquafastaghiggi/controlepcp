import requests
import json

url = 'http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=1'
response = requests.get(url)
data = response.json()

# Mostrar se trouxe dados
print(f'Status: {data["sucesso"]}')
print(f'Total timeline: {len(data["timeline"])}')

# Mostrar primeiro item completo
if data['timeline']:
    item = data['timeline'][0]
    print(f'\nPrimeiro item:')
    print(f'  OP: {item["op"]}')
    print(f'  Previsto: {item["quantidade_prevista"]}')
    print(f'  Realizado: {item["quantidade_realizada"]}')
    print(f'  Cumprimento: {item["percentual_cumprimento"]}%')
    print(f'  Data: {item["data"]}')

# Mostrar métricas
m = data['metricas']
print(f'\nMétricas:')
print(f'  Total previsto: {m["total_previsto"]}')
print(f'  Total realizado: {m["total_realizado"]}')
print(f'  Percentual: {m["percentual"]}%')

# Mostrar alguns itens com realizado
print(f'\n\nItens com REALIZADO > 0:')
count = 0
for item in data['timeline']:
    if item['quantidade_realizada'] > 0:
        count += 1
        if count <= 5:
            print(f'  OP {item["op"]} ({item["data"]}): Prev={item["quantidade_prevista"]}, Real={item["quantidade_realizada"]}')

print(f'Total com realizado: {count} de {len(data["timeline"])}')
