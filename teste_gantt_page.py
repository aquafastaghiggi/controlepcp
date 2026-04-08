import urllib.request

print("Testando página gantt.php...")

try:
    with urllib.request.urlopen('http://localhost:8081/gantt.php') as r:
        html = r.read().decode('utf-8')
        
        print(f"✅ Página carregada: {len(html)} bytes")
        
        # Procurar elementos importantes
        elements = {
            'timelineChart': 'timelineChart' in html,
            'sequenciamento': 'sequenciamento' in html,
            'programacaoSelect': 'programacaoSelect' in html,
            'messageContainer': 'messagesContainer' in html
        }
        
        for key, val in elements.items():
            status = "✅" if val else "❌"
            print(f"  {status} {key}")
        
        # Procurar erros
        errors = ['Parse error', 'Fatal error', 'Syntax error']
        has_error = False
        for err in errors:
            if err.lower() in html.lower():
                print(f"\n⚠️  Encontrado: {err}")
                idx = html.lower().find(err.lower())
                print(html[max(0, idx-50):idx+200])
                has_error = True
        
        if not has_error:
            print("\n✅ Nenhum erro PHP encontrado")
            
except Exception as e:
    print(f"❌ Erro ao acessar: {e}")
