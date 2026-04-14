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

def normalizar_ordem(valor):
    """Normaliza número da OP para casar com o padrão usado no PCP local."""
    texto = str(valor or '').strip()
    if not texto:
        return ''
    if texto.isdigit():
        return texto.lstrip('0') or '0'
    return texto

def normalizar_nome_parada(valor):
    """Normaliza o nome da parada para persistencia e filtros do relatorio."""
    return str(valor or '').strip()

def normalizar_tipo_parada(valor):
    """Normaliza o nome do tipo de parada para o detalhe complementar."""
    return str(valor or '').strip()

def prioridade_parada(nome):
    """Prioriza as paradas alvo do relatorio."""
    texto = normalizar_nome_parada(nome).upper()
    if texto == 'TROCA DE KIT':
        return 2
    if texto == 'TROCA DE LIQUIDO':
        return 1
    return 0

def eh_parada_alvo(nome):
    """Retorna True quando a parada deve entrar no relatorio de setup."""
    return prioridade_parada(nome) > 0

def calcular_duracao_minutos(inicio, fim):
    """Calcula a duracao em minutos de um evento do CODI."""
    try:
        if not inicio or not fim:
            return 0.0
        dt_inicio = datetime.fromisoformat(str(inicio))
        dt_fim = datetime.fromisoformat(str(fim))
        return max(0.0, (dt_fim - dt_inicio).total_seconds() / 60.0)
    except Exception:
        return 0.0

def chave_evento_externa(codigo_evento, data_evento, inicio_evento, fim_evento, ordem_op, parada_nome):
    """Gera uma chave estável para preservar o evento bruto no detalhe complementar."""
    codigo = str(codigo_evento or '').strip()
    ordem = normalizar_ordem(ordem_op)
    if codigo:
        return f"{codigo}|{ordem}"
    return "|".join([
        str(data_evento or '').strip(),
        str(inicio_evento or '').strip(),
        str(fim_evento or '').strip(),
        ordem,
        normalizar_nome_parada(parada_nome) or 'SEM_PARADA'
    ])

def connect_db():
    """Conecta ao banco MySQL"""
    try:
        # Permite rodar em sandbox/produção sem hardcode (defaults pelo nome da pasta do projeto)
        # e ainda permite override via env vars (DB_HOST/DB_USER/DB_PASS/DB_NAME).
        project_dir = os.path.basename(os.path.dirname(os.path.abspath(__file__))) or ''
        default_db = project_dir if project_dir in ('controlepcp', 'controlepcp_sandbox') else 'controlepcp_sandbox'

        db_config = {
            'host': os.getenv('DB_HOST', 'localhost'),
            'user': os.getenv('DB_USER', 'root'),
            'password': os.getenv('DB_PASS', 'k7m2y9u4'),
            'database': os.getenv('DB_NAME', default_db)
        }
        
        conn = mysql.connector.connect(**db_config)
        log(f"Conectado ao banco: {db_config['database']}", 'success')
        return conn
    except Exception as e:
        log(f"Erro ao conectar MySQL: {e}", 'error')
        sys.exit(1)

def ensure_sync_status_table(conn):
    cursor = conn.cursor()
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS codi_sync_status (
            sync_key VARCHAR(32) NOT NULL,
            sync_date DATE NULL,
            is_running TINYINT(1) NOT NULL DEFAULT 0,
            stage_code VARCHAR(64) NOT NULL DEFAULT 'idle',
            stage_label VARCHAR(120) NOT NULL DEFAULT 'Idle',
            stage_detail TEXT NULL,
            stage_index INT NOT NULL DEFAULT 0,
            stage_total INT NOT NULL DEFAULT 0,
            backend VARCHAR(16) NULL,
            started_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            last_error TEXT NULL,
            records_today INT NOT NULL DEFAULT 0,
            last_sync_at DATETIME NULL,
            PRIMARY KEY (sync_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """
    )
    conn.commit()
    cursor.close()

def set_sync_status(conn, stage_code, stage_label, stage_detail='', stage_index=0, stage_total=0, is_running=True, backend='python', started_at=None, finished_at=None, last_error=None, records_today=0, last_sync_at=None):
    ensure_sync_status_table(conn)
    cursor = conn.cursor()
    sql = """
        INSERT INTO codi_sync_status
            (sync_key, sync_date, is_running, stage_code, stage_label, stage_detail, stage_index, stage_total, backend, started_at, updated_at, finished_at, last_error, records_today, last_sync_at)
        VALUES
            (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            sync_date = VALUES(sync_date),
            is_running = VALUES(is_running),
            stage_code = VALUES(stage_code),
            stage_label = VALUES(stage_label),
            stage_detail = VALUES(stage_detail),
            stage_index = VALUES(stage_index),
            stage_total = VALUES(stage_total),
            backend = VALUES(backend),
            started_at = COALESCE(codi_sync_status.started_at, VALUES(started_at)),
            updated_at = NOW(),
            finished_at = VALUES(finished_at),
            last_error = VALUES(last_error),
            records_today = VALUES(records_today),
            last_sync_at = VALUES(last_sync_at)
    """
    today = datetime.now().strftime('%Y-%m-%d')
    cursor.execute(sql, (
        'codi',
        today,
        1 if is_running else 0,
        stage_code,
        stage_label,
        stage_detail,
        int(stage_index or 0),
        int(stage_total or 0),
        backend,
        started_at,
        finished_at,
        last_error,
        int(records_today or 0),
        last_sync_at,
    ))
    conn.commit()
    cursor.close()

def get_codi_data_from_api(status_callback=None):
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
    todos_os_detalhes = []
    
    contador = 0
    for data in lista_datas:
        data_str = data.strftime('%Y-%m-%d')
        url = f"{api_url}?data={data_str}&operacao={operacao}"

        if status_callback:
            status_callback(
                'consulting_codi',
                'Consultando CODI',
                f'Buscando eventos de {data_str} ({contador + 1}/{len(lista_datas)})',
                2,
                6,
                True
            )
        
        try:
            resposta = requests.get(url, auth=(usuario, senha), timeout=10)
            
            if resposta.status_code == 200:
                json_data = resposta.json()
                if "data" in json_data and json_data["data"]:
                    for item in json_data["data"]:
                        codigo_evento = item.get('codigoEvento')
                        estado_evento = str(item.get('estado') or '').strip()
                        # Extrair informacoes de ordens (pode ter multiplas ordens por evento)
                        ordens = item.get('ordens', [])
                        inicio_evento = item.get('inicio')
                        fim_evento = item.get('fim')
                        duracao_evento = calcular_duracao_minutos(inicio_evento, fim_evento)
                        parada = item.get('parada')
                        parada_nome = ''
                        parada_tipo_nome = ''
                        if isinstance(parada, dict):
                            parada_nome = normalizar_nome_parada(parada.get('nomeParada'))
                            tipo_parada = parada.get('tipoParada')
                            if isinstance(tipo_parada, dict):
                                parada_tipo_nome = normalizar_tipo_parada(tipo_parada.get('nomeTipoParada'))
                        if ordens and isinstance(ordens, list):
                            for ordem in ordens:
                                # Extrair numero real da OP; fallback para SKU apenas se a OP nao vier.
                                ordem_prod = ordem.get('ordemProducao', {})
                                if isinstance(ordem_prod, dict):
                                    ordem_op = normalizar_ordem(ordem_prod.get('ordem'))
                                    item_obj = ordem_prod.get('item', {})
                                    sku = item_obj.get('codItem', '').strip()
                                else:
                                    ordem_op = ''
                                    sku = ''
                                identificador_op = ordem_op or sku
                                
                                chave_externa = chave_evento_externa(
                                    codigo_evento,
                                    data_str,
                                    inicio_evento,
                                    fim_evento,
                                    identificador_op,
                                    parada_nome
                                )

                                detalhe_linha = {
                                    'evt_chave_externa': chave_externa,
                                    'codigo_evento': codigo_evento,
                                    'data_evento': data_str,
                                    'ordem_op': identificador_op,
                                    'quantidade': float(ordem.get('quantidadeBoasItem') or 0),
                                    'inicio_evento': inicio_evento,
                                    'fim_evento': fim_evento,
                                    'duracao_evento_minutos': duracao_evento,
                                    'estado_evento': estado_evento,
                                    'parada_nomeParada': parada_nome,
                                    'parada_tipo_nome': parada_tipo_nome,
                                    'setup_duracao_minutos': calcular_duracao_minutos(inicio_evento, fim_evento) if eh_parada_alvo(parada_nome) else 0.0,
                                    'setup_eventos_count': 1 if eh_parada_alvo(parada_nome) else 0,
                                    'payload_json': json.dumps(item, ensure_ascii=False)
                                }
                                todos_os_detalhes.append(detalhe_linha)

                                # Usar a mesma base do Excel importado: quantidade boa por item.
                                # Se a parada for um setup alvo, manter mesmo com quantidade zero
                                # para nao perder o intervalo de tempo do setup realizado.
                                quantidade = ordem.get('quantidadeBoasItem') or 0
                                setup_duracao_minutos = calcular_duracao_minutos(inicio_evento, fim_evento) if eh_parada_alvo(parada_nome) else 0.0
                                
                                if identificador_op and ((quantidade and quantidade > 0) or eh_parada_alvo(parada_nome)):
                                    linha = {
                                        'data_evento': data_str,
                                        'ordem_op': identificador_op,
                                        'quantidade': float(quantidade),
                                        'inicio_evento': inicio_evento,
                                        'fim_evento': fim_evento,
                                        'parada_nomeParada': parada_nome,
                                        'setup_duracao_minutos': setup_duracao_minutos,
                                        'setup_eventos_count': 1 if eh_parada_alvo(parada_nome) else 0,
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
            log(f"Processadas {contador} datas ({len(todos_os_dados)} registros principais | {len(todos_os_detalhes)} registros brutos)", 'info')
    
    log(f"Recuperados {len(todos_os_dados)} registros principais e {len(todos_os_detalhes)} registros brutos dos ultimos 150 dias", 'success')
    if status_callback:
        status_callback(
            'processing_data',
            'Processando dados',
            f'Consolidando {len(todos_os_dados)} registros principais e {len(todos_os_detalhes)} registros brutos.',
            3,
            6,
            True
        )
    
    # Agrupar por data_evento + ordem_op e somar quantidades
    dados_agrupados = {}
    for linha in todos_os_dados:
        chave = (linha['data_evento'], linha['ordem_op'])
        if chave in dados_agrupados:
            dados_agrupados[chave]['quantidade'] += linha['quantidade']
            dados_agrupados[chave]['setup_duracao_minutos'] += float(linha.get('setup_duracao_minutos') or 0)
            dados_agrupados[chave]['setup_eventos_count'] += int(linha.get('setup_eventos_count') or 0)
            nome_atual = dados_agrupados[chave].get('parada_nomeParada')
            nome_novo = normalizar_nome_parada(linha.get('parada_nomeParada'))
            if prioridade_parada(nome_novo) > prioridade_parada(nome_atual):
                dados_agrupados[chave]['parada_nomeParada'] = nome_novo
            elif not nome_atual and nome_novo:
                dados_agrupados[chave]['parada_nomeParada'] = nome_novo
            if linha.get('inicio_evento'):
                inicio_atual = dados_agrupados[chave].get('inicio_evento')
                if not inicio_atual or linha['inicio_evento'] < inicio_atual:
                    dados_agrupados[chave]['inicio_evento'] = linha['inicio_evento']
            if linha.get('fim_evento'):
                fim_atual = dados_agrupados[chave].get('fim_evento')
                if not fim_atual or linha['fim_evento'] > fim_atual:
                    dados_agrupados[chave]['fim_evento'] = linha['fim_evento']
        else:
            dados_agrupados[chave] = {
                'quantidade': linha['quantidade'],
                'inicio_evento': linha.get('inicio_evento'),
                'fim_evento': linha.get('fim_evento'),
                'parada_nomeParada': normalizar_nome_parada(linha.get('parada_nomeParada')),
                'setup_duracao_minutos': float(linha.get('setup_duracao_minutos') or 0),
                'setup_eventos_count': int(linha.get('setup_eventos_count') or 0)
            }
    
    # Converter de volta para lista de dicionarios
    dados_consolidados = [
        {
            'data_evento': chave[0],
            'ordem_op': chave[1],
            'quantidade': dados['quantidade'],
            'inicio_evento': dados.get('inicio_evento'),
            'fim_evento': dados.get('fim_evento'),
            'parada_nomeParada': normalizar_nome_parada(dados.get('parada_nomeParada')),
            'setup_duracao_minutos': float(dados.get('setup_duracao_minutos') or 0),
            'setup_eventos_count': int(dados.get('setup_eventos_count') or 0),
            'status': 'realizado'
        }
        for chave, dados in dados_agrupados.items()
    ]
    
    log(f"Apos consolidacao: {len(dados_consolidados)} registros unicos", 'success')
    return {
        'realizado': dados_consolidados,
        'detalhes': todos_os_detalhes,
    }

def insert_eventos_detalhe(conn, data):
    """Insere eventos brutos em realizado_2026_eventos para analise complementar."""
    cursor = conn.cursor()
    inserted = 0
    errors = 0

    sql = """
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

    for row in data:
        try:
            cursor.execute(sql, (
                row['evt_chave_externa'],
                row.get('codigo_evento'),
                row['data_evento'],
                row['ordem_op'],
                float(row['quantidade']) if row.get('quantidade') else 0,
                row.get('inicio_evento'),
                row.get('fim_evento'),
                float(row.get('duracao_evento_minutos') or 0),
                row.get('estado_evento'),
                row.get('parada_nomeParada') or None,
                row.get('parada_tipo_nome') or None,
                float(row.get('setup_duracao_minutos') or 0),
                int(row.get('setup_eventos_count') or 0),
                row.get('payload_json')
            ))
            inserted += 1
        except Exception as e:
            log(f"Erro ao gravar detalhe bruto da OP {row.get('ordem_op', 'UNKNOWN')}: {e}", 'warning')
            errors += 1

    conn.commit()
    cursor.close()

    log(f"Detalhes brutos inseridos: {inserted} | Erros: {errors}", 'success' if errors == 0 else 'warning')
    return inserted, errors

def insert_realizado_data(conn, data):
    """Insere dados em realizado_2026_excel"""
    cursor = conn.cursor()
    inserted = 0
    errors = 0
    
    sql = """
        INSERT INTO realizado_2026_excel 
        (data_evento, ordem_op, quantidade, inicio_evento, fim_evento, parada_nomeParada, setup_duracao_minutos, setup_eventos_count)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
        quantidade = VALUES(quantidade),
        inicio_evento = VALUES(inicio_evento),
        fim_evento = VALUES(fim_evento),
        parada_nomeParada = COALESCE(NULLIF(VALUES(parada_nomeParada), ''), parada_nomeParada),
        setup_duracao_minutos = VALUES(setup_duracao_minutos),
        setup_eventos_count = VALUES(setup_eventos_count),
        imported_at = NOW()
    """
    
    for row in data:
        try:
            qtd = float(row['quantidade']) if row['quantidade'] else 0
            setup_eventos = int(row.get('setup_eventos_count') or 0)
            if qtd > 0 or setup_eventos > 0:
                cursor.execute(sql, (
                    row['data_evento'],
                    row['ordem_op'],
                    qtd,
                    row.get('inicio_evento'),
                    row.get('fim_evento'),
                    row.get('parada_nomeParada') or None,
                    row.get('setup_duracao_minutos') or 0,
                    row.get('setup_eventos_count') or 0
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
        started_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        set_sync_status(conn, 'starting', 'Iniciando', 'Conectando ao CODI.', 1, 6, True, 'python', started_at=started_at)

        def report_stage(code, label, detail, index, total, running=True, last_error=None, records_today=0, last_sync_at=None):
            try:
                set_sync_status(
                    conn,
                    code,
                    label,
                    detail,
                    index,
                    total,
                    running,
                    'python',
                    started_at=started_at,
                    finished_at=None if running else datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                    last_error=last_error,
                    records_today=records_today,
                    last_sync_at=last_sync_at
                )
            except Exception as stage_error:
                log(f"Falha ao atualizar status: {stage_error}", 'warning')
        
        # Puxar dados da API
        codi_data = get_codi_data_from_api(report_stage)
        
        realizado_data = codi_data.get('realizado', []) if isinstance(codi_data, dict) else []
        detalhes_data = codi_data.get('detalhes', []) if isinstance(codi_data, dict) else []

        if not realizado_data and not detalhes_data:
            log("Nenhum dado encontrado na API", 'warning')
            report_stage('done', 'Concluído', 'Nenhum dado encontrado na API.', 6, 6, False, records_today=0, last_sync_at=datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            conn.close()
            sys.exit(0)
        
        # Inserir dados
        report_stage('saving_aggregate', 'Gravando agregado', 'Persistindo tabela realizada_2026_excel.', 4, 6, True)
        inserted, errors = insert_realizado_data(conn, realizado_data)
        detalhe_inserted = 0
        detalhe_errors = 0
        if detalhes_data:
            report_stage('saving_events', 'Gravando eventos brutos', 'Persistindo realizado_2026_eventos.', 5, 6, True)
            detalhe_inserted, detalhe_errors = insert_eventos_detalhe(conn, detalhes_data)

        final_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        report_stage(
            'done',
            'Concluído',
            f'Sincronização concluída: {inserted} principais e {detalhe_inserted} brutos.',
            6,
            6,
            False,
            records_today=inserted,
            last_sync_at=final_at
        )
        
        conn.close()
        
        # Resultado final
        log(f"Sincronizacao concluida: {inserted} registros principais e {detalhe_inserted} registros brutos inseridos", 'success')
        
        # Retornar JSON com resultado
        result = {
            'success': True,
            'message': f'Sincronizacao concluida: {inserted} registros principais e {detalhe_inserted} registros brutos dos ultimos 150 dias via API CODI',
            'inserted': inserted,
            'detailInserted': detalhe_inserted,
            'errors': errors
        }
        if detalhe_errors:
            result['detailErrors'] = detalhe_errors
        print(json.dumps(result))
        
    except Exception as e:
        log(f"Erro fatal: {e}", 'error')
        try:
            if 'conn' in locals() and conn:
                set_sync_status(
                    conn,
                    'error',
                    'Erro',
                    str(e),
                    0,
                    6,
                    False,
                    'python',
                    started_at=started_at if 'started_at' in locals() else None,
                    finished_at=datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                    last_error=str(e),
                    records_today=0,
                    last_sync_at=None
                )
        except Exception:
            pass
        sys.exit(1)

if __name__ == '__main__':
    main()
