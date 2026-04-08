import mysql.connector
from src.Database.Connection import connection_config

conn = mysql.connector.connect(**connection_config)
cur = conn.cursor()

# Procurar tabelas que podem ter nome do produto
cur.execute("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")
tables = cur.fetchall()
print('=== Tabelas disponíveis ===')
for t in tables:
    print(f'  - {t[0]}')

# Verificar estrutura da tabela sch_linhas
print('\n=== Campos de sch_linhas ===')
cur.execute('DESC sch_linhas')
fields = cur.fetchall()
for f in fields:
    print(f'  - {f[0]} ({f[1]})')

# Verificar se existe tabela de produtos/SKU
print('\n=== Procurando tabelas de SKU/Produto ===')
cur.execute("SHOW TABLES LIKE '%produto%' OR LIKE '%sku%'")
result = cur.fetchall()
if result:
    for t in result:
        print(f'  - {t[0]}')
        cur.execute(f'DESC {t[0]}')
        fields = cur.fetchall()
        for f in fields:
            print(f'    - {f[0]} ({f[1]})')

conn.close()
