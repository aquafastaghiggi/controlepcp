# Prompt para Análise e Melhorias - Performance Card (Desempenho)

## Contexto do Projeto

Este é o projeto **controlepcp** - uma aplicação PHP para planejamento e controle de produção.

- **Repositório**: https://github.com/aquafastaghiggi/controlepcp
- **Workspace LOCAL**: `c:\xampp\htdocs\controlepcp_sandbox` (sandbox)
- **Ambientes**: 
  - SANDBOX: `controlepcp_sandbox/` (para testes e desenvolvimento)
  - PRODUÇÃO: `controlepcp/` (nunca tocar sem autorização explícita)

---

## REGRAS OBRIGATÓRIAS (Não negociáveis)

### 1. **Sandbox por Padrão**
- ✅ Todas as mudanças devem ser feitas **APENAS** em `controlepcp_sandbox`
- ❌ Nunca tocar em `controlepcp/` (produção) sem ordem explícita
- ❌ Não refletir mudanças sandbox em produção sem autorização

### 2. **Escopo Restrito**
- ✅ Alterar APENAS a parte solicitada (neste caso: card "Desempenho")
- ❌ Não mexer em outras seções, layouts ou funcionalidades
- ❌ Não refatorar código não pedido
- ❌ Não alterar cálculos ou importações de dados

### 3. **Encoding e Formatação**
- ⚠️ **CRÍTICO**: Projeto extremamente sensível a UTF-8/encoding
- ✅ Sempre salvar com UTF-8 BOM (byte order mark)
- ✅ Preservar acentuação corretamente (não criar mojibake)
- ✅ Cuidado ao copiar/colar entre arquivos - pode corromper encoding
- ❌ Não converter de encoding sem avisar

### 4. **JavaScript e Browser Compatibility**
- ⚠️ **CRÍTICO**: Código deve funcionar em navegadores antigos
- ✅ Usar `||` em vez de `??` (nullish coalescing não é suportado)
- ✅ Testar em navegadores com ES5/ES6 basic support
- ❌ Não usar sintaxe moderna sem fallback
- ❌ Não quebrar a app dinamicamente

### 5. **Versionamento**
- ✅ Fazer commit e push sempre que terminar uma etapa
- ✅ Mensagens descritivas no Git
- ✅ Trabalhar em branch `main` do sandbox
- ✅ Deixar arquivos tmp/test fora do commit

---

## O QUE FOI IMPLEMENTADO (Etapas 1-5 + FASE 0)

### Etapa 1: Consolidação de Layout
```css
#performance-timeline { display: flex !important; }
.performance-gantt-scroll { display: none; }
.performance-alt { display: none; }
.performance-daily { display: none; }
```
**Resultado**: Timeline (horizontal) agora é o layout **único ativo**

### Etapa 2: Espaçamento e Visibilidade
```css
.performance-gantt-scroll { min-height: 200px; } /* Removido max-height 380px */
.performance-timeline { padding: 20px; } /* Aumentado de 16px */
```
**Resultado**: Mais espaço vertical, melhor legibilidade

### Etapa 3: Grid Simplificado
```css
.performance-timeline-row { grid-template-columns: 120px 1fr 140px; }
.performance-timeline-header { grid-template-columns: 120px 1fr 140px; }
```
**Antes**: 240px + 1fr + 160px (muito espaço esquerda)
**Depois**: 120px + 1fr + 140px (mais equilibrado)

### Etapa 4: Tabela de Contexto
**HTML** (adicionado em `index.php`):
```html
<div class="performance-data-table-wrapper">
    <table class="performance-data-table">
        <thead>
            <tr>
                <th>Seq</th>
                <th>SKU</th>
                <th>Qtd</th>
                <th>Duração</th>
                <th>Data</th>
                <th>Início</th>
                <th>Fim</th>
            </tr>
        </thead>
        <tbody id="performance-data-table-body">
            <tr><td colspan="7" class="text-muted">Nenhum dado selecionado</td></tr>
        </tbody>
    </table>
</div>
```

**CSS** (adicionado em `app.css`):
```css
.performance-data-table {
    width: 100%;
    font-size: 13px;
    border-collapse: collapse;
    margin-bottom: 16px;
}
.performance-data-table thead {
    background: rgba(59, 130, 246, 0.08);
    font-weight: 600;
}
.performance-data-table th,
.performance-data-table td {
    padding: 8px 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    text-align: left;
}
.performance-data-table tbody tr:hover {
    background: rgba(59, 130, 246, 0.04);
}
```

### Etapa 5: Cores e Tipografia
```css
/* Setup - Laranja */
.performance-timeline-item.is-setup {
    background: linear-gradient(90deg, #EA580C, #D94600);
}

/* Produção - Azul */
.performance-timeline-item.is-prod {
    background: linear-gradient(90deg, #3B82F6, #2563EB);
}

/* Tipografia */
.performance-timeline-item {
    opacity: 0.95;
    font-size: 11px;
}
```

### FASE 0: Correção de Erros JavaScript
**Problema**: Operador `??` (nullish coalescing) não suportado em navegadores antigos
- **Localizado em**: `app.js` (linhas 277, 339) e `xlsx-import.js` (linhas 462-466, 643-644)
- **Solução**: Substituir `??` por `||`
```javascript
// Antes (erro)
const text = String(value ?? '');

// Depois (correto)
const text = String(value || '');
```

### Etapa 6: Formato de Tempo e Layout Compactado (31/03 - 06/04/2026)
**Commits**: 7 commits (abaixo)

#### 6.1 - Formato de Tempo
```javascript
// Antes: `1511h 29m` (difícil de ler)
// Depois: `1511:29` (HH:MM - mais compacto e profissional)
const formatHourMin = (ms) => {
  const totalMinutes = Math.round(ms / (1000 * 60));
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
};
```
**Afeta**: Cards PRODUÇÃO, SETUP, TOTAL, DISPONÍVEL, OCIOSIDADE

#### 6.2 - Compactação de Espaçamento (4 passes iterativas)
**Pass 1**: Redução inicial
```css
.performance-timeline { gap: 8px; }
.performance-timeline-summary { margin-top: -4px; }
.performance-timeline-legend-enhanced { margin-top: 0; }
```

**Pass 2**: Eliminação de gaps
```css
.performance-timeline { gap: 0; }
.performance-timeline-summary { margin-top: -8px; }
.performance-timeline-legend-enhanced { margin-top: -6px; }
```

**Pass 3**: Compactação de padding/rows
```css
.performance-timeline-row { padding: 4px 14px; margin-bottom: 2px; }
.performance-timeline-body { padding-top: 4px; }
.performance-timeline-summary { margin-top: -12px; padding: 14px 16px; }
.performance-timeline-legend-enhanced { margin-top: -10px; }
```

#### 6.3 - Ociosidade Card (Ocultação)
```css
.performance-timeline-summary .performance-timeline-summary-card:nth-child(5) {
    display: none;
}
```
**Resultado**: Card OCIOSIDADE removido da exibição

#### 6.4 - Proporção Corrigida de Barras (Timeline)
**Problema**: Setup e Produção mesma largura visual apesar de durações diferentes
**Solução**:
```javascript
// Remover extensão artificial que forçava mesmo tamanho
const renderEndMs = endMs; // Objeto: mantém duração real

// Aplicar tamanho mínimo para visibilidade (3%)
const MIN_BAR_WIDTH = 3;
if (itemWidthPct > 0 && itemWidthPct < MIN_BAR_WIDTH) {
  itemWidthPct = MIN_BAR_WIDTH;
}
// Anti-overflow
if (itemStartPct + itemWidthPct > 100) {
  itemStartPct = Math.max(0, 100 - itemWidthPct);
}
```
**Resultado**: 
- Setup (30min) = barra pequena
- Produção (90min) = barra maior (3x tamanho)
- Ambas visíveis mesmo em multi-dia

#### 6.5 - Sincronização PDF ↔ Timeline
**Problema**: PDF "Histórico de Programação" mostrava até 08/04, timeline até 07/04
**Solução**: Filtrar dados do PDF pelo mesmo período selecionado no timeline
```javascript
async function openHistoryPreview(prgId, filterDateKey = null) {
  // ... fetch data ...
  
  if (filterDateKey) {
    const filterDate = new Date(filterDateKey);
    const filterEndDate = new Date(filterDate);
    filterEndDate.setDate(filterEndDate.getDate() + 1);
    
    schedule = schedule.filter((item) => {
      // Include items that overlap with filter range
      return (startDate >= filterStart && startDate < filterEnd) || 
             (endDate >= filterStart && endDate < filterEnd) ||
             (startDate < filterStart && endDate >= filterEnd);
    });
  }
}
```
**Resultado**: PDF agora reflete período visível no gráfico

#### 6.6 - Correção de Overlay de Título
**Problema**: Negative margins extremas cortavam o título "TIPO DE OPERAÇÃO"
**Solução**: Restaurar margins adequados
```css
.performance-timeline-title { margin-bottom: 4px; }
.performance-timeline-summary { margin-top: 0px; }
.performance-timeline-legend-enhanced { margin-top: 4px; }
```

### Commits Etapa 6
1. **7be2675** - Fix: Change time summary format (XXXh YYm → HH:MM)
2. **367da7a** - Compact timeline layout: Remove unnecessary white space
3. **bb77094** - Further compact timeline layout: Maximize efficiency
4. **9d6fb0b** - Maximum compaction: Eliminate gaps and overlap elements
5. **9cc7151** - Extreme compaction: Reduce padding, minimize height
6. **5394259** - Fix: Adjust negative margins to prevent title overlay
7. **933a6d5** - Hide: Ociosidade card from performance summary
8. **62fa12a** - Fix: Add minimum bar width for visibility
9. **d0c76c9** - Fix: Synchronize PDF history with timeline

---

## Estrutura de Arquivos Relevantes

```
controlepcp_sandbox/
├── index.php                    # HTML principal (contém section-performance)
├── assets/
│   ├── css/
│   │   └── app.css              # Estilos (performance-*)
│   └── js/
│       ├── app.js               # Lógica principal (renderização timeline)
│       └── xlsx-import.js        # Import de dados
└── [outros arquivos...]
```

---

## O Que Precisa de Melhoria (Análise Aberta)

Você é convidado a **analisar e propor melhorias** para o card de desempenho considerando:

1. **Visual/UX**
   - Layout está bom? Há problemas de espaçamento ou alinhamento?
   - Cores estão legíveis (AC contrast)?
   - Tipografia adequada para o context?
   - Falta algo de usabilidade?

2. **Performance Técnica**
   - Timeline renderiza rápido com muitos dados?
   - Há vazamentos de memória no JS?
   - CSS é otimizado? Há redundâncias?

3. **Responsividade**
   - Funciona bem em mobile/tablet?
   - Grid 120px + 1fr + 140px é adequado para telas pequenas?

4. **Acessibilidade**
   - Cores contrastam bem (WCAG)?
   - Há labels/ARIA attributes adequados?
   - Navegação por teclado funciona?

5. **Código**
   - JavaScript tem melhorias possíveis (performance, legibilidade)?
   - CSS pode ser simplificado?
   - Há duplicações ou padrões repetidos?

---

## Instruções para Implementação

### Se você é uma **IA**:
1. Leia este prompt completamente
2. Analise os arquivos: `index.php`, `assets/css/app.css`, `assets/js/app.js`
3. Execute **apenas** em `controlepcp_sandbox`
4. Respeite as regras acima - especialmente encoding UTF-8 e compatibilidade JavaScript
5. Faça commits descritivos no Git
6. **Nunca** toque em `controlepcp/` (produção)
7. Quando terminar, detalhe as mudanças implementadas

### Se você é um **Dev**:
1. Clone ou abra o workspace local: `c:\xampp\htdocs\controlepcp_sandbox`
2. Crie uma branch se necessário: `git checkout -b feature/desempenho-melhorias`
3. Faça as alterações respeitando as regras
4. Teste em navegadores antigos (IE11, Edge antigo, etc.)
5. Verifique encoding UTF-8 antes de commit
6. Faça push: `git push origin feature/desempenho-melhorias`
7. Abra PR se aplicável

---

## Checklist antes de Entregar

- [ ] Sandbox modificado, produção intacta
- [ ] Encoding UTF-8 preservado (sem mojibake)
- [ ] JavaScript testado em navegadores antigos
- [ ] Sem `??`, sem async/await sem fallback
- [ ] Commits feitos e pushed
- [ ] Mudanças documentadas
- [ ] Escopo respeitado (apenas card desempenho)
- [ ] Mensagem de conclusão clara

---

## Contato/Dúvidas

Se tiver dúvidas sobre:
- **Estrutura do projeto**: Ver documentação em `docs/`
- **Regras de encoding**: Verificar histórico Git para exemplos de erro
- **Compatibilidade JS**: Testar sempre em navegadores com 2-3 anos atrás
- **Escopo**: Não hesite em perguntar antes de alterar código fora do desempenho

---

**Última atualização**: 6 de abril de 2026  
**Commit base**: `d0c76c9` (Etapas 1-6 concluídas - Timeline proporcional + PDF sincronizado)  
**Status**: ✅ Todos os testes de sandbox passando
