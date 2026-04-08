import mysql.connector

try:
    config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'database': 'controlepcp'
    }
    conn = mysql.connector.connect(**config)
    cursor = conn.cursor(dictionary=True)
    
    cursor.execute('SELECT prg_id, prg_numero_op FROM prg_programas LIMIT 15')
    
    print('=== Programações ===')
    for row in cursor:
        op = row['prg_numero_op'] if row['prg_numero_op'] else 'NULL'
        print('ID: {} | OP: {}'.format(row['prg_id'], op))
    
    conn.close()
except Exception as e:
    print('Erro: {}'.format(str(e)))
