# 📊 Dump SQL do Banco - Portabilidade

## ℹ️ Informações

**Arquivo:** `dump_controlepcp_20260408_201208.sql`

**Data Gerada:** 08 de abril de 2026

**Tamanho:** 1.90 MB

**Tabelas:** 17 (todas com estrutura + dados)

**Encoding:** UTF-8MB4

## 📍 Localização

- **Sandbox:** `c:\xampp\htdocs\controlepcp_sandbox\dump_controlepcp_20260408_201208.sql`
- **Produção:** `c:\xampp\htdocs\controlepcp\dump_controlepcp_20260408_201208.sql`
- **GitHub:** Referência em documentação (arquivo não versionado para economizar espaço)

## 🚀 Como Usar em Outro PC

### 1. Copiar o arquivo dump para o novo PC
```bash
# Para o diretório do MySQL/XAMPP
cp dump_controlepcp_20260408_201208.sql C:\xampp\mysql\data\
```

### 2. Restaurar o banco de dados
```bash
# Via MySQL CLI
mysql -u root < dump_controlepcp_20260408_201208.sql

# Ou especificar o banco
mysql -u root controlepcp < dump_controlepcp_20260408_201208.sql
```

### 3. Verificar a restauração
```bash
# Conectar ao MySQL
mysql -u root

# Dentro do MySQL
USE controlepcp;
SHOW TABLES;
SELECT COUNT(*) FROM prg_programas;
```

## 📦 Conteúdo do Dump

Tabelas incluídas:
1. `cal_calendarios` - Calendários de produção
2. `cal_dias_uteis` - Dias úteis
3. `cal_feriados` - Feriados
4. `cal_intervalos` - Intervalos de tempo
5. `codi_calendario` - CODI calendário
6. `codi_mapeamento` - Mapeamento CODI
7. `codi_performance` - Performance CODI
8. `codi_recursos` - Recursos CODI
9. `codi_sincronizacao` - Sincronização CODI
10. `lin_linhas` - Linhas de produção
11. `mat_matriz_setup` - Matriz de setup
12. `prd_produtos` - Produtos
13. `prg_itens` - Itens de programação
14. `prg_programas` - Programações
15. `realizado_2026_excel` - Realizado 2026 (Excel)
16. `sch_linhas` - Schedule/Linhas
17. `usr_users` - Usuários do sistema

## ✅ Integridade

- ✅ Todas as chaves primárias preservadas
- ✅ Todas as chaves estrangeiras preservadas
- ✅ Dados completos e consistentes
- ✅ Encoding UTF-8MB4 para caracteres especiais (português, acentos, etc)

## 🔄 Atualizações Futuras

Sempre que houver mudanças significativas no banco, gere um novo dump:
```bash
php generate_dump_via_pdo.php
```

Novo arquivo será criado em `/controlepcp_sandbox/` com timestamp da data/hora.
