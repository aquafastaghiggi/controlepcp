<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Linha;
use App\Models\MatrizSetup;
use App\Models\Produto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importa o catálogo completo de produtos do grupo 20 (produtos acabados)
 * combinando duas fontes:
 *
 *   1. API CIGAM (?produtos) — código + descrição atualizados em tempo real
 *   2. controlepcp_sandbox   — taxa_por_hora, referencia_setup e linha_id calibrados
 *
 * Fluxo:
 *   buscarProdutosCigam()  → 195 produtos grupo 20 da API
 *   buscarProdutosSandbox() → 143 produtos com taxa + linha
 *   construirMapaLinhas()  → sandbox.prd_linha_id → v2.linhas.id
 *   importar()             → updateOrCreate em produtos + MatrizSetup
 */
class ImportarProdutosService
{
    private const URL_API   = 'https://web.aquafast.com.br/api_ds/zeev.php';
    private const USUARIO   = 'cigam';
    private const SENHA     = 'aquacigam';
    private const PREFIXO_GRUPO_20 = '20';

    // ─── Ponto de entrada ────────────────────────────────────────────────────

    /**
     * Executa a importação completa.
     *
     * @return array{importados: int, atualizados: int, sem_taxa: int, erros: string[]}
     */
    public function importar(): array
    {
        $resultado = ['importados' => 0, 'atualizados' => 0, 'sem_taxa' => 0, 'erros' => []];

        try {
            $cigam   = $this->buscarProdutosCigam();
            $sandbox = $this->buscarProdutosSandbox();
            $mapaLinhas = $this->construirMapaLinhas();

            DB::transaction(function () use ($cigam, $sandbox, $mapaLinhas, &$resultado) {
                foreach ($cigam as $item) {
                    $this->processarProduto($item, $sandbox, $mapaLinhas, $resultado);
                }
            });

        } catch (Throwable $e) {
            $resultado['erros'][] = 'Falha geral: ' . $e->getMessage();
            Log::error('ImportarProdutosService::importar falhou', ['erro' => $e->getMessage()]);
        }

        return $resultado;
    }

    /**
     * Importa apenas a matriz de setup do sandbox para o v2.
     *
     * @return array{importados: int, erros: string[]}
     */
    public function importarMatrizSetup(): array
    {
        $resultado = ['importados' => 0, 'erros' => []];

        try {
            $mapaLinhas  = $this->construirMapaLinhas();
            $entradasSandbox = DB::connection('sandbox')
                ->table('mat_matriz_setup')
                ->get();

            // SKUs efetivamente importados no v2 — evita FK violation
            $skusExistentes = Produto::pluck('sku')->flip();

            DB::transaction(function () use ($entradasSandbox, $mapaLinhas, $skusExistentes, &$resultado) {
                foreach ($entradasSandbox as $entrada) {
                    $linhaIdV2 = $mapaLinhas[$entrada->mat_linha_id] ?? null;
                    if (! $linhaIdV2) {
                        continue;
                    }

                    // Pular entradas que referenciam SKUs não importados (ex: descontinuados no CIGAM)
                    if (! isset($skusExistentes[$entrada->mat_sku_origem]) ||
                        ! isset($skusExistentes[$entrada->mat_sku_destino])) {
                        continue;
                    }

                    MatrizSetup::updateOrCreate(
                        [
                            'linha_id'    => $linhaIdV2,
                            'sku_origem'  => $entrada->mat_sku_origem,
                            'sku_destino' => $entrada->mat_sku_destino,
                        ],
                        ['duracao_minutos' => $entrada->mat_duracao_minutos]
                    );

                    $resultado['importados']++;
                }
            });

        } catch (Throwable $e) {
            $resultado['erros'][] = 'Falha na matriz de setup: ' . $e->getMessage();
            Log::error('ImportarProdutosService::importarMatrizSetup falhou', ['erro' => $e->getMessage()]);
        }

        return $resultado;
    }

    // ─── Fontes de dados ─────────────────────────────────────────────────────

    /**
     * Busca produtos do grupo 20 na API CIGAM.
     * Filtra apenas os que têm mcod_produto começando com "20".
     *
     * @return Collection<int, array{sku: string, descricao: string}>
     */
    private function buscarProdutosCigam(): Collection
    {
        // A API exige ?produtos sem valor (não ?produtos=) — URL literal
        $resposta = Http::withBasicAuth(self::USUARIO, self::SENHA)
            ->withOptions(['verify' => false])
            ->timeout(30)
            ->get(self::URL_API . '?produtos');

        $todos = $resposta->json('data') ?? [];

        return collect($todos)
            ->filter(fn ($p) => str_starts_with((string) ($p['mcod_produto'] ?? ''), self::PREFIXO_GRUPO_20))
            ->map(fn ($p) => [
                'sku'      => (string) $p['mcod_produto'],
                'descricao' => (string) $p['mproduto'],
            ])
            ->values();
    }

    /**
     * Carrega todos os produtos do sandbox indexados pelo SKU.
     *
     * @return Collection<string, object> SKU → registro do sandbox
     */
    private function buscarProdutosSandbox(): Collection
    {
        return DB::connection('sandbox')
            ->table('prd_produtos')
            ->get()
            ->keyBy('prd_sku');
    }

    // ─── Mapeamento de linhas ─────────────────────────────────────────────────

    /**
     * Constrói o mapa: sandbox.prd_linha_id (int) → v2.linhas.id (int).
     *
     * Duas etapas:
     *   1. sandbox.lin_id → lin_codigo  (ex: 2 → 'LN01')
     *   2. lin_codigo → v2.linhas.id    (ex: 'LN01' → 3)
     *
     * @return array<int, int>  [sandbox_linha_id => v2_linha_id]
     */
    private function construirMapaLinhas(): array
    {
        // Etapa 1: sandbox lin_id → lin_codigo
        $linhasSandbox = DB::connection('sandbox')
            ->table('lin_linhas')
            ->get(['lin_id', 'lin_codigo'])
            ->keyBy('lin_id');

        // Etapa 2: lin_codigo → v2 id
        $linhasV2 = Linha::all(['id', 'codigo'])
            ->keyBy(fn ($l) => strtoupper($l->codigo));

        $mapa = [];
        foreach ($linhasSandbox as $linId => $linSandbox) {
            $codigoUpper = strtoupper(trim($linSandbox->lin_codigo));
            if (isset($linhasV2[$codigoUpper])) {
                $mapa[(int) $linId] = $linhasV2[$codigoUpper]->id;
            }
        }

        return $mapa;
    }

    // ─── Processamento individual ─────────────────────────────────────────────

    private function processarProduto(
        array      $itemCigam,
        Collection $sandbox,
        array      $mapaLinhas,
        array      &$resultado
    ): void {
        $sku     = $itemCigam['sku'];
        $dadosSb = $sandbox->get($sku);

        // Se não existe no sandbox, produto ainda sem dados de produção
        if (! $dadosSb) {
            $resultado['sem_taxa']++;
            return;
        }

        $linhaId = $mapaLinhas[$dadosSb->prd_linha_id] ?? null;
        if (! $linhaId) {
            $resultado['erros'][] = "SKU {$sku}: linha_id={$dadosSb->prd_linha_id} sem correspondência no v2";
            return;
        }

        $existia = Produto::where('sku', $sku)->exists();

        Produto::updateOrCreate(
            ['sku' => $sku],
            [
                'descricao'        => $itemCigam['descricao'],
                'taxa_por_hora'    => (float) $dadosSb->prd_taxa_por_hora,
                'referencia_setup' => $dadosSb->prd_referencia_setup,
                'linha_id'         => $linhaId,
                'ativo'            => true,
            ]
        );

        if ($existia) {
            $resultado['atualizados']++;
        } else {
            $resultado['importados']++;
        }
    }
}
