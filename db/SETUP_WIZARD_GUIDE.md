# 🚀 Setup Wizard - Guia Prático

## O que é?

**Setup Wizard** é uma interface visual interativa que guia você passo-a-passo através de todo o processo de inicialização da integração CODI.

Ele automatiza:
- ✅ Verificação de pré-requisitos
- ✅ Execução de migrations SQL
- ✅ Validação de tabelas
- ✅ Teste de conexão com CODI
- ✅ Configuração de credenciais

---

## 🎯 Como Usar

### Passo 1: Abrir o Wizard

1. Copie a URL abaixo e abra no navegador:
```
http://localhost/controlepcp_sandbox/db/setup_wizard.html
```

2. Um formulário visual aparecerá

### Passo 2: Seguir os Passos

O wizard possui 6 passos automáticos:

#### **Passo 1: Verificar Pré-requisitos**
- ✓ Verifica PHP extensions (PDO, cURL)
- ✓ Verifica conexão com BD
- ✓ Verifica arquivo de migrations

**O que você vê:**
- Checklist com status de cada item
- Verde (✓) = OK
- Vermelho (✕) = Erro
- Amarelo (⚠) = Aviso

**Ação:** Clique "Próximo →"

---

#### **Passo 2: Executar Migrations**
- Lê o arquivo `codi_migrations.sql`
- Executa cada comando SQL
- Cria as 8 tabelas no banco

**O que você vê:**
- Quantidade de statements executados
- Erros (se houver)
- Status final (sucesso ou parcial)

**Ação:** Clique "Próximo →"

---

#### **Passo 3: Verificar Tabelas**
- Consulta cada uma das 8 tabelas
- Conta as colunas
- Valida integridade

**O que você vê:**
```
- cdi_configuracao         ✓ existe (8 colunas)
- cdi_eventos            ✓ existe (15 colunas)
- cdi_performance        ✓ existe (10 colunas)
- ... (5 mais tabelas)
```

**Status esperado:** "sucesso" se todas as 8 aparecerem ✓

**Ação:** Clique "Próximo →"

---

#### **Passo 4: Configuração CODI**
Digite as credenciais do seu servidor CODI:

```
Campo                    | Exemplo
-------------------------|---------------------------
cdi_servidor_url         | http://192.168.0.100:8080
cdi_usuario              | admin
cdi_senha                | senha123
cdi_codename_empresa     | matriz
```

**Onde encontrar?**
- URL: Proporcionado pelo fornecedor CODI
- Usuário/Senha: Credenciais de acesso ao servidor
- Codename: Nome da unidade/empresa no CODI

**Ação:** Preencha todos os campos e clique "Próximo →"

---

#### **Passo 5: Testar Conexão**
Valida a conexão com o servidor CODI

**Possíveis resultados:**
```
✅ Conectado com sucesso!
→ Significa que a URL e credenciais estão corretos

⚠️ Autenticação falhou
→ Verifique username/password

❌ URL não encontrada
→ Verifique o endereço do servidor
```

**Ação:** Se OK, clique "Próximo →"

---

#### **Passo 6: Setup Concluído**
Resumo final com próximas ações

**Você verá:**
- ✅ Tudo pronto para começar
- Próximos passos (FASE 2)
- Links para documentação

**Ação:** Clique "✓ Concluir"

---

## 🔄 Navegação

Durante qualquer passo:
- **← Anterior**: Volta pro passo anterior
- **Próximo →**: Avança pro próximo
- **✓ Concluir**: Finaliza (no último passo)

---

## ⚠️ Troubleshooting

### "PHP PDO MySQL não encontrado"
**Problema:** Extensão MySQL não ativada

**Solução:**
1. Abra `php.ini` (em `xampp/php/php.ini`)
2. Descomente: `;extension=pdo_mysql` → `extension=pdo_mysql`
3. Reinicie Apache

---

### "Banco de dados não conectado"
**Problema:** Bootstrap.php não consegue conectar

**Solução:**
1. Verifique `src/bootstrap.php`
2. Verifique credenciais MySQL em `src/config/database.php`
3. Teste conectar manualmente:
```bash
mysql -u root -p controlepcp_sandbox
```

---

### "Arquivo de migrations não encontrado"
**Problema:** `codi_migrations.sql` não existe

**Solução:**
1. Verifique se o arquivo está em `db/codi_migrations.sql`
2. Se não, baixe novamente

---

### "Erro HTTP 401 na conexão CODI"
**Problema:** Credenciais incorretas

**Solução:**
1. Volte to Passo 4
2. Verifique username e password
3. Teste em Postman antes de adicionar no wizard

---

### "Erro HTTP 404 na conexão CODI"
**Problema:** URL do servidor está errada

**Solução:**
1. Volte ao Passo 4
2. Confirme: `http://ip-do-servidor:porta`
3. Teste ping para o servidor

---

## 📊 Dados Salvos

Depois de completar o wizard:

### Banco de Dados
- 8 tabelas criadas em `controlepcp_sandbox`
- Pronto para receber dados do CODI

### Arquivo de Configuração
Os dados entrados serão salvos em:
- `cdi_configuracao` (tabela no BD)

---

## 🔐 Segurança

**Dados sensíveis:**
- Senhas são armazenadas em `cdi_configuracao` (BD)
- NÃO são logadas
- NÃO são enviadas por email
- Acesso apenas via `src/bootstrap.php`

---

## 📋 Checklist Pós-Setup

Depois de concluir o wizard:

- [ ] Todos os 6 passos completados com sucesso
- [ ] 8 tabelas criadas no BD
- [ ] Conexão CODI testada e OK
- [ ] Credentials salvos em `cdi_configuracao`
- [ ] Pronto para FASE 2

---

## 🚀 Próximo Passo

Depois de concluir o Setup Wizard:

### FASE 2: Criar CodiClient.php

Será criado: `src/Codi/CodiClient.php`

Este arquivo será responsável por:
- Conectar ao servidor CODI
- Buscar dados de produção
- Enviar dados para nosso BD

**Tempo estimado:** 1-2 dias

---

## 📞 Precisando de Ajuda?

### Arquivos Relacionados

| Arquivo | Para |
|---------|------|
| [`CODI_SCHEMA_DOCUMENTATION.md`](CODI_SCHEMA_DOCUMENTATION.md) | Entender as 8 tabelas |
| [`visualizar_migrations.php`](visualizar_migrations.php) | Ver diagrama visual |
| [`README_MIGRATIONS.md`](README_MIGRATIONS.md) | Executar migrations manualmente |
| [`ÍNDICE.md`](ÍNDICE.md) | Índice de todos arquivos |

---

## 🎓 Aprendendo Mais

Depois de usar o wizard, leia (nesta ordem):

1. [`RESUMO_FASE_1.md`](RESUMO_FASE_1.md) - Entender o que foi criado
2. [`CODI_SCHEMA_DOCUMENTATION.md`](CODI_SCHEMA_DOCUMENTATION.md) - Detalhar cada tabela
3. Código-fonte: `db/codi_migrations.sql` - Ver exatamente cada statement

---

Generated: 2026-04-06
Part of: CODI Integration PHASE 1
