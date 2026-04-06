# 🔍 Guia de Debug - Sequenciamento API

## Quick Test URLs

### 1. **Teste PING** (verificar se PHP está respondendo)
```
http://192.168.8.123:8081/api/ping.php
```
**Esperado:** Status 200, JSON com PHP version
```json
{
  "status": "ok",
  "php_version": "8.2.12"
}
```

### 2. **Teste BANCO DE DADOS** (verificar conexão)
```
http://192.168.8.123:8081/api/test_db.php
```
**Esperado:** Status 200, mostra contagem de registros
```json
{
  "sucesso": true,
  "conectado": true,
  "banco": {
    "prg_programas": 15,
    "sch_linhas": 340
  }
}
```

### 3. **Teste STATUS** (verificar API)
```
http://192.168.8.123:8081/api/sequenciamento.php?action=status
```
**Esperado:** Status 200, confirmação que API está online
```json
{
  "sucesso": true,
  "status": "API Online",
  "timestamp": "2025-07-07 14:30:45"
}
```

### 4. **LISTAR programações** (buscar dados reais)
```
http://192.168.8.123:8081/api/sequenciamento.php?action=listar&limit=50
```
**Esperado:** Status 200, array de programações
```json
{
  "sucesso": true,
  "data": [...]
}
```

---

## Passos para Debug

### **Passo 1: Abrir Browser Console**
1. Acesse `http://192.168.8.123:8081/sequenciamento.php`
2. Abra DevTools: **F12** (ou Ctrl+Shift+I)
3. Vá para a aba **Console**

### **Passo 2: Observar mensagens**
Você verá mensagens com prefixos:

- ✅ Verde - sucesso
- ❌ Vermelho - erro
- 🧪 Azul - teste
- ⚠️ Amarelo - aviso
- 📊 Laranja - dados
- 📡 Roxo - rede
- 🔴 Vermelho escuro - erro crítico

### **Passo 3: Executar teste progressivo**

```javascript
// No console do browser, execute:

// 1. Ver URL da API calculada
console.log('API Base:', apiBase);

// 2. Teste PING
fetch('/api/ping.php').then(r => r.json()).then(j => console.log('PING OK:', j));

// 3. Teste DB
fetch('/api/test_db.php').then(r => r.json()).then(j => console.log('DB OK:', j));

// 4. Teste STATUS
fetch('/api/sequenciamento.php?action=status').then(r => r.json()).then(j => console.log('STATUS OK:', j));

// 5. Teste LISTAR (o que mais importa)
fetch('/api/sequenciamento.php?action=listar').then(r => {
  console.log('Status:', r.status);
  return r.json();
}).then(j => {
  console.log('Dados:', j);
  if (j.data) console.log('Total:', j.data.length, 'programações');
}).catch(e => console.error('ERRO:', e));
```

---

## Possíveis Problemas

### ❌ **Status 404**
- API folder não acessível
- **Solução:** Verificar se `/api/` existe em `c:\xampp\htdocs\controlepcp_sandbox\api\`

### ❌ **Status 500 em test_db.php**
- `bootstrap.php` não encontrado ou erro de conexão
- **Solução:** Verificar `src/bootstrap.php` existe

### ❌ **Status 500 em sequenciamento.php?action=listar**
- Erro em `ProgramacaoRepository` ou query
- **Revisar FileErrorLog:**
```
c:\xampp\apache\logs\error.log
// Procurar por "🔴" ou "Sequenciamento API"
```

### ❌ **Dados vazios (status 200 mas `data: []`)**
- Nenhum registro em `sch_linhas`
- **Solução:** Executar `criar_teste.php` para gerar dados de teste

### ❌ **CORS error no console**
- Menos provável (mesmo servidor)
- **Se ocorrer:** Adicionar headers CORS em `sequenciamento.php` linha 6

---

## Log do Servidor

Para ver logs detalhados do servidor:

```powershell
# Abrir arquivo log
type c:\xampp\apache\logs\error.log | Select-Object -Last 50

# Ou em tempo real (Windows):
Get-Content c:\xampp\apache\logs\error.log -Wait
```

Procurar por:
- 🔵 `handleListar()` - início da função
- 🟡 `Parâmetros:` - valores recebidos
- 🟡 `Obtendo conexão PDO` - tentativa de conexão
- ✅ `PDO obtido com sucesso` - conexão OK
- ❌ Qualquer mensagem com 🔴 ou "Error"

---

## Próximas ações baseado em resultado:

**Se PING OK:**
> PHP está funcionando, continue...

**Se DB OK:**
> Banco + dados existem, continue...

**Se STATUS OK:**
> API está respondendo, continue...

**Se LISTAR OK:**
> 🎉 Gantt chart deve carregar!

**Se LISTAR com erro:**
> Erro em `handleListar()` - verificar logs do servidor acima
