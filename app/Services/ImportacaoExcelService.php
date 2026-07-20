<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Linha;
use App\Models\Produto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Importa ordens de produção a partir do arquivo Excel padrão do PCP.
 *
 * Estrutura esperada por aba:
 *   A: OP | B: Data Programada (serial Excel) | C: Hr_Ini | D: Hr_Fim
 *   E: Liberação | F: Sequencia | G: Codigo Material (SKU)
 *   H: Descrição | I: Desc Categoria | J: Quantidade | K: Frascos
 *
 * Cada aba representa uma linha de produção.
 * Sequência 999 = sem ordem definida (vai para o final).
 * Suporta .xls e .xlsx.
 */
class ImportacaoExcelService
{
    /**
     * Lê o arquivo e retorna todas as abas com suas ordens.
     *
     * @return array{abas: array<string, array>, erros: string[]}
     */
    public function importar(string $caminhoArquivo): array
    {
        $resultado = ['abas' => [], 'erros' => []];

        $spreadsheet = IOFactory::load($caminhoArquivo);

        // Pré-carregar SKUs cadastrados para evitar N+1
        $skusCadastrados = Produto::pluck('sku')->flip()->toArray();

        foreach ($spreadsheet->getAllSheets() as $planilha) {
            $nomeAba     = trim($planilha->getTitle());
            $codigoLinha = $this->extrairCodigoLinha($nomeAba);
            $linha       = Linha::where('codigo', $codigoLinha)->first();

            $abaInfo = [
                'nome_aba'     => $nomeAba,
                'linha_codigo' => $codigoLinha,
                'linha_id'     => $linha?->id,
                'linha_nome'   => $linha?->nome ?? $nomeAba,
                'linha_existe' => $linha !== null,
                'ordens'       => [],
            ];

            $linhas = $planilha->toArray(
                nullValue:          null,
                calculateFormulas:  true,
                formatData:         false,  // false = retorna float, não string formatada "2.000,00"
                returnCellRef:      true,
            );
            array_shift($linhas); // remove cabeçalho (linha 1)

            foreach ($linhas as $celulas) {
                if (empty(trim((string) ($celulas['A'] ?? '')))) {
                    continue;
                }

                $ordem = $this->processarLinha($celulas, $linha?->id);
                if ($ordem === null) {
                    continue;
                }

                $ordem['sku_cadastrado'] = isset($skusCadastrados[$ordem['sku']]);
                $abaInfo['ordens'][]     = $ordem;
            }

            // Sequências definidas primeiro; 999 vai para o final
            usort($abaInfo['ordens'], static function (array $a, array $b): int {
                $sa = $a['sequencia'] === 999 ? PHP_INT_MAX : $a['sequencia'];
                $sb = $b['sequencia'] === 999 ? PHP_INT_MAX : $b['sequencia'];
                return $sa <=> $sb;
            });

            $resultado['abas'][$nomeAba] = $abaInfo;
        }

        return $resultado;
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function processarLinha(array $celulas, ?int $linhaId): ?array
    {
        $numeroOp   = trim((string) ($celulas['A'] ?? ''));
        $sku        = trim((string) ($celulas['G'] ?? ''));
        $quantidade = $this->parsearNumero($celulas['J'] ?? 0);

        if ($numeroOp === '' || $sku === '' || $quantidade <= 0) {
            return null;
        }

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
            'linha_id'        => $linhaId,
        ];
    }

    /**
     * "Linha 01" → "LN01" | "Linha 1" → "LN01" | "LN05" → "LN05"
     */
    private function extrairCodigoLinha(string $nomeAba): string
    {
        if (preg_match('/^LN\d+$/i', trim($nomeAba))) {
            return strtoupper(trim($nomeAba));
        }

        if (preg_match('/linha\s*0*(\d+)/i', $nomeAba, $m)) {
            return 'LN' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        return strtoupper(str_replace(' ', '', $nomeAba));
    }

    /**
     * Parseia número que pode vir como float (PhpSpreadsheet com formatData:false)
     * ou como string formatada BR "2.000,00" / US "2,000.00" (fallback).
     */
    private function parsearNumero(mixed $valor): float
    {
        if (is_float($valor) || is_int($valor)) {
            return (float) $valor;
        }

        $str = trim((string) $valor);
        if ($str === '' || $str === '-') {
            return 0.0;
        }

        // Formato BR: ponto como milhar, vírgula como decimal → "2.000,00"
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $str)) {
            return (float) str_replace(['.', ','], ['', '.'], $str);
        }

        // Formato US: vírgula como milhar, ponto como decimal → "2,000.00"
        if (preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $str)) {
            return (float) str_replace(',', '', $str);
        }

        // Fallback: remover tudo exceto dígitos e ponto decimal
        return (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', $str));
    }
}
