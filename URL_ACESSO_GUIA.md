# URLs de Acesso - Sandbox vs Produção

## Sandbox (Teste) - PORTA 8081
- **Base URL:** `http://localhost:8081/`
- **Gantt Chart:** `http://localhost:8081/gantt.php`
- **API Syncronizar:** `http://localhost:8081/api/sync_codi.php`
- **Banco:** controlepcp_sandbox
- **Usuário DB:** controlepcp_sbx
- **Ambiente:** APP_ENV=sandbox

### Exemplo de Acesso (PowerShell)
```powershell
# Testar sync sem forçar:
curl.exe http://localhost:8081/api/sync_codi.php `
  -Method POST `
  -Headers @{"Content-Type"="application/json"} `
  -Body '{"action":"sync_yesterday"}'

# Testar sync com força:
curl.exe http://localhost:8081/api/sync_codi.php `
  -Method POST `
  -Headers @{"Content-Type"="application/json"} `
  -Body '{"action":"sync_yesterday","force":true}'
```

### Exemplo de Acesso (Browser)
```
1. Abrir: http://localhost:8081/
2. Fazer login na sandbox
3. Ir para: http://localhost:8081/gantt.php
4. Clicar em "Sincronizar CODI"
5. Aguardar resposta (pode levar alguns minutos)
```

---

## Produção (PORTA 80)
- **Base URL:** `http://localhost/controlepcp/`
- **Gantt Chart:** `http://localhost/controlepcp/gantt.php`
- **Banco:** controlepcp
- **Usuário DB:** pcp_app
- **Ambiente:** Produção

---

## Estrutura no FileSystem

```
C:\xampp\htdocs\
├── controlepcp/               # Produção (porta 80)
│   ├── api/
│   ├── gantt.php
│   └── ...
│
└── controlepcp_sandbox/       # Sandbox (porta 8081)
    ├── api/
    │   ├── sync_codi.php     # ✅ CORRIGIDO - Usa venv Python
    │   └── ...
    ├── .venv/
    │   └── Scripts/python.exe
    ├── gantt.php
    ├── sync_codi_yesterday.py  # Script que executa
    └── ...
```

---

## Verificação Atual

**Último Test (2026-04-08):**
```json
GET http://localhost:8081/api/sync_codi.php (POST)
Status: 200 OK
Response: {"success":false,"message":"Já foi sincronizado hoje (1666 registros inseridos)...","alreadySynced":true}
```

✅ **API Funcionando Corretamente**
