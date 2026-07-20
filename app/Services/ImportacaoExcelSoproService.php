<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Frasco;
use App\Models\Maquina;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Importa ordens de produção Sopro a partir do arquivo Excel padrão do Colemar.
 *
 * Estrutura esperada por aba (igual ao Envase):
 *   A: OP | B: Data Programada | C: Hr_Ini | D: Hr_Fim
 *   E: Liberação | F: Sequencia | G: Codigo Material (SKU sem zero à esq.)
 *   H: Descrição | I: Desc Categoria | J: Quantidade | K: Frascos
 *
 * Cada aba representa uma máquina de sopro.
 * Padrão de aba: "Maq. 1", "Maq.3", "Maq. 10" etc. → MAQ01, MAQ03, MAQ10
 * SKU vem sem zero à esquerda: "7030109" → "07030109"
 */
class ImportacaoExcelSoproService
{
    public function importar(string $caminhoArquivo): array
    {
        $resultado = ['abas' => [], 'erros' => []];

        $spreadsheet = IOFactory::load($caminhoArquivo);

        // Pré-carregar SKUs de frascos cadastrados
        $skusCadastrados = Frasco::pluck('sku')->flip()->toArray();

        foreach ($spreadsheet->getAllSheets() as $planilha) {
            $nomeAba      = trim($planilha->getTitle());
            $codigoMaquina = $this->extrairCodigoMaquina($nomeAba);
            $maquina       = Maquina::where('codigo', $codigoMaquina)->first();

            $abaInfo = [
                'nome_aba'       => $nomeAba,
                'maquina_codigo' => $codigoMaquina,
                'maquina_id'     => $maquina?->id,
                'maquina_nome'   => $maquina?->nome ?? $nomeAba,
                'maquina_existe' => $maquina !== null,
                'ordens'         => [],
            ];

            $linhas = $planilha->toArray(
                nullValue:         null,
                calculateFormulas: true,
                formatData:        false,
                returnCellRef:     true,
            );
            array_shift($linhas); // remove cabeçalho

            foreach ($linhas as $celulas) {
                if (empty(trim((string) ($celulas['A'] ?? '')))) {
                    continue;
                }

                $ordem = $this->processarLinha($celulas, $maquina?->id);
                if ($ordem === null) {
                    continue;
                }

                $ordem['sku_cadastrado'] = isset($skusCadastrados[$ordem['sku']]);
                $abaInfo['ordens'][]     = $ordem;
            }

            usort($abaInfo['ordens'], static function (array $a, array $b): int {
                $sa = $a['sequencia'] === 999 ? PHP_INT_MAX : $a['sequencia'];
                $sb = $b['sequencia'] === 999 ? PHP_INT_MAX : $b['sequencia'];
                return $sa <=> $sb;
            });

            $resultado['abas'][$nomeAba] = $abaInfo;
        }

        return $resultado;
    }

    private function processarLinha(array $celulas, ?int $maquinaId): ?array
    {
        $numeroOp   = trim((string) ($celulas['A'] ?? ''));
        $skuRaw     = trim((string) ($celulas['G'] ?? ''));
        $quantidade = $this->parsearNumero($celulas['J'] ?? 0);

        if ($numeroOp === '' || $skuRaw === '' || $quantidade <= 0) {
            return null;
        }

        // Normalizar SKU: "7030109" → "07030109"
        $sku = $this->normalizarSku($skuRaw);

        $dataProgramada = null;
        $dataSerial     = $celulas['B'] ?? null;
        if (is_numeric($dataSerial) && (float) $dataSerial > 0) {
            try {
                $dataProgramada = ExcelDate::excelToDateTimeObject((float) $dataSerial)
                    ->format('Y-m-d');
            } catch (Throwable) {
                // data inválida — manter null
            }
        }

        $sequencia = (int) ($celulas['F'] ?? 999);
        if ($sequencia <= 0) {
            $sequencia = 999;
        }

        return [
            'numero_op'       => $numeroOp,
            'sku'             => $sku,
            'descricao'       => trim((string) ($celulas['H'] ?? '')),
            'categoria'       => trim((string) ($celulas['I'] ?? '')),
            'quantidade'      => $quantidade,
            'frascos'         => $this->parsearNumero($celulas['K'] ?? 0),
            'sequencia'       => $sequencia,
            'data_programada' => $dataProgramada,
            'maquina_id'      => $maquinaId,
        ];
    }

    /**
     * Normaliza SKU removendo zeros à esquerda inconsistentes.
     * "7030109" → "07030109" (família 07, sempre 8 dígitos)
     */
    private function normalizarSku(string $sku): string
    {
        // Remove parte decimal se vier como float string "7030109.0"
        $sku = preg_replace('/\.0+$/', '', $sku);

        // Se começar com "703" (faltando o zero inicial da família 07)
        if (preg_match('/^703\d{4}$/', $sku)) {
            return '0' . $sku;
        }

        return $sku;
    }

    /**
     * "Maq. 1" → "MAQ01" | "Maq.3" → "MAQ03" | "Maq. 10" → "MAQ10"
     */
    private function extrairCodigoMaquina(string $nomeAba): string
    {
        if (preg_match('/^MAQ\d+$/i', trim($nomeAba))) {
            return strtoupper(trim($nomeAba));
        }

        if (preg_match('/maq\.?\s*0*(\d+)/i', $nomeAba, $m)) {
            return 'MAQ' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        return strtoupper(str_replace([' ', '.'], '', $nomeAba));
    }

    private function parsearNumero(mixed $valor): float
    {
        if (is_float($valor) || is_int($valor)) {
            return (float) $valor;
        }

        $str = trim((string) $valor);
        if ($str === '' || $str === '-') {
            return 0.0;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $str)) {
            return (float) str_replace(['.', ','], ['', '.'], $str);
        }

        if (preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $str)) {
            return (float) str_replace(',', '', $str);
        }

        return (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', $str));
    }
}
