# 🚀 CODI Integration - Quick Start Guide

## ✅ Projeto 100% Completo

Todas as **7 fases** foram implementadas, testadas e documentadas. O sistema está **pronto para produção**.

---

## 📡 Acessar os Componentes

### 1. 📊 Dashboard (Principal)
```
http://localhost/controlepcp_sandbox/src/Codi/dashboard_fase6.php
```
**O que você vê**:
- 5 abas de visualização (Resumo, Eficiência, Críticos, Recursos, Tendência)
- Filtros dinâmicos
- Estatísticas em tempo real
- Tabelas interativas
- Status de cada programação: OK, Aviso ou Crítico

---

### 2. 🔌 API REST (Para Integração)
```
http://localhost/controlepcp_sandbox/api/codi_eficiencia.php?action=listar
```

**Endpoints Disponíveis**:
```bash
# 1. Listar últimos 7 dias
GET ?action=listar&periodo=7

# 2. Detalhe de uma medição
GET ?action=detalhe&id=1

# 3. Apenas críticos
GET ?action=criticos&dias=7

# 4. Resumo agregado
GET ?action=resumo&data_inicio=2026-04-01&data_fim=2026-04-06

# 5. Por recurso/máquina
GET ?action=por_recurso&recurso_id=1

# 6. Tendência histórica
GET ?action=tendencia&dias=30

# 7. Filtrar por status
GET ?action=filtrar&status=critico

# 8. Exportar em CSV
GET ?action=exportar&formato=csv
```

**Testar API**:
```
http://localhost/controlepcp_sandbox/src/Codi/teste_api_fase5.php
```

---

### 3. 🧪 Testes Automatizados
```bash
# Rodar testes no terminal
php c:\xampp\htdocs\controlepcp_sandbox\src\Codi\teste_fase7.php

# Ou via navegador (mostrar HTML)
http://localhost/controlepcp_sandbox/src/Codi/teste_fase7.php
```

**O que é testado**:
- ✅ Conexão com BD (10 testes)
- ✅ CodiClient HTTP (8 testes)
- ✅ CodiSyncService (7 testes)
- ✅ EficienciaCalculator (6 testes)
- ✅ API REST (4 testes)

---

## 🔧 Como Funciona (Fluxo)

```
STEP 1: CODI Hardware
   └─→ Eventos & Performance via API REST

STEP 2: CodiClient (FASE 2)
   └─→ Autentica e baixa dados

STEP 3: CodiSyncService (FASE 3)
   └─→ Transforma e sincroniza para BD

STEP 4: EficienciaCalculator (FASE 4)
   └─→ Cruza previsto vs realizado
   └─→ Calcula KPIs (OEE, Performance, etc)
   └─→ Determina status (OK, Aviso, Crítico)
   └─→ Salva em cdi_eficiencia_medicao

STEP 5: API REST (FASE 5)
   └─→ Expõe dados com 8 endpoints

STEP 6: Dashboard (FASE 6)
   └─→ Visualiza em tempo real

STEP 7: Testes (FASE 7)
   └─→ Valida todo o sistema
```

---

## 📚 Documentação

### Documentação Técnica Completa
```
c:\xampp\htdocs\controlepcp_sandbox\
├── RESUMO_FINAL_CODI_INTEGRATION.md      ← Visão geral completa
├── src/Codi/DOCUMENTACAO_FASE1.md        ← Database Schema
├── src/Codi/DOCUMENTACAO_FASE2.md        ← HTTP Client
├── src/Codi/DOCUMENTACAO_FASE3.md        ← Sync Service
├── src/Codi/DOCUMENTACAO_FASE4.md        ← Eficiência Calculator
└── src/Codi/DOCUMENTACAO_FASES_5_6_7.md  ← API, Dashboard, Testes
```

### README de Cada Fase
```
├── src/Codi/README_FASE1.md
├── src/Codi/README_FASE2.md
├── src/Codi/README_FASE3.md
├── src/Codi/README_FASE4.md
```

---

## 💡 Exemplos de Código

### Exemplo 1: Sincronizar Dados
```php
use Codi\CodiSyncService;
use Src\Database\Connection;

$db = Connection::getInstance();
$sync = new CodiSyncService($db);

// Sincronizar tudo: eventos + performance
$resultado = $sync->syncAll();
echo "Sincronizados: " . $resultado['total_sincronizado'] . " registros\n";
```

### Exemplo 2: Calcular Eficiência
```php
use Codi\EficienciaCalculator;

$calc = new EficienciaCalculator($db);

$resultado = $calc->calcularEficienciaCompleta(
    '2026-04-01',
    '2026-04-06',
    [
        'eficiencia_critica' => 70,
        'oee_critica' => 50
    ]
);

echo "Processadas: " . $resultado['periodosProcessados'] . " programações\n";
echo "Críticas: " . count(array_filter($resultado['detalhes'], 
    fn($d) => $d['status']['geral'] === 'critico'
)) . "\n";
```

### Exemplo 3: Consultar via API
```javascript
// No navegador ou Node.js
fetch('http://localhost/controlepcp_sandbox/api/codi_eficiencia.php?action=resumo')
    .then(r => r.json())
    .then(data => {
        console.log('OEE Médio:', data.dados.resumo.oee_media);
        console.log('Críticos:', data.dados.resumo.total_criticos);
    });
```

---

## 📊 Indicadores Monitorados

| Indicador | O que é | Importância |
|-----------|---------|-------------|
| **Eficiência** | % Quantidade produzida vs programada | 🔴 Crítica |
| **Performance** | Velocidade vs padrão técnico | 🟡 Alta |
| **Disponibilidade** | % Tempo máquina ativa | 🟡 Alta |
| **OEE** | Overall Equipment Effectiveness | 🔴 Crítica |
| **Produtividade** | Peças/hora | 🟢 Informativa |

---

## ⚠️ Alertas & Status

### Status OK (Verde) ✅
- Eficiência ≥ 85%
- OEE ≥ 75%
- Sem atrasos

### Status Aviso (Amarelo) ⚠️
- Eficiência 70-84%
- OEE 50-74%
- 2-4 dias de atraso

### Status Crítico (Vermelho) 🔴
- Eficiência < 70%
- OEE < 50%
- 5+ dias de atraso

**Ação**: Revisar programação e avaliar possíveis gargalos

---

## 🔌 Integração CODI

### Configuração do Servidor CODI
```php
// Em src/Codi/config.php
[
    'baseUrl' => 'http://192.168.8.123:8081',  ← Seu servidor
    'username' => 'admin',                       ← Seu user
    'password' => 'senha123',                    ← Sua senha
    'companId' => 'matriz',                      ← Seu código
    'maxRetries' => 3,
    'retryDelayMs' => 1000,
    'timeout' => 30
]
```

---

## ✨ Recursos Principais

✅ **Síncrono**: Dados sempre atualizados a cada Pull  
✅ **Automático**: Cálculos acontecem sem intervenção  
✅ **Inteligente**: Status e alertas automáticos  
✅ **Escalável**: Suporta múltiplas máquinas/períodos  
✅ **Seguro**: PDO + Basic Auth + Logging  
✅ **Rápido**: Batch processing + índices otimizados  
✅ **Testado**: 35+ testes de cobertura total  

---

## 🎓 Estrutura de Pastas

```
controlepcp_sandbox/
│
├── api/
│   └── codi_eficiencia.php              ← API REST (FASE 5)
│
├── db/
│   ├── codi_migrations.sql              ← Schema (FASE 1)
│   ├── run_codi_migrations.php
│   └── visualizar_migrations.php
│
├── src/Codi/
│   ├── CodiClient.php                   ← HTTP Client (FASE 2)
│   ├── CodiSyncService.php              ← Sync Service (FASE 3)
│   ├── EficienciaCalculator.php         ← Calculadora (FASE 4)
│   ├── config.php
│   │
│   ├── dashboard_fase6.php              ← Dashboard (FASE 6)
│   ├── teste_api_fase5.php              ← Testes API (FASE 5)
│   ├── teste_fase7.php                  ← Test Suite (FASE 7)
│   │
│   ├── exemplo_eficiencia.php           ← Exemplos
│   ├── eficiencia_dashboard.php
│   │
│   ├── DOCUMENTACAO_FASE*.md            ← Documentação
│   ├── README_FASE*.md
│   └── DOCUMENTACAO_FASES_5_6_7.md
│
└── RESUMO_FINAL_CODI_INTEGRATION.md     ← Este arquivo
```

---

## 🚦 Checklist de Produção

Antes de colocar em produção, verifique:

- [ ] URL do servidor CODI configurada corretamente
- [ ] Credenciais CODI testadas e funcionando
- [ ] Banco de dados sincronizado pelo menos uma vez
- [ ] Primeiros cálculos de eficiência executados
- [ ] Dashboard acessível e mostrando dados
- [ ] Testes passando (35+)
- [ ] Backups do BD configurados
- [ ] Logs sendo armazenados
- [ ] Alertas de críticos funcionando
- [ ] Equipe treinada

---

## 📞 Troubleshooting

### "Erro ao conectar ao CODI"
1. Verificar URL: `http://192.168.8.123:8081`
2. Verificar credenciais em `src/Codi/config.php`
3. Verificar firewall/rede
4. Testar com `CodiClient::testConnection()`

### "BD vazia, sem dados"
1. Verificar se `cdi_migrations.sql` foi executado
2. Rodar sincronização: `$sync->syncAll()`
3. Verificar logs em `cdi_sincronizacao_log`

### "Dashboard sem dados"
1. Confirmar dados em BD: `SELECT * FROM cdi_eficiencia_medicao`
2. Verificar cálculo: `calcularEficienciaCompleta()`
3. Verificar filtros e período

### "Testes falhando"
1. Rodar suite: `php teste_fase7.php`
2. Verificar erros específicos
3. Confirmar tabelas existem: `SHOW TABLES LIKE 'cdi_%'`

---

## 📈 Próximas Melhorias (Optional)

- [ ] WebSocket para updates real-time
- [ ] Mobile app (PWA)
- [ ] Alertas por email/SMS
- [ ] Previsão com ML
- [ ] Integração Grafana/Prometheus
- [ ] Multi-tenant

---

## 🎉 Conclusão

Seu sistema **CODI Integration** está **100% operacional** e **pronto para produção**.

Para dúvidas, consulte a documentação técnica ou rode os exemplos.

---

**Desenvolvido**: 6 de Abril de 2026  
**Status**: ✅ Pronto para Produção  
**Suporte**: Documentação Completa + 35+ Testes
