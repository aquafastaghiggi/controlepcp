#!/usr/bin/env python3
import urllib.request
import json

url = 'http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=1'
response = urllib.request.urlopen(url)
data = json.loads(response.read().decode())

if data.get('sucesso'):
    print('API Status: OK')
    timeline = data['timeline']
    print(f'Timeline items: {len(timeline)}')
    
    print('\nPrimeiros 10 itens (com horas):')
    for i, item in enumerate(timeline[:10]):
        op = item['op']
        nome = item['nome'][:30]
        tipo = item['tipo']
        start = item['start']
        end = item['end']
        status = item['status']
        qtd_prev = item['quantidade_prevista']
        qtd_real = item['quantidade_realizada']
        
        print(f'{i+1}. OP {op} - {tipo}')
        print(f'   Nome: {nome}')
        print(f'   Timeline: {start}h a {end}h (duracao: {round(end-start,1)}h)')
        print(f'   Status: {status}')
        print(f'   Qtd: Prev={qtd_prev} | Real={qtd_real}')
        print()
else:
    print(f'ERRO: {data.get("erro")}')
