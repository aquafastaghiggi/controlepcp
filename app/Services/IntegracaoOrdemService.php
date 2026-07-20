<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Busca ordens de produção do ERP CIGAM via API REST.
 *
 * Endpoint confirmado: GET zeev.php?ops={numero_op}
 * Campos reais da API (confirmados em 10/06/2026):
 *   op_numero  → número da OP
 *   op_cod     → SKU do produto
 *   op_nome    → descrição do produto
 *   op_qtde    → quantidade (formato BR: "2.000,000")
 *   op_um      → unidade de medida (CX, LT, FR, etc.)
 *   op_op      → número da OP (redundante com op_numero)
 *
 * Uso: buscarOrdem('201298')  → dados da OP 201298
 *      verificarConexao()     → testa se a API responde
 */
class IntegracaoOrdemService
{
    private const URL_API = 'https://web.aquafast.com.br/api_ds/zeev.php';
    private const USUARIO = 'cigam';
    private const SENHA   = 'aquacigam';

    /**
     * Busca uma OP específica pelo número.
     *
     * @return array{numero_op: string, sku: string, descricao: string, quantidade: float, unidade: string}|null
     *         null se a OP não for encontrada ou a API falhar
     */
    public function buscarOrdem(string $numeroOp): ?array
    {
        $numeroOp = trim($numeroOp);

        if ($numeroOp === '') {
            return null;
        }

        try {
            // Endpoint: ?ops={numero} — o valor após = é o número da OP
            $resposta = Http::withBasicAuth(self::USUARIO, self::SENHA)
                ->timeout(15)
                ->get(self::URL_API . '?ops=' . $numeroOp);

            if (! $resposta->successful()) {
                Log::warning('CIGAM API: resposta não-200 ao buscar OP', [
                    'op'     => $numeroOp,
                    'status' => $resposta->status(),
                ]);
                return null;
            }

            $dados = $resposta->json('data') ?? [];

            if (empty($dados)) {
                return null;
            }

            return $this->mapearOrdem($dados[0]);

        } catch (Throwable $e) {
            Log::error('CIGAM API: falha ao buscar OP ' . $numeroOp, ['erro' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Busca múltiplas OPs de uma vez.
     *
     * @param  string[] $numeros  Lista de números de OP
     * @return Collection<int, array>  Apenas as OPs encontradas
     */
    public function buscarOrdens(array $numeros): Collection
    {
        $ordens = collect();

        foreach ($numeros as $numero) {
            $ordem = $this->buscarOrdem((string) $numero);
            if ($ordem !== null) {
                $ordens->push($ordem);
            }
        }

        return $ordens;
    }

    /**
     * Verifica se a API está acessível.
     * Usa uma OP fictícia — o que importa é o HTTP status, não o resultado.
     *
     * @return array{conectado: bool, mensagem: string}
     */
    public function verificarConexao(): array
    {
        try {
            $resposta = Http::withBasicAuth(self::USUARIO, self::SENHA)
                ->timeout(10)
                ->get(self::URL_API . '?ops=0');

            if ($resposta->successful()) {
                return ['conectado' => true, 'mensagem' => 'API CIGAM acessível'];
            }

            return [
                'conectado' => false,
                'mensagem'  => 'API CIGAM respondeu HTTP ' . $resposta->status(),
            ];

        } catch (Throwable $e) {
            return ['conectado' => false, 'mensagem' => 'Sem conexão com a API CIGAM: ' . $e->getMessage()];
        }
    }

    /**
     * Mapeia os campos reais da API para o modelo interno.
     *
     * Este é o ÚNICO método a alterar se a estrutura do JSON mudar.
     */
    private function mapearOrdem(array $item): array
    {
        return [
            'numero_op'  => (string) ($item['op_numero'] ?? $item['op_op'] ?? ''),
            'sku'        => (string) ($item['op_cod'] ?? ''),
            'descricao'  => (string) ($item['op_nome'] ?? ''),
            'quantidade' => $this->parsearQuantidade((string) ($item['op_qtde'] ?? '0')),
            'unidade'    => (string) ($item['op_um'] ?? ''),
        ];
    }

    /**
     * Converte quantidade no formato BR ("2.000,000") para float (2000.0).
     */
    private function parsearQuantidade(string $valor): float
    {
        // Remove separador de milhar (ponto) e troca vírgula decimal por ponto
        $normalizado = str_replace(['.', ','], ['', '.'], $valor);
        return (float) $normalizado;
    }
}
