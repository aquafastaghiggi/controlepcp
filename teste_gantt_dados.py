import urllib.request
import json
import re

print("Testando página gantt.php dengan dados realizado...")

try:
    with urllib.request.urlopen('http://localhost:8081/gantt.php') as r:
        html = r.read().decode('utf-8')
        
        # Procurar pelos dados JSON da tarefa
        match = re.search(r'var tasksData = \{.*?"data":\s*(\[.*?\])', html, re.DOTALL)
        if match:
            try:
                tasks_json = match.group(1)
                # Truncar para primeiros 500 chars para não ficar muito grande
                print("✅ Dados de tarefa encontrados!")
                print(f"Primeiros 300 chars: {tasks_json[:300]}...")
                
                # Procurar por quantidade_realizada
                if 'quantidade_realizada' in tasks_json:
                    print("✅ Campo 'quantidade_realizada' encontrado no JSON!")
                else:
                    print("❌ Campo 'quantidade_realizada' NÃO encontrado")
                    
                if 'percentual_cumprimento' in tasks_json:
                    print("✅ Campo 'percentual_cumprimento' encontrado no JSON!")
                else:
                    print("❌ Campo 'percentual_cumprimento' NÃO encontrado")
                    
            except Exception as e:
                print(f"Erro ao parsear JSON: {e}")
        else:
            print("❌ Dados de tarefa não encontrados na página")
            
        # Procurar pela coluna "realizado"
        if 'quantidade_realizado' in html or '"realizado"' in html:
            print("✅ Coluna 'realizado' encontrada na configuração gantt")
        else:
            print("⚠️  Coluna 'realizado' não encontrada")
            
except Exception as e:
    print(f"Erro: {e}")
