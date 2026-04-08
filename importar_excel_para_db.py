import pandas as pd
import mysql.connector
from datetime import datetime

# Ler o arquivo Excel gerado
arquivo_excel = "relatorio_api_20260408_161834.xlsx"
df = pd.read_excel(arquivo_excel)

print(f"Carregadas {len(df)} linhas do Excel")
print(f"Colunas disponíveis: {list(df.columns)}")

# Conectar ao banco de dados
conexao = mysql.connector.connect(
    host="localhost",
    user="root",
    password="k7m2y9u4",
    database="controlepcp_sandbox"
)

cursor = conexao.cursor()

# Preparar dados para inserção
registros_inseridos = 0
registros_erro = 0

for idx, row in df.iterrows():
    try:
        # Extrair valores usando as colunas corretas
        data_consulta = row.get('data_consulta', None)
        op = row.get('ordem_ordemProducao_ordem', None)
        quantidade = row.get('ordem_quantidadeBoasItem', None)
        
        # Validar dados mínimos
        if not all([data_consulta, op, quantidade]):
            registros_erro += 1
            continue
        
        # Converter quantidade para float
        quantidade = float(quantidade)
        
        # Pular se quantidade for 0 ou negativa
        if quantidade <= 0:
            registros_erro += 1
            continue
        
        # Inserir na tabela
        sql = """
        INSERT INTO realizado_2026_excel (data_evento, ordem_op, quantidade, imported_at)
        VALUES (%s, %s, %s, NOW())
        ON DUPLICATE KEY UPDATE 
            quantidade = quantidade + VALUES(quantidade),
            imported_at = NOW()
        """
        
        cursor.execute(sql, (data_consulta, op, quantidade))
        registros_inseridos += 1
        
        if registros_inseridos % 100 == 0:
            print(f"Processados {registros_inseridos} registros...")
    
    except Exception as e:
        print(f"Erro na linha {idx}: {e}")
        registros_erro += 1

# Confirmar transação
conexao.commit()
cursor.close()
conexao.close()

print(f"\n✅ Inserção concluída!")
print(f"Registros inseridos/atualizados: {registros_inseridos}")
print(f"Registros com erro: {registros_erro}")
