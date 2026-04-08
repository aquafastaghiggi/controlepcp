#!/usr/bin/env python3
import urllib.request
import json

def test_api(url, name):
    print(f'\n=== TESTE: {name} ===')
    print(f'URL: {url}')
    try:
        req = urllib.request.Request(url)
        req.add_header('User-Agent', 'Mozilla/5.0')
        try:
            response = urllib.request.urlopen(req)
        except urllib.error.HTTPError as e:
            response = e
        
        content = response.read().decode('utf-8')
        print(f'Status: {response.status}')
        print(f'Response length: {len(content)}')
        print(f'Content:')
        print(content[:1000])
        
        try:
            data = json.loads(content)
            print('\nJSON Parsed:')
            print(json.dumps(data, indent=2)[:500])
        except:
            pass
    except Exception as e:
        print(f'ERROR: {e}')

# Testar APIs
test_api('http://localhost:8081/api/programacoes_historico.php', 'programacoes_historico.php')
test_api('http://localhost:8081/api/sequenciamento_gantt.php?programacao_id=1', 'sequenciamento_gantt.php')
test_api('http://localhost:8081/api/realizado_agrupado.php?programacao_id=1', 'realizado_agrupado.php')
