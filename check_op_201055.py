import mysql.connector
from decimal import Decimal

conexao = mysql.connector.connect(
    host='localhost',
    user='root',
    password='k7m2y9u4',
    database='controlepcp_sandbox'
)
cursor = conexao.cursor()

# Procurar por OP 201055 (SKU 20010003)
cursor.execute('SELECT data_evento, quantidade FROM realizado_2026_excel WHERE ordem_op = %s ORDER BY data_evento', ('20010003',))
registros = cursor.fetchall()

print(f'OP 201055 (SKU 20010003): {len(registros)} registros')
print('\nDetalhes:')
total = Decimal('0')
for data, qtd in registros:
    total += qtd
    print(f'{data} -> {qtd}')

print(f'\n✅ TOTAL PARA OP 201055: {total}')

cursor.close()
conexao.close()
