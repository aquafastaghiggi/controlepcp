import pandas as pd
import mysql.connector
import json
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
registros_eventos = 0
registros_eventos_erro = 0

sql_eventos = """
INSERT INTO realizado_2026_eventos
(evt_chave_externa, evt_codigo_evento, data_evento, ordem_op, quantidade, inicio_evento, fim_evento, duracao_evento_minutos, estado_evento, parada_nomeParada, parada_tipo_nome, setup_duracao_minutos, setup_eventos_count, payload_json)
VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
ON DUPLICATE KEY UPDATE
    evt_codigo_evento = VALUES(evt_codigo_evento),
    data_evento = VALUES(data_evento),
    ordem_op = VALUES(ordem_op),
    quantidade = VALUES(quantidade),
    inicio_evento = VALUES(inicio_evento),
    fim_evento = VALUES(fim_evento),
    duracao_evento_minutos = VALUES(duracao_evento_minutos),
    estado_evento = VALUES(estado_evento),
    parada_nomeParada = COALESCE(NULLIF(VALUES(parada_nomeParada), ''), parada_nomeParada),
    parada_tipo_nome = COALESCE(NULLIF(VALUES(parada_tipo_nome), ''), parada_tipo_nome),
    setup_duracao_minutos = VALUES(setup_duracao_minutos),
    setup_eventos_count = VALUES(setup_eventos_count),
    payload_json = VALUES(payload_json),
    imported_at = NOW()
"""

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
        inicio_evento = row.get('inicio', None)
        fim_evento = row.get('fim', None)
        duracao_evento = row.get('duracao', None)
        parada_nome = str(row.get('parada_nomeParada', '') or '').strip()
        parada_tipo = str(row.get('parada_tipoParada_nomeTipoParada', '') or '').strip()
        estado_evento = str(row.get('estado', '') or '').strip()
        setup_duracao_minutos = float(duracao_evento) if parada_nome in ('TROCA DE KIT', 'TROCA DE LIQUIDO') and duracao_evento is not None else 0.0
        setup_eventos_count = 1 if parada_nome in ('TROCA DE KIT', 'TROCA DE LIQUIDO') else 0
        evt_chave_externa = f"xlsx|{idx}|{data_consulta}|{op}|{inicio_evento}|{fim_evento}|{parada_nome or 'SEM_PARADA'}"
        
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

        try:
            cursor.execute(
                sql_eventos,
                (
                    evt_chave_externa,
                    row.get('codigo_evento', None),
                    data_consulta,
                    op,
                    quantidade,
                    inicio_evento,
                    fim_evento,
                    float(duracao_evento) if duracao_evento not in (None, '') else 0,
                    estado_evento,
                    parada_nome or None,
                    parada_tipo or None,
                    setup_duracao_minutos,
                    setup_eventos_count,
                    json.dumps(row.to_dict(), ensure_ascii=False, default=str)
                )
            )
            registros_eventos += 1
        except Exception as e:
            print(f"Erro ao gravar evento bruto na linha {idx}: {e}")
            registros_eventos_erro += 1
        
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
    print(f"Eventos brutos inseridos/atualizados: {registros_eventos}")
    print(f"Registros com erro: {registros_erro}")
    print(f"Erros em eventos brutos: {registros_eventos_erro}")
