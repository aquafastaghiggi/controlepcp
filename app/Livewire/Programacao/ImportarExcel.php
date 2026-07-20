<?php

declare(strict_types=1);

namespace App\Livewire\Programacao;

use App\Services\ImportacaoExcelService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Gerencia o upload e o cache do Excel de PCP.
 *
 * Responsabilidades:
 *   - Fazer o upload e processar o arquivo via ImportacaoExcelService
 *   - Manter TODAS as abas em memória ($abas) após o upload
 *   - Ao selecionar uma aba, despachar 'ordensImportadas' para o FormularioProgramacao
 *   - Responder ao evento 'trocarAbaExcel' (sem re-upload)
 */
class ImportarExcel extends Component
{
    use WithFileUploads;

    public $arquivo;

    /** Todas as abas lidas — mantidas em memória para troca sem re-upload */
    public array $abas = [];

    public string $abaSelecionada = '';
    public string $arquivoNome   = '';
    public bool   $processado    = false;
    public string $erro          = '';

    public function updatedArquivo(): void
    {
        $this->processarArquivo();
    }

    public function processarArquivo(): void
    {
        $this->validate([
            'arquivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ], [
            'arquivo.required' => 'Selecione um arquivo.',
            'arquivo.mimes'    => 'O arquivo deve ser .xlsx ou .xls.',
            'arquivo.max'      => 'Tamanho máximo: 10 MB.',
        ]);

        $this->abas       = [];
        $this->processado = false;
        $this->erro       = '';

        try {
            $resultado = app(ImportacaoExcelService::class)
                ->importar($this->arquivo->getRealPath());

            $this->abas       = $resultado['abas'];
            $this->arquivoNome = $this->arquivo->getClientOriginalName();
            $this->processado = true;

            // Selecionar e despachar a primeira aba com linha cadastrada
            foreach ($this->abas as $nome => $aba) {
                if ($aba['linha_existe']) {
                    $this->abaSelecionada = $nome;
                    $this->despacharAba($nome);
                    break;
                }
            }

        } catch (Throwable $e) {
            $this->erro = 'Erro ao processar o arquivo: ' . $e->getMessage();
        }
    }

    public function selecionarAba(string $nomeAba): void
    {
        if (! isset($this->abas[$nomeAba]) || $nomeAba === $this->abaSelecionada) {
            return;
        }
        $this->abaSelecionada = $nomeAba;
        $this->despacharAba($nomeAba);
    }

    /** Recebe pedido de troca vindo do FormularioProgramacao (sem re-upload) */
    #[On('trocarAbaExcel')]
    public function trocarAba(string $aba): void
    {
        if (! isset($this->abas[$aba])) {
            return;
        }
        $this->abaSelecionada = $aba;
        $this->despacharAba($aba);
    }

    public function resetar(): void
    {
        $this->abas          = [];
        $this->arquivoNome   = '';
        $this->abaSelecionada = '';
        $this->processado    = false;
        $this->erro          = '';
        $this->arquivo       = null;
    }

    public function render()
    {
        return view('livewire.programacao.importar-excel');
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function despacharAba(string $nomeAba): void
    {
        $aba = $this->abas[$nomeAba];

        $ordens = collect($aba['ordens'])
            ->filter(fn ($o) => $o['sku_cadastrado'])
            ->values()
            ->toArray();

        $todasAbas = collect($this->abas)
            ->map(fn ($a, $nome) => [
                'nome'          => $nome,
                'linha_codigo'  => $a['linha_codigo'],
                'linha_nome'    => $a['linha_nome'],
                'linha_existe'  => $a['linha_existe'],
                'total_ordens'  => count($a['ordens']),
                'skus_faltando' => count(array_filter($a['ordens'], fn ($o) => ! $o['sku_cadastrado'])),
            ])
            ->values()
            ->toArray();

        $this->dispatch('ordensImportadas', [
            'ordens'          => $ordens,
            'linha_id'        => $aba['linha_id'],
            'linha_nome'      => $aba['linha_nome'],
            'todas_abas'      => $todasAbas,
            'aba_selecionada' => $nomeAba,
            'arquivo_nome'    => $this->arquivoNome,
        ]);
    }
}
