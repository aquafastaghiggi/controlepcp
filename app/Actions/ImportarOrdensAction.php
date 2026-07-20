<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Linha;
use App\Models\Programacao;
use App\Services\ImportacaoExcelService;
use App\Services\IntegracaoOrdemService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Importa ordens de produção para uma programação nova a partir de duas origens:
 *   - Excel (XLSX): arquivo enviado pelo usuário
 *   - API ERP:      busca automática via IntegracaoOrdemService
 *
 * Em ambos os casos, cria uma Programacao em status 'rascunho' com os itens,
 * pronta para ser sequenciada pela CalcularSequenciaAction.
 */
class ImportarOrdensAction
{
    public function __construct(
        private readonly ImportacaoExcelService $importacaoExcel,
        private readonly CriarProgramacaoAction $criarProgramacao,
    ) {}

    /**
     * Importa ordens de um arquivo Excel e cria a programação.
     *
     * O Excel deve ter aba "Ordens" com: numero_op, sku, descricao, quantidade
     * A aba "Produtos" e "MatrizSetup" (se presentes) também são importadas.
     */
    public function fromExcel(Linha $linha, UploadedFile $arquivo, array $opcoes = []): Programacao
    {
        // Importa produtos e matriz de setup se presentes no Excel
        $resultadoImportacao = $this->importacaoExcel->importar($arquivo);

        // Extrai as ordens da aba específica de ordens (se houver)
        $ordens = $this->extrairOrdensDoExcel($arquivo);

        return $this->criarProgramacao->executar([
            'linha_id'              => $linha->id,
            'numero_op'             => $opcoes['numero_op'] ?? null,
            'descricao'             => $opcoes['descricao'] ?? 'Importação Excel',
            'data_inicio_planejada' => $opcoes['data_inicio'] ?? now()->format('Y-m-d H:i:s'),
            'eficiencia'            => $opcoes['eficiencia'] ?? 100.0,
            'origem'                => 'excel',
            'itens'                 => $ordens,
        ]);
    }

    /**
     * Busca ordens do ERP e cria a programação.
     * Retorna null se não houver ordens disponíveis.
     */
    public function fromErp(Linha $linha, IntegracaoOrdemService $erp, array $opcoes = []): ?Programacao
    {
        $ordens = $erp->buscarOrdens($linha->codigo);

        if ($ordens->isEmpty()) {
            return null;
        }

        $itens = $ordens->values()->map(fn ($ordem, $index) => [
            'sku'        => $ordem['sku'],
            'quantidade' => $ordem['quantidade'],
            'sequencia'  => $index + 1,
        ])->toArray();

        return $this->criarProgramacao->executar([
            'linha_id'              => $linha->id,
            'numero_op'             => $opcoes['numero_op'] ?? null,
            'descricao'             => "Importação ERP — {$ordens->count()} ordens",
            'data_inicio_planejada' => $opcoes['data_inicio'] ?? now()->format('Y-m-d H:i:s'),
            'eficiencia'            => $opcoes['eficiencia'] ?? 100.0,
            'origem'                => 'api_erp',
            'itens'                 => $itens,
        ]);
    }

    private function extrairOrdensDoExcel(UploadedFile $arquivo): array
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return [];
        }

        $planilha = \PhpOffice\PhpSpreadsheet\IOFactory::load($arquivo->getRealPath());
        $aba      = $planilha->getSheetByName('Ordens');

        if ($aba === null) {
            return [];
        }

        $ordens = [];

        foreach ($aba->getRowIterator(2) as $indice => $linhaExcel) {
            $celulas = $linhaExcel->getCellIterator();
            $celulas->setIterateOnlyExistingCells(false);
            $valores = [];

            foreach ($celulas as $celula) {
                $valores[] = $celula->getValue();
            }

            [$numeroOp, $sku, $descricao, $quantidade] = array_pad($valores, 4, null);

            $sku = trim((string) $sku);

            if (empty($sku)) {
                continue;
            }

            $ordens[] = [
                'sku'        => $sku,
                'quantidade' => (float) $quantidade,
                'sequencia'  => $indice,
            ];
        }

        return $ordens;
    }
}
