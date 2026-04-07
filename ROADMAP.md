# 🚀 ROADMAP - PRÓXIMAS PRIORIDADES

**Última atualização:** 07/04/2026 22:30  
**Commit:** `98df5ba` - Relatório Previsto vs Realizado Completo

---

## 🟡 ALTA PRIORIDADE (Fazer Amanhã)

### 1️⃣ Corrigir Gráficos para Filtro de Programação
**Estimativa:** 30-45 minutos  
**Status:** ⚠️ PENDENTE  
**Problema:** Quando usuário seleciona uma programação, os gráficos não recalculam os arrays

**Solução:**
1. Editar `api_programacoes.php`
2. Adicionar action `graph-data` que retorna arrays de gráficos calculados
3. Modificar função `recalcularGraficosPorPrograma()` em `previstorealizado.php`
4. Recalcular arrays usando querie SQL específica por programa

**Arquivos a modificar:**
```
api_programacoes.php (adicionar action 'graph-data')
previstorealizado.php (linhas 475-485)
```

**Teste:**
- [ ] Selecionar Prog #3 no dropdown
- [ ] Verificar se gráfico Donut atualiza (deve mostrar 0/0/0/31/0)
- [ ] Verificar se gráfico Performance reaplica (0-50%, 50-100%, 100%+)
- [ ] Verificar se gráfico Top 15 mostra apenas OPs da Prog #3

---

### 2️⃣ Remover / Ocultar Debug Panel em Sandbox
**Estimativa:** 5-10 minutos  
**Status:** ⚠️ PENDENTE  
**Detalhe:** O painel é útil para dev mas pode confundir usuários finais

**Opções:**
- Opção A: Remover completamente (controlar com variável de env `APP_ENV`)
- Opção B: Ocultar por padrão (usuário aperta botão para mostrar)
- Opção C: Mostrar apenas para usuário admin

**Arquivo a modificar:**
```
previstorealizado.php (linhas 575-595)
```

---

### 3️⃣ Testar em Browser Real
**Estimativa:** 15-20 minutos  
**Status:** ⚠️ PENDENTE  
**Navegadores a testar:**
- [ ] Chrome (recomendado)
- [ ] Firefox
- [ ] Edge
- [ ] Safari (se possível)

**Checklist:**
- [ ] Página carrega em < 2 segundos
- [ ] Gráficos renderizam corretamente
- [ ] Dropdown de programações funciona
- [ ] Filtros funcionam
- [ ] Busca funciona
- [ ] Paginação funciona
- [ ] Responsivo em mobile (se necessário)

---

## 🟠 MÉDIA PRIORIDADE (Fazer Esta Semana)

### 4️⃣ Adicionar Validação de Entrada na API
**Estimativa:** 20 minutos  
**Arquivo:** `api_programacoes.php`

```php
// Adicionar validação
$prg_id = (int)($_GET['prg_id'] ?? 0);
if ($prg_id <= 0) {
    throw new Exception('ID de programação inválido');
}
```

---

### 5️⃣ Implementar Cache de 1 Hora
**Estimativa:** 30 minutos  
**Detalhe:** Evitar queries repetidas ao banco para dados que não mudam frequentemente

**Implementação:**
```php
// Usar Redis ou arquivo de cache
$cache_key = "previsto_realizado_{$prg_id}";
$cache_time = 3600; // 1 hora
```

---

### 6️⃣ Adicionar Tooltips nos Gráficos
**Estimativa:** 15 minutos  
**Detalhe:** Mostrar valores ao passar mouse nos gráficos (ApexCharts já suporta)

```javascript
tooltip: {
    shared: true,
    intersect: false
}
```

---

## 🟢 BAIXA PRIORIDADE (Fazer Quando Houver Tempo)

### 7️⃣ Exportar Tabela como CSV
- [ ] Adicionar botão de download CSV
- [ ] Usar PHP para gerar arquivo temp
- [ ] Trigger download no browser

---

### 8️⃣ Adicionar Export PDF
- [ ] Considerar library como TCPDF ou mPDF
- [ ] Incluir gráficos no PDF
- [ ] Incluir resumo estatístico

---

### 9️⃣ Dashboard com Gráficos de Tendência
- [ ] Gráfico de linha: Total Realizado por Dia
- [ ] Gráfico de linha: Progressão de % (cumprimento)
- [ ] Heatmap: OPs por Status ao longo do tempo

---

## 📋 CHECKLIST PRA PASSAR PRA PRODUÇÃO

Antes de mover para producão controlpecp (não sandbox), garantir:

- [ ] Todos os gráficos funcionam com filtro
- [ ] Debug panel removido/oculto
- [ ] Performance aceitável (< 3s para carregar)
- [ ] Testes em todos os navegadores
- [ ] Dados reais testados (não apenas sandbox)
- [ ] API protegida contra injection SQL (use prepared statements - já feito ✅)
- [ ] Permissões de arquivo corretas
- [ ] Backups do banco realizados
- [ ] Documentação atualizada no README.md principal
- [ ] Usuários finais treinados

---

## 🎯 KPIs PARA MONITORAR

1. **Performance:** Tempo de carregamento < 2s
2. **Acurácia:** Somas de Previsto/Realizado batem com base de dados
3. **Adoção:** Usuários usando filtro de programação
4. **Bugs:** Nenhum erro em 7 dias após lançamento

---

## 📞 CONTATO PRÓXIMO DEV

Se encontrar problemas:

1. **Verificar DOCUMENTATION.md** - Tem respostas para dúvidas comuns
2. **Executar validate_all_etapas.php** - Testa integridade dos dados
3. **Ver DEBUG panel** - Mostra logs de cada ação
4. **Verificar logs:** `c:\xampp\tmp\previstorealizado_debug.log`

---

**Próximo passo recomendado:** Começar por #1 (Gráficos) pois é blocker visual 🎨
