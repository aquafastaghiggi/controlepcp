<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Linha;
use Illuminate\View\View;

/**
 * Entrega as views de cada seção do sistema.
 * Toda a lógica reativa fica nos componentes Livewire.
 */
class PaginaController extends Controller
{
    public function dashboard(): View
    {
        return view('dashboard.index', $this->contextoBase());
    }

    public function selecionarProgramacao(): View
    {
        return view('programacao.selecionar');
    }

    public function programacoes(): View
    {
        return view('programacoes.index', $this->contextoBase());
    }

    public function programacoesSopro(): View
    {
        return view('programacao.sopro');
    }

    public function historico(): View
    {
        return view('historico.index', $this->contextoBase());
    }

    public function calendario(): View
    {
        return view('calendario.index', $this->contextoBase());
    }

    public function produtos(): View
    {
        return view('produtos.index', $this->contextoBase());
    }

    public function exportarProdutos()
    {
        $produtos = \App\Models\Produto::with('linha')
            ->orderBy('linha_id')
            ->orderBy('sku')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produtos');

        // Cabeçalho
        $headers = ['Linha', 'SKU', 'Descrição', 'Taxa (cx/h)', 'Ref. Setup', 'Ativo'];
        foreach ($headers as $i => $header) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF374151');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setARGB('FFFFFFFF');
        }

        // Dados
        $row = 2;
        foreach ($produtos as $p) {
            $sheet->setCellValue('A' . $row, $p->linha->codigo ?? 'Sem linha');
            $sheet->setCellValue('B' . $row, $p->sku);
            $sheet->setCellValue('C' . $row, $p->descricao);
            $sheet->setCellValue('D' . $row, $p->taxa_por_hora ?? 0);
            $sheet->setCellValue('E' . $row, $p->referencia_setup ?? '');
            $sheet->setCellValue('F' . $row, $p->ativo ? 'Sim' : 'Não');
            $row++;
        }

        // Auto-width nas colunas
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'produtos_' . date('Y-m-d') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function matrizes(): View
    {
        return view('produtos.matrizes', $this->contextoBase());
    }

    public function fotos(): View
    {
        return view('produtos.fotos', $this->contextoBase());
    }

    public function desempenho(): View
    {
        return view('desempenho.index', $this->contextoBase());
    }

    public function acompanharProducao(): View
    {
        return view('acompanhar.index', $this->contextoBase());
    }

    public function ordens(): View
    {
        return view('ordens.index', $this->contextoBase());
    }

    public function divergencias(): View
    {
        return view('planejamento.divergencias', $this->contextoBase());
    }

    public function soproMaquinas(): View
    {
        return view('sopro.maquinas', $this->contextoBase());
    }

    public function soproFrascos(): View
    {
        return view('sopro.frascos', $this->contextoBase());
    }

    public function soproFotosFrascos(): View
    {
        return view('sopro.frascos-fotos', $this->contextoBase());
    }

    public function soproMatrizSetup(): View
    {
        return view('sopro.matriz-setup');
    }

    public function soproAcompanhar(): View
    {
        return view('sopro.acompanhar-producao');
    }

    public function soproProgramacoes(): View
    {
        return view('sopro.programacoes');
    }

    public function soproCalendario(): View
    {
        return view('sopro.calendario');
    }

    public function soproResultado(int $id)
    {
        $programacao = \App\Models\ProgramacaoSopro::with(['maquina', 'itens', 'resultados'])
            ->findOrFail($id);
        return view('sopro.resultado', compact('programacao'));
    }

    public function soproImprimir(int $id)
    {
        $programacao = \App\Models\ProgramacaoSopro::with(['maquina', 'itens', 'resultados'])
            ->findOrFail($id);
        return view('sopro.imprimir', compact('programacao'));
    }

    public function imprimirProgramacao(int $id): View
    {
        $programacao = \App\Models\Programacao::with([
            'linha',
            'itens',
            'resultados' => fn ($q) => $q->orderBy('inicio'),
        ])->findOrFail($id);

        return view('programacoes.imprimir', compact('programacao'));
    }

    /** Contexto mínimo compartilhado por todas as views (alimenta o layout). */
    private function contextoBase(): array
    {
        $linha = Linha::where('ativo', true)->first();

        return compact('linha');
    }
}
