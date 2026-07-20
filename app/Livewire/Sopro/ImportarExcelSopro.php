<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use App\Services\ImportacaoExcelSoproService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class ImportarExcelSopro extends Component
{
    use WithFileUploads;

    public $arquivo;
    public array $abas          = [];
    public string $abaSelecionada = '';
    public string $arquivoNome  = '';
    public bool   $processado   = false;
    public string $erro         = '';
    public string $aviso        = '';

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
        $this->aviso      = '';

        try {
            $resultado = app(ImportacaoExcelSoproService::class)
                ->importar($this->arquivo->getRealPath());

            $this->abas        = $resultado['abas'];
            $this->arquivoNome = $this->arquivo->getClientOriginalName();
            $this->processado  = true;

            // Validar contexto — se tiver aba com "Linha" é planilha do Envase
            $temPadraoLinha = collect($this->abas)->keys()->contains(
                fn($nome) => preg_match('/linha\s*\d+|^LN\d+$/i', trim($nome))
            );
            if ($temPadraoLinha) {
                $this->aviso = '⚠️ Esta planilha parece ser do Envase (contém abas com "Linha"). Verifique se está importando o arquivo correto para o Sopro.';
            }

            // Selecionar primeira aba com máquina cadastrada
            foreach ($this->abas as $nome => $aba) {
                if ($aba['maquina_existe']) {
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

    #[On('trocarAbaSopro')]
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
        $this->abas           = [];
        $this->arquivoNome    = '';
        $this->abaSelecionada = '';
        $this->processado     = false;
        $this->erro           = '';
        $this->aviso          = '';
        $this->arquivo        = null;
    }

    public function render()
    {
        return view('livewire.sopro.importar-excel-sopro');
    }

    private function despacharAba(string $nomeAba): void
    {
        $aba = $this->abas[$nomeAba];

        $ordens = collect($aba['ordens'])
            ->filter(fn ($o) => $o['sku_cadastrado'])
            ->values()
            ->toArray();

        $todasAbas = collect($this->abas)
            ->map(fn ($a, $nome) => [
                'nome'           => $nome,
                'maquina_codigo' => $a['maquina_codigo'],
                'maquina_nome'   => $a['maquina_nome'],
                'maquina_existe' => $a['maquina_existe'],
                'total_ordens'   => count($a['ordens']),
                'skus_faltando'  => count(array_filter($a['ordens'], fn ($o) => ! $o['sku_cadastrado'])),
            ])
            ->values()
            ->toArray();

        $this->dispatch('ordensImportadasSopro', [
            'ordens'          => $ordens,
            'maquina_id'      => $aba['maquina_id'],
            'maquina_nome'    => $aba['maquina_nome'],
            'todas_abas'      => $todasAbas,
            'aba_selecionada' => $nomeAba,
            'arquivo_nome'    => $this->arquivoNome,
        ]);
    }
}
