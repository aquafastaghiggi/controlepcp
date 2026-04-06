# 📦 MIGRAÇÕES - INTEGRAÇÃO CODI

## 🚀 Como executar as migrações

### Opção 1: Via HTTP (Recomendado para desenvolvimento)

Abra no navegador:
```
http://localhost/controlepcp_sandbox/db/run_codi_migrations.php
```

Resposta esperada:
```json
{
  "status": "sucesso",
  "mensagem": "✅ Migrações executadas com sucesso! (42 statements)",
  "statements_executados": 42,
  "erros": [],
  "tabelas_verificadas": {
    "cdi_configuracao": {"existe": true, "colunas": 9},
    "cdi_eventos": {"existe": true, "colunas": 14},
    ...
  }
}
```

### Opção 2: Via MySQL Workbench ou CLI

```bash
mysql -u root -p controlepcp_sandbox < codi_migrations.sql
```

### Opção 3: Executar SQL manualmente

Copiar todo o conteúdo de `codi_migrations.sql` e executar no seu cliente MySQL.

---

## 📋 Arquivos nesta pasta

| Arquivo | Descrição |
|---------|-----------|
| `codi_migrations.sql` | Schema SQL completo (8 tabelas) |
| `run_codi_migrations.php` | Script PHP para executar migrations |
| `CODI_SCHEMA_DOCUMENTATION.md` | Documentação detalhada das tabelas |
| `README.md` | Este arquivo |

---

## ✅ Verificar se funcionou

Após executar as migrações:

```sql
-- Listar todas as tabelas CODI
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'controlepcp_sandbox' 
AND TABLE_NAME LIKE 'cdi_%';
```

Resultado esperado:
```
cdi_configuracao
cdi_eventos
cdi_performance
cdi_sincronizacao_log
cdi_sku_mapping
cdi_eficiencia_medicao
cdi_eficiencia_historico
cdi_resumo_diario
```

---

## 🔧 Próximas etapas

1. ✅ **Migrations criadas** (você está aqui)
2. ⏳ Criar `CodiClient.php` (conectar ao CODI)
3. ⏳ Criar `CodiSyncService.php` (sincronizar dados)
4. ⏳ Criar `EficienciaCalculator.php` (calcular eficiência)
5. ⏳ Criar endpoints de API (`/api/codi_sync.php`)

---

## 📚 Documentação

Para detalhes sobre cada tabela, ver:
👉 [CODI_SCHEMA_DOCUMENTATION.md](CODI_SCHEMA_DOCUMENTATION.md)

---

## ❓ Troubleshooting

### Erro: "Arquivo não encontrado"
- Verificar se você está no diretório correto
- Usar caminho absoluto se necessário

### Erro: "Permissão negada"
- Verificar se o usuário MySQL tem permissão de CREATE TABLE
- Usar usuário `root` ou admin

### Erro: "Tabela já existe"
- Normal! As declarações usam `IF NOT EXISTS`
- Repetir migrations é seguro (idempotente)

### View não aparece em Workbench
- View é criada com `CREATE OR REPLACE VIEW`
- Atualizar F5 se estiver em Workbench

---

## 💾 Backup antes de começar

Recomendação: Fazer backup do banco antes de rodar:

```bash
mysqldump -u root -p controlepcp_sandbox > backup_antes_migrations.sql
```

---

**Status**: ✅ Pronto para executar
**Executado em**: ---
**Próxima atualização**: Ver documentação para próximas fases
