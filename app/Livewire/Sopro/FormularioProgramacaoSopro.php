<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use App\Actions\CalcularSequenciaSoproAction;
use App\Actions\CriarProgramacaoSoproAction;
use App\Models\ProgramacaoSopro;
use App\Services\Codi\EficienciaCalculatorSopro;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class FormularioProgramacaoSopro extends Component
{
    // ── Estado ───────────────────────────────────────────────────────────────
    public bool   $excelCarregado    = false;
    public string $etapaAtual        = 'entrada';
    public bool   $processando       = false;

    public ?int   $maquinaId         = null;
    public string $maquinaNome       = '';
    public string $dataInicio        = '';
    public float  $eficiencia        = 70.0;
    public string $abaSelecionada    = '';
    public string $arquivoNome       = '';
    public array  $abasDisponiveis   = [];
    public array  $abasCalculadas    = [];
    public array  $itens             = [];
    public array  $resultados        = [];
    public array  $resumo            = [];
    public array  $erros             = [];
    public ?int   $programacaoId     = null;
    public ?int   $programacaoSalvaId = null;

    // Turnos
    public array $turnosDisponiveis  = [];
    public array $configuracaoDias   = [];
    public array $proximosDias       = [];

    public function mount(): void
    {
        $this->dataInicio = now()->format('Y-m-d\TH:i');
    }

    // ── Receber dados do Excel ────────────────────────────────────────────────
    #[On('ordensImportadasSopro')]
    public function receberOrdensExcel(array $payload): void
    {
        $ordens = $payload['ordens'] ?? [];

        $this->itens = array_map(static function (array $o, int $i): array {
            return [
                'sku'        => $o['sku'],
                'descricao'  => $o['descricao'],
                'quantidade' => (float) $o['quantidade'],
                'sequencia'  => $i + 1,
                'prazo'      => $o['data_programada'] ?? null,
                'numero_op'  => $o['numero_op'],
            ];
        }, $ordens, array_keys($ordens));

        $this->maquinaId        = $payload['maquina_id'] ?? null;
        $this->maquinaNome      = $payload['maquina_nome'] ?? '';
        $this->abasDisponiveis  = $payload['todas_abas'] ?? [];
        $this->abaSelecionada   = $payload['aba_selecionada'] ?? '';
        $this->arquivoNome      = $payload['arquivo_nome'] ?? '';
        $this->excelCarregado   = true;

        $this->carregarTurnosDaMaquina();

        $this->etapaAtual          = 'entrada';
        $this->resultados          = [];
        $this->resumo              = [];
        $this->erros               = [];
        $this->programacaoId       = null;
        $this->programacaoSalvaId  = null;
    }

    // ── Turnos ────────────────────────────────────────────────────────────────
    public function carregarTurnosDaMaquina(): void
    {
        if (! $this->maquinaId) {
            $this->turnosDisponiveis = [];
            $this->configuracaoDias  = [];
            return;
        }

        $maquina = \App\Models\Maquina::with([
            'calendarioSopro.intervalosAtivos',
        ])->find($this->maquinaId);

        if (! $maquina?->calendarioSopro) {
            $this->turnosDisponiveis = [];
            $this->configuracaoDias  = [];
            return;
        }

        $this->turnosDisponiveis = $maquina->calendarioSopro->intervalosAtivos
            ->map(fn ($i) => [
                'id'          => $i->id,
                'nome'        => $i->nome,
                'hora_inicio' => $i->hora_inicio,
                'hora_fim'    => $i->hora_fim,
                'ordem'       => $i->ordem,
                // ADM vem antes dos turnos operacionais
                'ordem_exibicao' => str_starts_with(strtoupper($i->nome), 'T. ADM') ? 0 : 1,
            ])
            ->sortBy(['ordem_exibicao', 'ordem'])
            ->values()
            ->toArray();

        $this->inicializarConfiguracaoDias();
    }

    public function toggleDia(string $data): void
    {
        if (! isset($this->configuracaoDias[$data])) return;
        $config = $this->configuracaoDias;
        $novoEstado = ! $config[$data]['ativo'];
        $config[$data]['ativo'] = $novoEstado;
        if ($novoEstado) {
            foreach ($config[$data]['turnos'] as $idx => $_) {
                $config[$data]['turnos'][$idx]['ativo'] = true;
            }
        }
        $this->configuracaoDias = $config;
    }

    public function toggleTurnoDia(string $data, int $turnoId): void
    {
        if (! isset($this->configuracaoDias[$data])) return;
        if (! $this->configuracaoDias[$data]['ativo']) return;
        $config = $this->configuracaoDias;
        foreach ($config[$data]['turnos'] as $idx => $t) {
            if ((int) $t['id'] === $turnoId) {
                $config[$data]['turnos'][$idx]['ativo'] = ! $t['ativo'];
                break;
            }
        }
        $algumAtivo = collect($config[$data]['turnos'])->contains(fn ($t) => $t['ativo']);
        if (! $algumAtivo) $config[$data]['ativo'] = false;
        $this->configuracaoDias = $config;
    }

    public function trocarAba(string $nomeAba): void
    {
        if ($nomeAba === $this->abaSelecionada) return;
        $this->dispatch('trocarAbaSopro', aba: $nomeAba);
    }

    // ── Cálculo ───────────────────────────────────────────────────────────────
    public function calcular(
        CriarProgramacaoSoproAction  $criar,
        CalcularSequenciaSoproAction $calcularSequencia
    ): void {
        $this->validate([
            'maquinaId'  => 'required|integer|min:1',
            'dataInicio' => 'required|date',
            'eficiencia' => 'required|numeric|min:1|max:150',
            'itens'      => 'required|array|min:1',
        ], [
            'maquinaId.required'  => 'Selecione uma máquina.',
            'dataInicio.required' => 'Informe a data e hora de início.',
            'eficiencia.min'      => 'Eficiência mínima é 1%.',
            'eficiencia.max'      => 'Eficiência máxima é 150%.',
            'itens.min'           => 'Importe o Excel primeiro.',
        ]);

        $this->processando = true;
        $this->erros       = [];

        try {
            $programacao = $this->resolverProgramacao($criar);

            $resultado = $calcularSequencia->executar(
                $programacao->id,
                new DateTimeImmutable()
            );

            $this->programacaoId = $resultado['programacao']->id;
            $this->resultados    = $this->serializarResultados($resultado['resultados']);
            $this->resumo        = $resultado['resumo'];
            $this->erros         = [];
            $this->etapaAtual    = 'resultado';

            $this->salvar();

            $this->dispatch('gantt-sopro-atualizado', resultados: $this->resultados);

        } catch (Throwable $e) {
            $this->erros = [$e->getMessage()];
        } finally {
            $this->processando = false;
        }
    }

    public function salvar(): void
    {
        if (! $this->programacaoId) return;

        $programacaoId = $this->programacaoId;

        DB::transaction(function () use ($programacaoId): void {
            $programacao = ProgramacaoSopro::lockForUpdate()->find($programacaoId);
            if ($programacao) {
                ProgramacaoSopro::where('maquina_id', $programacao->maquina_id)
                    ->where('status', 'confirmada')
                    ->where('id', '!=', $programacao->id)
                    ->update(['status' => 'arquivada', 'arquivada_em' => now()]);
            }
            ProgramacaoSopro::where('id', $programacaoId)->update([
                'status'       => 'confirmada',
                'calculado_em' => now(),
            ]);
        });

        // Popula codi_eficiencia_sopro para os KPIs funcionarem imediatamente
        try {
            app(EficienciaCalculatorSopro::class)
                ->calcularParaProgramacao($this->programacaoId);
        } catch (\Throwable $e) {
            \Log::warning('EficienciaCalculatorSopro falhou ao confirmar programação', [
                'programacao_id' => $this->programacaoId,
                'erro'           => $e->getMessage(),
            ]);
        }

        $this->programacaoSalvaId = $this->programacaoId;
        if ($this->abaSelecionada && ! in_array($this->abaSelecionada, $this->abasCalculadas)) {
            $this->abasCalculadas[] = $this->abaSelecionada;
        }
        session()->flash('sucesso', "✅ Programação Sopro #{$this->programacaoId} salva.");
    }

    public function recalcular(): void
    {
        $this->etapaAtual          = 'entrada';
        $this->resultados          = [];
        $this->resumo              = [];
        $this->erros               = [];
        $this->programacaoId       = null;
        $this->programacaoSalvaId  = null;
    }

    public function render()
    {
        return view('livewire.sopro.formulario-programacao-sopro');
    }

    // ── Privados ──────────────────────────────────────────────────────────────
    private function resolverProgramacao(CriarProgramacaoSoproAction $criar): ProgramacaoSopro
    {
        if ($this->programacaoSalvaId) {
            $prog = ProgramacaoSopro::findOrFail($this->programacaoSalvaId);
            $prog->update([
                'data_inicio_planejada' => $this->dataInicio,
                'eficiencia'            => $this->eficiencia,
                'dias_selecionados'     => $this->montarDiasSelecionados(),
                'status'                => 'rascunho',
            ]);
            $prog->resultados()->delete();
            return $prog;
        }

        return $criar->executar([
            'maquina_id'            => $this->maquinaId,
            'numero_op'             => $this->itens[0]['numero_op'] ?? null,
            'data_inicio_planejada' => $this->dataInicio,
            'eficiencia'            => $this->eficiencia,
            'dias_selecionados'     => $this->montarDiasSelecionados(),
            'origem'                => 'excel',
            'itens'                 => $this->itens,
        ]);
    }

    private function inicializarConfiguracaoDias(): void
    {
        $proximosDias       = $this->gerarProximosDias();
        $this->proximosDias = $proximosDias;
        $config             = [];

        foreach ($proximosDias as $diaInfo) {
            $ehSabado = $diaInfo['dia_semana'] === 6;
            $ativo    = ! $ehSabado;
            $turnos   = [];

            foreach ($this->turnosDisponiveis as $turno) {
                $turnoAtivo = $ativo;
                $turnos[] = ['id' => $turno['id'], 'ativo' => $turnoAtivo];
            }

            $config[$diaInfo['data']] = [
                'ativo'      => $ativo,
                'dia_semana' => $diaInfo['dia_semana'],
                'turnos'     => $turnos,
            ];
        }

        $this->configuracaoDias = $config;
    }

    private function gerarProximosDias(): array
    {
        $dias      = [];
        $data      = now()->startOfDay()->copy();
        $count     = 0;
        $nomesDias = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

        while ($count < 10) {
            $carbonDow = (int) $data->dayOfWeek;
            $isoDow    = $carbonDow === 0 ? 7 : $carbonDow;

            if ($isoDow !== 7) {
                $dias[] = [
                    'data'       => $data->format('Y-m-d'),
                    'label_dia'  => $nomesDias[$isoDow],
                    'label_data' => $data->format('d/m'),
                    'dia_semana' => $isoDow,
                ];
                $count++;
            }
            $data->addDay();
        }

        return $dias;
    }

    private function montarDiasSelecionados(): array
    {
        $resultado = [];
        foreach ($this->configuracaoDias as $data => $config) {
            if (! $config['ativo']) continue;
            $turnosAtivos = array_values(array_map(
                fn ($t) => (int) $t['id'],
                array_filter($config['turnos'], fn ($t) => $t['ativo'])
            ));
            if (! empty($turnosAtivos)) {
                $resultado[$data] = [
                    'dia_semana' => $config['dia_semana'],
                    'turnos'     => $turnosAtivos,
                ];
            }
        }
        return $resultado;
    }

    private function serializarResultados(array $resultados): array
    {
        return array_map(function (array $r): array {
            if (isset($r['inicio']) && $r['inicio'] instanceof \DateTimeInterface) {
                $r['inicio'] = $r['inicio']->format('Y-m-d H:i:s');
            }
            if (isset($r['fim']) && $r['fim'] instanceof \DateTimeInterface) {
                $r['fim'] = $r['fim']->format('Y-m-d H:i:s');
            }
            return $r;
        }, $resultados);
    }
}
