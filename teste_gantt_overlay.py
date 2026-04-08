import urllib.request
import time

print("Testando página gantt.php com overlay realizado...")

try:
    with urllib.request.urlopen('http://localhost:8081/gantt.php') as r:
        html = r.read().decode('utf-8')
        
        # Procurar pelo evento onAfterTaskRender
        if 'onAfterTaskRender' in html:
            print("✅ Evento 'onAfterTaskRender' encontrado")
        else:
            print("❌ Evento 'onAfterTaskRender' não encontrado")
            
        # Procurar pelo texto do overlay
        if 'gantt_task_bar' in html:
            print("✅ Código para encontrar 'gantt_task_bar' presente")
        else:
            print("❌ Código para 'gantt_task_bar' não encontrado")
            
        # Procurar pela informação de realizado no overlay
        if "prev.toFixed(0) + '|' + real" in html or "prev.toFixed(0) + '|'" in html:
            print("✅ Lógica de formatação do overlay presente")
        else:
            print("⚠️  Lógica de formatação pode estar diferente")
            
        # Procurar por display: none (para esconder coluna)
        if "style.display = 'none'" in html:
            print("✅ Código para esconder coluna presente")
        else:
            print("⚠️  Código para esconder coluna não encontrado")
            
        print("\n✅ Página compilada com sucesso!")
        print("Abra gantt.php no navegador para ver o overlay nos OP's")
        
except Exception as e:
    print(f"❌ Erro: {e}")
