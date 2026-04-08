import requests
import pandas as pd
from datetime import datetime, timedelta

data_inicial = datetime(2026, 1, 1)
data_final = datetime.now() - timedelta(days=1)
operacao = 20
url_base = "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEventoConsolidado"
usuario = "marcos.brun"
senha = "Eb035611!"

lista_datas = [data_inicial + timedelta(days=x) for x in range((data_final - data_inicial).days + 1)]
todos_os_dados = []

def expand_dict(prefix, obj, destino):
    if isinstance(obj, dict):
        for k, v in obj.items():
            if isinstance(v, dict):
                expand_dict(f"{prefix}_{k}", v, destino)
            else:
                destino[f"{prefix}_{k}"] = v

def expand_list(prefix, lst, destino):
    if lst and isinstance(lst, list):
        primeiro = lst[0]
        if isinstance(primeiro, dict):
            expand_dict(prefix, primeiro, destino)

for data in lista_datas:
    data_str = data.strftime('%Y-%m-%d')
    url = f"{url_base}?data={data_str}&operacao={operacao}"
    print(f"Consultando: {url}")
    resposta = requests.get(url, auth=(usuario, senha))
    if resposta.status_code == 200:
        try:
            json_data = resposta.json()
            if "data" in json_data and json_data["data"]:
                for item in json_data["data"]:
                    linha = {}
                    # copia campos simples
                    for k, v in item.items():
                        if k not in ['turno', 'grandeza', 'parada', 'funcionarios', 'ordens']:
                            linha[k] = v
                    linha['data_consulta'] = data_str
                    # expande campos aninhados
                    expand_dict('turno', item.get('turno'), linha)
                    expand_dict('grandeza', item.get('grandeza'), linha)
                    expand_dict('parada', item.get('parada'), linha)
                    expand_list('funcionario', item.get('funcionarios'), linha)
                    expand_list('ordem', item.get('ordens'), linha)
                    todos_os_dados.append(linha)
            else:
                print(f"Sem dados para {data_str}")
        except Exception as e:
            print(f"Erro ao ler JSON da data {data_str}: {e}")
    else:
        print(f"Erro {resposta.status_code} em {url}")

if todos_os_dados:
    df = pd.DataFrame(todos_os_dados)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    arquivo_saida = f"relatorio_api_{timestamp}.xlsx"
    df.to_excel(arquivo_saida, index=False)
    print(f"Arquivo salvo como {arquivo_saida}")
    print(f"Total de registros: {len(todos_os_dados)}")
else:
    print("Nenhum dado foi extraído.")
