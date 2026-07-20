<?php

declare(strict_types=1);

namespace App\Services\Codi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para a API REST do CODI.
 * Autenticação: HTTP Basic. Encoding: ISO-8859-1 → UTF-8.
 */
class CodiClient
{
    private const ENDPOINTS = [
        'performance'          => '/action/ger/webservice/rest/performance',
        'eventos'              => '/action/ger/webservice/rest/relatorioEvento',
        'eventos_consolidado'  => '/action/ger/webservice/rest/relatorioEventoConsolidado',
        'recursos'             => '/action/ger/webservice/rest/recurso',
        'calendario'           => '/action/ger/webservice/rest/calendarioFabril',
    ];

    private const EMPRESA_CODIGO  = 1;
    private const PAGE_SIZE       = 500;
    private const MAX_TENTATIVAS  = 3;

    public function __construct(
        private readonly string $baseUrl = 'http://192.168.8.246:8080',
        private readonly string $usuario = 'Aghiggi',
        private readonly string $senha   = '@Ag0351@',
    ) {}

    /**
     * Busca todos os registros de performance (cadência de produção).
     * 422 registros — seguro carregar tudo.
     */
    public function getPerformance(array $params = []): array
    {
        return $this->buscarTodos('performance', $params);
    }

    /**
     * Busca recursos (linhas de produção) cadastrados no CODI.
     */
    public function getRecursos(): array
    {
        return $this->buscarTodos('recursos');
    }

    /**
     * Busca todos os eventos de um dia via relatorioEventoConsolidado.
     *
     * Este endpoint aceita ?data=YYYY-MM-DD e retorna apenas os eventos daquele dia
     * em uma única resposta (sem paginação necessária). Muito mais eficiente que
     * /relatorioEvento que retorna 597k registros sem suporte real a filtro de data.
     *
     * @param string $data  Data no formato 'YYYY-MM-DD'.
     * @return array        Lista de eventos do dia.
     */
    public function getEventosDia(string $data): array
    {
        $url      = $this->baseUrl . self::ENDPOINTS['eventos_consolidado'];
        $resposta = $this->requisitar($url, ['data' => $data]);

        return $resposta['data'] ?? [];
    }

    /**
     * @deprecated Use getEventosDia() em loop dia a dia — evita OOM.
     * Mantido apenas para compatibilidade caso necessário.
     */
    public function processarEventosPaginas(callable $callback): int
    {
        $url    = $this->baseUrl . self::ENDPOINTS['eventos'];
        $pagina = 0;
        $total  = 0;

        do {
            $resposta = $this->requisitar($url, [
                'empresaCodigo' => self::EMPRESA_CODIGO,
                'pageNumber'    => $pagina,
                'pageSize'      => self::PAGE_SIZE,
            ]);

            if ($resposta === null) break;

            $dados = $resposta['data'] ?? [];
            if (empty($dados)) break;

            $continuar = $callback($dados);
            $total    += count($dados);
            $pagina++;

            unset($dados, $resposta);

            if ($continuar === false) break;

        } while (true);

        return $total;
    }

    /**
     * Verifica conectividade com o servidor CODI.
     */
    public function testarConexao(): array
    {
        try {
            $resposta = Http::withBasicAuth($this->usuario, $this->senha)
                ->timeout(10)
                ->get($this->baseUrl . self::ENDPOINTS['recursos'], [
                    'empresaCodigo' => self::EMPRESA_CODIGO,
                    'pageNumber'    => 0,
                    'pageSize'      => 1,
                ]);

            if ($resposta->successful()) {
                return ['conectado' => true, 'mensagem' => 'CODI respondendo normalmente.'];
            }

            return ['conectado' => false, 'mensagem' => 'HTTP ' . $resposta->status()];

        } catch (\Throwable $e) {
            return ['conectado' => false, 'mensagem' => $e->getMessage()];
        }
    }

    /**
     * Busca todos os registros de um endpoint com paginação automática.
     * Usar apenas para endpoints com volume baixo (performance, recursos).
     */
    private function buscarTodos(string $endpoint, array $params = []): array
    {
        $url    = $this->baseUrl . self::ENDPOINTS[$endpoint];
        $todos  = [];
        $pagina = 0;

        do {
            $resposta = $this->requisitar($url, array_merge($params, [
                'empresaCodigo' => self::EMPRESA_CODIGO,
                'pageNumber'    => $pagina,
                'pageSize'      => self::PAGE_SIZE,
            ]));

            if ($resposta === null) break;

            $dados = $resposta['data'] ?? [];
            if (empty($dados)) break;

            $todos  = array_merge($todos, $dados);
            $pagina++;

        } while (count($dados) === self::PAGE_SIZE);

        return $todos;
    }

    /**
     * Executa uma requisição HTTP com retry automático e conversão de encoding.
     */
    private function requisitar(string $url, array $params): ?array
    {
        for ($tentativa = 1; $tentativa <= self::MAX_TENTATIVAS; $tentativa++) {
            try {
                $resposta = Http::withBasicAuth($this->usuario, $this->senha)
                    ->timeout(30)
                    ->get($url, $params);

                if (!$resposta->successful()) {
                    Log::warning("CODI: HTTP {$resposta->status()} em {$url}");
                    continue;
                }

                $corpo = $resposta->body();

                if (!mb_check_encoding($corpo, 'UTF-8')) {
                    $corpo = mb_convert_encoding($corpo, 'UTF-8', 'ISO-8859-1');
                }

                return json_decode($corpo, true);

            } catch (\Throwable $e) {
                Log::warning("CODI: tentativa {$tentativa} falhou", ['erro' => $e->getMessage()]);
                if ($tentativa === self::MAX_TENTATIVAS) {
                    throw $e;
                }
                sleep(1);
            }
        }

        return null;
    }
}
