# ⚡ QUICK START - PARA PRÓXIMO DEV

**Tempo para setup:** ~5 minutos  
**Pré-requisito:** PHP, MySQL, Git já rodando

---

## 🚀 Comece Aqui

### 1️⃣ Entender o Projeto (2 min)

```bash
# Leia PRIMEIRO isso:
cat DOCUMENTATION.md         # O QUÊ foi feito
cat ROADMAP.md              # O QUÊ falta fazer
```

### 2️⃣ Executar Validação (1 min)

```bash
# Validar que os dados estão corretos:
php validate_all_etapas.php

# Output esperado:
# ✅ ETAPA 3: VALIDAÇÃO CONCLUÍDA COM SUCESSO
# ✅ ETAPA 4: GRÁFICOS VALIDADOS
# ✅ ETAPA 5: TABELA VALIDADA
# ✅ ETAPA 6: TODOS OS TESTES PASSARAM
```

### 3️⃣ Acessar a Página (1 min)

```
URL: http://192.168.8.123:8081/previstorealizado.php

Você deve ver:
- ✅ Cards de resumo (Planejado, Realizado, etc)
- ✅ 3 gráficos (Donut, Bar, Bar)
- ✅ 5 cards de status (Cumprida, Excedida, etc)
- ✅ Tabela com OPs
- ✅ Dropdown de programações
- ✅ Debug panel no canto (canto inferior direito)
```

### 4️⃣ Onde Estão os Problemas? (1 min)

Se algo não está funcionando:

**Gráficos não mudam ao selecionar programação?**
```
→ Ver ROADMAP.md "Alta Prioridade #1"
→ Editar: previstorealizado.php + api_programacoes.php
→ Adicionar recalculate de arrays
```

**Debug panel interferindo?**
```
→ Ver ROADMAP.md "Alta Prioridade #2"
→ Remover ou ocultar: previstorealizado.php linhas 575-595
```

**Performance lenta?**
```
→ Executar: validate_all_etapas.php
→ Deve mostrar "Performance: 2.22ms"
→ Se > 5ms, adicionar cache
```

**Erro 404 ou SQL?**
```
→ Verificar logs: c:\xampp\tmp\previstorealizado_debug.log
→ Ver painel DEBUG na página
→ Executar: test_api.php para diagnóstico
```

---

## 🔧 Principais Arquivos (Ordem de Importância)

| Arquivo | Linhas | Propósito | Editar? |
|---------|--------|----------|--------|
| `previstorealizado.php` | 600+ | Interface principal + JS | Às vezes |
| `api_programacoes.php` | 80 | API de programações | Sim (High Priority #1) |
| `validate_all_etapas.php` | 300+ | Validação de dados | Não (ler apenas) |
| `DOCUMENTATION.md` | 400+ | Documentação técnica | Se houver mudanças |
| `ROADMAP.md` | 150+ | Próximas tarefas | Atualizar conforme fizer |

---

## 📊 Dados de Teste

**Como reimportar dados do Excel?**
```bash
php import_excel_to_db.php
```

**Resultado esperado:**
```
✓ Excel carregado: 29728 linhas
✓ Filtradas (Mar-Abr 2026): 12875 linhas
✓ Registros únicos: 4448 itens
✓ Inseridos/atualizados: 4448 registros
✓ Total na tabela: 425 registros
✓ Total de OPs: 311
✓ Quantidade total: 378.610,95
```

---

## 🧪 Testar Mudanças

Depois de fazer uma mudança:

```bash
# 1. Validar sintaxe
php -l previstorealizado.php
php -l api_programacoes.php

# 2. Validar dados
php validate_all_etapas.php

# 3. Testar no navegador
# Visitarhttp://192.168.8.123:8081/previstorealizado.php

# 4. Fazer commit
git add .
git commit -m "describe: O que você mudou"
```

---

## 📍 Estrutura do BD

```
TABELAS ÚTEIS:
- prg_programas        → Programações de linha (9 registros)
- prg_itens            → Items planejados (130 registros)
- realizado_2026_excel → Dados realizados importados (425 registros)
- sch_linhas           → Linhas de produção (245 linhas)

QUERIES MAIS USADAS:

# Previsto por programa
SELECT SUM(prg_quantidade) as total
FROM prg_itens
WHERE prg_programa_id = ?

# Realizado por programa
SELECT SUM(quantidade) as total
FROM realizado_2026_excel
WHERE ordem_op = ?

# OPs do programa
SELECT COUNT(DISTINCT prg_itens_op) as ops_count
FROM prg_itens
WHERE prg_programa_id = ?
```

---

## 🎓 Próximas Tarefas em Ordem

1. **HOJE (Alta Prioridade):**
   - [ ] Corrigir gráficos para filtro programação (ROADMAP #1)
   - [ ] Testar em browser real (ROADMAP #3)
   - [ ] Remover debug panel (ROADMAP #2)

2. **ESTA SEMANA (Média Prioridade):**
   - [ ] Validação de entrada na API (ROADMAP #4)
   - [ ] Implementar cache (ROADMAP #5)
   - [ ] Adicionar tooltips (ROADMAP #6)

3. **PRÓXIMAS SEMANAS:**
   - [ ] Export CSV/PDF
   - [ ] Gráficos de tendência
   - [ ] Testes com dados reais

---

## 🚨 ERROS COMUNS

**Erro: "Unknown column 'sl.sch_linhas_descricao'"**
→ Você usou a linha antiga. A coluna correta é `prg_numero_op`

**Erro: "CORS error"**
→ API está em outro domínio? Configure CORS headers em `api_programacoes.php`

**Gráficos em branco**
→ Dados JSON inválidos. Ver painel DEBUG > verificar response da API

**Tabela vazia**
→ Programação selecionada não tem OPs? Mostrar mensagem "Sem dados"

---

## 📞 Dúvidas Frequentes

**P: Como debugar sem painel?**
R: Usar DevTools do navegador (F12) → Console/Network

**P: Como testar API diretamente?**
R: `curl "http://localhost/controlepcp_sandbox/api_programacoes.php?action=programacoes"`

**P: Posso deletar arquivos test_* ?**
R: Sim, são apenas para debug. Mas mantenha validate_all_etapas.php

**P: É seguro ir pra produção?**
R: Sim, se todos items do CHECKLIST no DOCUMENTATION.md estiverem ✅

---

## ✅ Final Checklist Antes de Parar

- [ ] Executou `validate_all_etapas.php` e passou?
- [ ] Acesso a página e viu todo content?
- [ ] Gráficos renderizam?
- [ ] Dropdown carrega programações?
- [ ] Fez git commit das mudanças?
- [ ] Atualizou ROADMAP.md com próximas tarefas?

---

**Qualquer dúvida, leie a DOCUMENTATION.md completa!**

Boa sorte 🚀
