#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Script de Sincronizacao CODI - Via API REST - Ultimos 150 Dias
Pulla dados diretamente da API do CODI e alimenta realizado_2026_excel
"""

import os
import sys
import json
import mysql.connector
import requests
from datetime import datetime, timedelta

def log(msg, type='info'):
    """Log com timestamp"""
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    prefix = f"[{timestamp}] "
    
    if type == 'success':
        print(f"{prefix}[OK] {msg}")
    elif type == 'error':
        print(f"{prefix}[ERRO] {msg}", file=sys.stderr)
    elif type == 'warning':
        print(f"{prefix}[AVISO] {msg}")
    else:
        print(f"{prefix}[INFO] {msg}")

def connect_db():
    """Conecta ao banco MySQL"""
    try:
        db_config = {
            'host': 'localhost',
            'user': 'root',
            'password': 'k7m2y9u4',
            'database': 'controlepcp_sandbox'
        }
        
        conn = mysql.connector.connect(**db_config)
        log(f"Conectado ao banco: {db_config['database']}", 'success')
        return conn
    except Exception as e:
        log(f"Erro ao conectar MySQL: {e}", 'error')
        sys.exit(1)

def get_codi_data_from_api():
    """
    Pulla dados da API REST do CODI dos ultimos 150 dias
    """
    # Configuracoes CODI
    api_url = "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEventoConsolidado"
    usuario = "marcos.brun"
    senha = "Eb035611!"
    operacao = 20
    
    # Calcular intervalo de datas
    data_final = datetime.now() - timedelta(days=1)
    data_inicial = data_final - timedelta(days=150)
    
    log(f"Consultando API CODI de {data_inicial.date()} ate {data_final.date()}")
    
    lista_datas = [data_inicial + timedelta(days=x) for x in range((data_final - data_inicial).days + 1)]
    todos_os_dados = []
    
    contador = 0
    for data in lista_datas:
        data_str = data.strftime('%Y-%m-%d')
        url = f"{api_url}?data={data_str}&operacao={operacao}"
        
        try:
            resposta = requests.get(url, auth=(usuario, senha), timeout=10)
            
            if resposta.status_code == 200:
                json_data = resposta.json()
                if "data" in json_data and json_data["data"]:
                    for item in json_data["data"]:
                        # Extrair informacoes de ordens (pode ter multiplas ordens por evento)
                        ordens = item.get('ordens', [])
                        if ordens and isinstance(ordens, list):
                            for ordem in ordens:
                                # Extrair SKU do item como identificador da OP
                                ordem_prod = ordem.get('ordemProducao', {})
                                if isinstance(ordem_prod, dict):
                                    item_obj = ordem_prod.get('item', {})
                                    sku = item_obj.get('codItem', '').strip()
                                else:
                                    sku = ''
                                
                                # Preferir quantidadeProduzidaRecurso, depois quantidadeProduzidaItem
                                quantidade = ordem.get('quantidadeProduzidaRecurso') or ordem.get('quantidadeProduzidaItem') or 0
                                
                                if sku and quantidade and quantidade > 0:
                                    linha = {
                                        'data_evento': data_str,
                                        'ordem_op': sku,  # SKU como identificador da OP
                                        'quantidade': float(quantidade),
                                        'status': 'realizado'
                                    }
                                    todos_os_dados.append(linha)
                    
                    log(f"{data_str}: {len(json_data['data'])} eventos encontrados", 'info')
                else:
                    log(f"{data_str}: Sem dados", 'info')
            else:
                log(f"{data_str}: HTTP {resposta.status_code}", 'warning')
                
        except requests.exceptions.Timeout:
            log(f"{data_str}: Timeout na API", 'warning')
        except Exception as e:
            log(f"{data_str}: Erro na API - {str(e)}", 'warning')
        
        contador += 1
        if contador % 20 == 0:
            log(f"Processadas {contador} datas ({len(todos_os_dados)} registros)", 'info')
    
    log(f"Recuperados {len(todos_os_dados)} registros dos ultimos 150 dias", 'success')
    
    # Agrupar por data_evento + ordem_op e somar quantidades
    dados_agrupados = {}
    for linha in todos_os_dados:
        chave = (linha['data_evento'], linha['ordem_op'])
        if chave in dados_agrupados:
            dados_agrupados[chave] += linha['quantidade']
        else:
            dados_agrupados[chave] = linha['quantidade']
    
    # Converter de volta para lista de dicionarios
    dados_consolidados = [
        {
            'data_evento': chave[0],
            'ordem_op': chave[1],
            'quantidade': quantidade,
            'status': 'realizado'
        }
        for chave, quantidade in dados_agrupados.items()
    ]
    
    log(f"Apos consolidacao: {len(dados_consolidados)} registros unicos", 'success')
    return dados_consolidados

def insert_realizado_data(conn, data):
    """Insere dados em realizado_2026_excel"""
    cursor = conn.cursor()
    inserted = 0
    errors = 0
    
    sql = """
        INSERT INTO realizado_2026_excel 
        (data_evento, ordem_op, quantidade)
        VALUES (%s, %s, %s)
        ON DUPLICATE KEY UPDATE
        quantidade = VALUES(quantidade),
        imported_at = NOW()
    """
    
    for row in data:
        try:
            qtd = float(row['quantidade']) if row['quantidade'] else 0
            if qtd > 0:
                cursor.execute(sql, (
                    row['data_evento'],
                    row['ordem_op'],
                    qtd
                ))
                inserted += 1
        except Exception as e:
            log(f"Erro ao inserir OP {row.get('ordem_op', 'UNKNOWN')}: {e}", 'warning')
            errors += 1
    
    conn.commit()
    cursor.close()
    
    log(f"Inseridos: {inserted} | Erros: {errors}", 'success' if errors == 0 else 'warning')
    return inserted, errors

def main():
    """Fluxo principal"""
    try:
        log("Iniciando sincronizacao CODI via API (ultimos 150 dias)")
        
        # Conectar banco
        conn = connect_db()
        
        # Puxar dados da API
        codi_data = get_codi_data_from_api()
        
        if not codi_data:
            log("Nenhum dado encontrado na API", 'warning')
            conn.close()
            sys.exit(0)
        
        # Inserir dados
        inserted, errors = insert_realizado_data(conn, codi_data)
        
        conn.close()
        
        # Resultado final
        log(f"Sincronizacao concluida: {inserted} registros inseridos", 'success')
        
        # Retornar JSON com resultado
        result = {
            'success': True,
            'message': f'Sincronizacao concluida: {inserted} registros dos ultimos 150 dias via API CODI',
            'inserted': inserted,
            'errors': errors
        }
        print(json.dumps(result))
        
    except Exception as e:
        log(f"Erro fatal: {e}", 'error')
        sys.exit(1)

if __name__ == '__main__':
    main()
