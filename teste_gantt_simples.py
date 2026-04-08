import urllib.request

print("Buscando tasksData na página...")

try:
    with urllib.request.urlopen('http://localhost:8081/gantt.php') as r:
        html = r.read().decode('utf-8')
        
        # Procurar pela string tasksData
        if 'tasksData' in html:
            print("✅ 'tasksData' encontrado")
            idx = html.find('tasksData')
            print(f"Contexto: ...{html[idx-50:idx+300]}...")
        else:
            print("❌ 'tasksData' não encontrado")
            
        # Procurar por quantidade_realizada
        if 'quantidade_realizada' in html:
            print("✅ 'quantidade_realizada' encontrado na página")
            idx = html.find('quantidade_realizada')
            print(f"Contexto: ...{html[idx-30:idx+100]}...")
        else:
            print("❌ 'quantidade_realizada' não encontrado")
            
except Exception as e:
    print(f"Erro: {e}")
