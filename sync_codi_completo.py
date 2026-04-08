#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Script de Sincronizacao CODI - Via API REST - Sem Consolidacao
Puxa TODOS os dados da API sem filtro de período
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
    Puxa dados da API REST do CODI SEM consolidacao
    Cada evento é registrado como um evento separado
    """
    # Configuracoes CODI
    api_url = "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEventoConsolidado"
    usuario = "marcos.brun"
    senha = "Eb035611!"
    operacao = 20
    
    # Usar período de 365 dias (1 ano completo)
    data_final = datetime.now() - timedelta(days=1)
    data_inicial = data_final - timedelta(days=365)
    
    log(f"Consultando API CODI de {data_inicial.date()} ate {data_final.date()} (sem consolidacao)")
    
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
                    eventos_no_dia = 0
                    for item in json_data["data"]:
                        ordens = item.get('ordens', [])
                        if ordens and isinstance(ordens, list):
                            for ordem in ordens:
                                # Extrair SKU do item
                                ordem_prod = ordem.get('ordemProducao', {})
                                if isinstance(ordem_prod, dict):
                                    item_obj = ordem_prod.get('item', {})
                                    sku = item_obj.get('codItem', '').strip()
                                else:
                                    sku = ''
                                
                                # Pegar quantidade (Recurso é a principal)
                                quantidade = ordem.get('quantidadeProduzidaRecurso') or ordem.get('quantidadeProduzidaItem') or 0
                                
                                if sku and quantidade and quantidade > 0:
                                    linha = {
                                        'data_evento': data_str,
                                        'ordem_op': sku,
                                        'quantidade': float(quantidade),
                                        'status': 'realizado'
                                    }
                                    todos_os_dados.append(linha)
                                    eventos_no_dia += 1
                    
                    if eventos_no_dia > 0:
                        log(f"{data_str}: {eventos_no_dia} eventos", 'info')
                else:
                    log(f"{data_str}: Sem dados", 'info')
            else:
                log(f"{data_str}: HTTP {resposta.status_code}", 'warning')
                
        except requests.exceptions.Timeout:
            log(f"{data_str}: Timeout na API", 'warning')
        except Exception as e:
            log(f"{data_str}: Erro na API - {str(e)}", 'warning')
        
        contador += 1
        if contador % 30 == 0:
            log(f"Processadas {contador} datas ({len(todos_os_dados)} eventos)", 'info')
    
    log(f"Total de eventos recuperados: {len(todos_os_dados)}", 'success')
    return todos_os_dados

def insert_realizado_data(conn, data):
    """Insere dados em realizado_2026_excel - SEM consolidacao"""
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
        log("Iniciando sincronizacao CODI via API (365 dias - SEM consolidacao)")
        
        conn = connect_db()
        
        # Recuperar dados da API
        dados = get_codi_data_from_api()
        
        if not dados:
            log("Nenhum dado para inserir", 'warning')
            conn.close()
            sys.exit(0)
        
        # Inserir dados
        inserted, errors = insert_realizado_data(conn, dados)
        
        # Resumo
        log(f"Sincronizacao concluida: {inserted} eventos inseridos", 'success')
        
        # JSON response para API
        response = {
            'success': True,
            'message': f"Sincronizacao concluida: {inserted} eventos dos ultimos 365 dias via API CODI",
            'inserted': inserted,
            'errors': errors
        }
        print(json.dumps(response, ensure_ascii=False))
        
        conn.close()
        
    except Exception as e:
        log(f"Erro critico: {e}", 'error')
        response = {
            'success': False,
            'message': str(e),
            'errors': 1
        }
        print(json.dumps(response, ensure_ascii=False))
        sys.exit(1)

if __name__ == '__main__':
    main()
