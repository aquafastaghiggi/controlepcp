<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use App\Models\IntervaloSopro;
use App\Models\Maquina;
use App\Models\ProgramacaoSopro;
use App\Models\ResultadoSequenciaSopro;
use App\Services\CalendarioSoproService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista paginada de programações do Sopro com filtros por máquina e status.
 * Equivalente ao ListaProgramacoes (Envase), adaptado para maquinas/frascos/
 * calendarios_sopro. Diferente do Envase, os turnos não são fixos (T1-T4) —
 * são lidos dinamicamente de intervalos_sopro por máquina, já que cada
 * máquina pode ter seu próprio calendário/turnos cadastrados.
 */
class ListaProgramacoesSopro extends Component
{
    use WithPagination;

    public int $filtroMaquinaId = 0;

    /** IDs das programações arquivadas com detalhes expandidos */
    public array $historicoExpandido = [];

    /** IDs das programações confirmadas com o expand de OPs por dia aberto */
    public array $linhasExpandidas = [];

    /** programacao_id => data (Y-m-d) selecionada no expand de OPs por dia */
    public array $dataSelecionadaPorProgramacao = [];

    protected $listeners = ['programacao-sopro-calculada' => '$refresh'];

    /** A partir deste horário (ou antes deste, na madrugada) um turno é considerado noturno */
    private const HORA_LIMITE_NOTURNO = '17:45';
    private const HORA_LIMITE_MADRUGADA = '06:00';

    public function updatingFiltroMaquinaId(): void
    {
        $this->resetPage();
        $this->historicoExpandido = [];
    }

    public function toggleHistorico(int $programacaoId): void
    {
        if (in_array($programacaoId, $this->historicoExpandido, true)) {
            $this->historicoExpandido = array_values(array_filter(
                $this->historicoExpandido,
                fn (int $id) => $id !== $programacaoId
            ));
        } else {
            $this->historicoExpandido[] = $programacaoId;
        }
    }

    public function toggleExpandir(int $programacaoId): void
    {
        if (in_array($programacaoId, $this->linhasExpandidas, true)) {
            $this->linhasExpandidas = array_values(array_filter(
                $this->linhasExpandidas,
                fn (int $id) => $id !== $programacaoId
            ));
        } else {
            $this->linhasExpandidas[] = $programacaoId;
        }
    }

    /**
     * Chamada explícita via wire:change no select de data do expand — evita
     * depender do binding automático de wire:model.live em array com chave
     * dinâmica (programacao_id), mesmo padrão usado no Envase.
     */
    public function selecionarData(int $programacaoId, string $data): void
    {
        $this->dataSelecionadaPorProgramacao[$programacaoId] = $data;
    }

    public function recalcularProgramacao(int $programacaoId): void
    {
        $programacao = ProgramacaoSopro::with(['itens', 'maquina.calendarioSopro'])->findOrFail($programacaoId);

        // Bloquear se já tem produção iniciada no CODI
        $temProducao = DB::table('codi_eventos')
            ->where('codigo_recurso', $programacao->maquina?->codigo_recurso)
            ->where('tipo_evento', 'PRODUCAO')
            ->whereIn('ordem_producao', $programacao->itens->pluck('numero_op')->filter()->values())
            ->exists();

        if ($temProducao) {
            $this->dispatch('notify', tipo: 'erro', mensagem: 'Não é possível recalcular: esta programação já possui produção registrada no CODI.');
            return;
        }

        if (! class_exists(\App\Actions\CalcularSequenciaSoproAction::class)) {
            $this->dispatch('notify', tipo: 'erro', mensagem: 'Recálculo não disponível para o Sopro ainda.');
            return;
        }

        try {
            DB::transaction(function () use ($programacao, $programacaoId) {
                // Temporariamente volta para calculada para passar a validação
                $programacao->update(['status' => 'calculada']);

                // Recalcula a sequência (sem otimizador — Sopro segue a ordem do Colemar)
                $calcularSequencia = app(\App\Actions\CalcularSequenciaSoproAction::class);
                $calcularSequencia->executar($programacaoId, new \DateTimeImmutable());

                // Restaura para confirmada
                $programacao->update(['status' => 'confirmada', 'calculado_em' => now()]);

                // Atualiza codi_eficiencia_sopro
                app(\App\Services\Codi\EficienciaCalculatorSopro::class)->calcularParaProgramacao($programacaoId);
            });

            $this->dispatch('notify', tipo: 'sucesso', mensagem: 'Programação recalculada com sucesso.');

        } catch (\Throwable $e) {
            $this->dispatch('notify', tipo: 'erro', mensagem: 'Erro ao recalcular: ' . $e->getMessage());
        }
    }

    /**
     * Extrai os IDs de turno selecionados para hoje/data a partir de
     * dias_selecionados — mesmo formato/normalização do Envase.
     *
     * @return array<int, int>
     */
    private function turnosSelecionadosHoje(?array $diasSelecionados, string $dataStr, int $diaIso): array
    {
        if (empty($diasSelecionados)) {
            return [];
        }

        $primeiraChave = (string) array_key_first($diasSelecionados);

        $turnos = strlen($primeiraChave) === 10
            ? ($diasSelecionados[$dataStr]['turnos'] ?? [])
            : ($diasSelecionados[$diaIso] ?? $diasSelecionados[(string) $diaIso] ?? []);

        return is_array($turnos) ? array_values(array_map('intval', $turnos)) : [];
    }

    /**
     * Para as programações confirmadas, verifica se algum turno selecionado
     * para hoje roda à noite (início >= 17:45 ou fim <= 06:00, overnight).
     *
     * @return array<int, string> programacao_id => horário do turno noturno
     */
    private function turnosNoturnosDeHoje(iterable $programacoes): array
    {
        $hoje = Carbon::today();
        $hojeData = $hoje->format('Y-m-d');
        $hojeIso = $hoje->isoWeekday();

        $turnoIdsPorProgramacao = [];
        $todosTurnoIds = [];

        foreach ($programacoes as $prog) {
            if ($prog->status !== 'confirmada') {
                continue;
            }

            $turnoIds = $this->turnosSelecionadosHoje($prog->dias_selecionados, $hojeData, $hojeIso);

            if (empty($turnoIds)) {
                continue;
            }

            $turnoIdsPorProgramacao[$prog->id] = $turnoIds;
            array_push($todosTurnoIds, ...$turnoIds);
        }

        if (empty($todosTurnoIds)) {
            return [];
        }

        $intervalos = IntervaloSopro::whereIn('id', array_unique($todosTurnoIds))->get()->keyBy('id');

        $resultado = [];

        foreach ($turnoIdsPorProgramacao as $programacaoId => $turnoIds) {
            foreach ($turnoIds as $turnoId) {
                $intervalo = $intervalos->get($turnoId);

                if (! $intervalo) {
                    continue;
                }

                $inicio = substr((string) $intervalo->hora_inicio, 0, 5);
                $fim = substr((string) $intervalo->hora_fim, 0, 5);

                $ehNoturno = $inicio >= self::HORA_LIMITE_NOTURNO || $fim <= self::HORA_LIMITE_MADRUGADA;

                if ($ehNoturno) {
                    $resultado[$programacaoId] = "{$inicio}–{$fim}";
                    break;
                }
            }
        }

        return $resultado;
    }

    /**
     * Coleta os turnos únicos (por nome+horário) entre todas as máquinas das
     * programações exibidas — diferente do Envase (T1-T4 fixos), o Sopro lê
     * os turnos reais cadastrados em intervalos_sopro por máquina, já que
     * cada máquina pode ter um calendário diferente.
     *
     * @return array<int, array{chave: string, nome: string, inicio: string, fim: string, noturno: bool}>
     */
    private function turnosUnicos(iterable $programacoes): array
    {
        $turnos = [];

        foreach ($programacoes as $prog) {
            $intervalos = $prog->maquina?->calendarioSopro?->intervalos ?? collect();

            foreach ($intervalos as $intervalo) {
                if (! $intervalo->ativo) {
                    continue;
                }

                $inicio = substr((string) $intervalo->hora_inicio, 0, 5);
                $fim = substr((string) $intervalo->hora_fim, 0, 5);
                $chave = $intervalo->nome . '|' . $inicio . '|' . $fim;

                if (isset($turnos[$chave])) {
                    continue;
                }

                $turnos[$chave] = [
                    'chave' => $chave,
                    'nome' => $intervalo->nome,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'noturno' => $inicio >= self::HORA_LIMITE_NOTURNO || $fim <= self::HORA_LIMITE_MADRUGADA,
                ];
            }
        }

        uasort($turnos, fn ($a, $b) => $a['inicio'] <=> $b['inicio']);

        return array_values($turnos);
    }

    /**
     * Monta, para cada programação confirmada, se cada turno único (coluna da
     * grade) está selecionado para hoje — casando pelo par nome+horário do
     * turno na máquina daquela programação (cada máquina tem seu calendário).
     *
     * @return array<int, array<string, bool>> programacao_id => [chave_turno => bool]
     */
    private function gradeTurnosPorProgramacao(iterable $programacoes, array $turnosUnicos): array
    {
        $hoje = Carbon::today();
        $hojeData = $hoje->format('Y-m-d');
        $hojeIso = $hoje->isoWeekday();

        $resultado = [];

        foreach ($programacoes as $prog) {
            $grade = [];
            foreach ($turnosUnicos as $t) {
                $grade[$t['chave']] = false;
            }

            if ($prog->status === 'confirmada') {
                $turnoIdsHoje = $this->turnosSelecionadosHoje($prog->dias_selecionados, $hojeData, $hojeIso);
                $intervalosMaquina = $prog->maquina?->calendarioSopro?->intervalos ?? collect();

                if (! empty($turnoIdsHoje)) {
                    foreach ($turnosUnicos as $t) {
                        $intervalo = $intervalosMaquina->first(
                            fn ($int) => $int->nome === $t['nome']
                                && substr((string) $int->hora_inicio, 0, 5) === $t['inicio']
                                && substr((string) $int->hora_fim, 0, 5) === $t['fim']
                        );

                        $grade[$t['chave']] = $intervalo !== null && in_array($intervalo->id, $turnoIdsHoje, true);
                    }
                }
            }

            $resultado[$prog->id] = $grade;
        }

        return $resultado;
    }

    /**
     * Lista as datas (Y-m-d) em que a programação tem OPs previstas.
     *
     * @return array<int, string>
     */
    private function diasDisponiveis(ProgramacaoSopro $prog): array
    {
        $diasSelecionados = $prog->dias_selecionados ?? [];

        if (! empty($diasSelecionados)) {
            $primeiraChave = (string) array_key_first($diasSelecionados);

            if (strlen($primeiraChave) === 10) {
                $datas = array_keys($diasSelecionados);
                sort($datas);

                return $datas;
            }
        }

        $range = DB::table('itens_programacao_sopro as ip')
            ->join('codi_eficiencia_sopro as ce', function ($j) use ($prog) {
                $j->on('ce.numero_op', '=', 'ip.numero_op')
                  ->where('ce.programacao_sopro_id', '=', $prog->id);
            })
            ->where('ip.programacao_sopro_id', $prog->id)
            ->selectRaw('MIN(ce.inicio_previsto) as inicio, MAX(ce.fim_previsto) as fim')
            ->first();

        if (! $range || ! $range->inicio || ! $range->fim) {
            return [];
        }

        $cursor = Carbon::parse($range->inicio)->startOfDay();
        $fim = Carbon::parse($range->fim)->startOfDay();
        $datas = [];

        while ($cursor->lte($fim)) {
            $datas[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $datas;
    }

    /**
     * Entre os turnos selecionados pelo Colemar naquela data, retorna apenas
     * os que realmente coincidem com a janela de execução da OP.
     *
     * @return array<int, array{nome: string, inicio: string, fim: string, noturno: bool}>
     */
    private function turnosQueRodamNoDia(
        iterable $intervalosMaquina,
        array $turnoIdsSelecionados,
        \DateTimeImmutable $inicioOverlapOp,
        \DateTimeImmutable $fimOverlapOp,
        string $data
    ): array {
        if (empty($turnoIdsSelecionados)) {
            return [];
        }

        $ativos = [];

        foreach ($intervalosMaquina as $intervalo) {
            if (! in_array($intervalo->id, $turnoIdsSelecionados, true)) {
                continue;
            }

            $inicioStr = substr((string) $intervalo->hora_inicio, 0, 5);
            $fimStr = substr((string) $intervalo->hora_fim, 0, 5);

            [$hIni, $mIni] = array_map('intval', explode(':', $inicioStr));
            [$hFim, $mFim] = array_map('intval', explode(':', $fimStr));

            $diaBase = Carbon::parse($data);
            $turnoInicio = $diaBase->copy()->setTime($hIni, $mIni, 0);
            $turnoFim = $diaBase->copy()->setTime($hFim, $mFim, 0);

            if ($turnoFim->lessThanOrEqualTo($turnoInicio)) {
                $turnoFim->addDay();
            }

            // Turno só conta se sua janela real (nessa data) cruza a janela da OP
            if ($turnoInicio->lt($fimOverlapOp) && $turnoFim->gt($inicioOverlapOp)) {
                $ativos[] = [
                    'nome' => $intervalo->nome,
                    'inicio' => $inicioStr,
                    'fim' => $fimStr,
                    'noturno' => $inicioStr >= self::HORA_LIMITE_NOTURNO || $fimStr <= self::HORA_LIMITE_MADRUGADA,
                ];
            }
        }

        return $ativos;
    }

    /**
     * Monta as OPs da programação que cruzam a janela 06:00→06:00 (dia
     * seguinte) da data informada, com o previsto (cx) de cada uma via
     * taxa_por_hora (frascos) × eficiência. Janela igual à já usada em
     * TvStaticSoproController — diferente do Envase (06:00→03:00), que segue
     * o turno noturno do Envase (T4 termina 03:00).
     *
     * @return array{ops: array<int, array<string, mixed>>, total_prev_cx: int}
     */
    private function detalhesLinha(ProgramacaoSopro $prog, string $data): array
    {
        $diasSelecionados = $prog->dias_selecionados ?? [];
        $diaIso = Carbon::parse($data)->isoWeekday();
        $turnoIdsSelecionados = $this->turnosSelecionadosHoje($diasSelecionados, $data, $diaIso);

        $inicioDia = new \DateTimeImmutable($data . ' 06:00:00');
        $fimDia = new \DateTimeImmutable(Carbon::parse($data)->addDay()->format('Y-m-d') . ' 06:00:00');

        $calendarioId = $prog->maquina?->calendarioSopro?->id;
        $intervalosMaquina = $prog->maquina?->calendarioSopro?->intervalos ?? collect();

        $opsRaw = DB::table('itens_programacao_sopro as ip')
            ->leftJoin('codi_eficiencia_sopro as ce', function ($j) use ($prog) {
                $j->on('ce.numero_op', '=', 'ip.numero_op')
                  ->where('ce.programacao_sopro_id', '=', $prog->id);
            })
            // frascos.sku e itens_programacao_sopro.sku têm collations
            // diferentes — join direto por coluna gera "Illegal mix of
            // collations"; força COLLATE no join (mesmo padrão do
            // TvStaticSoproController).
            ->leftJoin('frascos as frs', DB::raw('frs.sku COLLATE utf8mb4_unicode_ci'), '=', 'ip.sku')
            ->where('ip.programacao_sopro_id', $prog->id)
            ->whereNotNull('ce.inicio_previsto')
            ->whereNotNull('ce.fim_previsto')
            ->where('ce.fim_previsto', '>', $inicioDia->format('Y-m-d H:i:s'))
            ->where('ce.inicio_previsto', '<', $fimDia->format('Y-m-d H:i:s'))
            ->orderBy('ce.inicio_previsto')
            ->select('ip.numero_op', 'ip.sku', 'ip.descricao_produto', 'ip.quantidade',
                     'ce.inicio_previsto', 'ce.fim_previsto', 'frs.taxa_por_hora')
            ->get();

        $calendarioService = app(CalendarioSoproService::class);
        $eficiencia = max(0.0, (float) $prog->eficiencia) / 100;

        $ops = [];
        $totalPrevCx = 0;

        foreach ($opsRaw as $opRow) {
            $inicioOp = new \DateTimeImmutable($opRow->inicio_previsto);
            $fimOp = new \DateTimeImmutable($opRow->fim_previsto);
            $inicioOverlap = $inicioOp < $inicioDia ? $inicioDia : $inicioOp;
            $fimOverlap = $fimOp > $fimDia ? $fimDia : $fimOp;

            if ($fimOverlap <= $inicioOverlap) {
                continue;
            }

            $prevCx = 0;

            // itens_programacao_sopro.quantidade vem do Colemar em milheiros (ex.: 7 =
            // 7.000 frascos) — ×1000 pra comparar/exibir na mesma escala de taxa_por_hora
            // e da produção real do CODI.
            $quantidadeReal = (float) $opRow->quantidade * 1000;

            if ($calendarioId && $opRow->taxa_por_hora) {
                try {
                    $minUteis = $calendarioService->minutosUteisEntre($inicioOverlap, $fimOverlap, $calendarioId, $diasSelecionados);
                    $ritmoOp = (float) $opRow->taxa_por_hora * $eficiencia;
                    $prevCx = min((int) $quantidadeReal, (int) round($ritmoOp * $minUteis / 60));
                } catch (\Throwable $e) {
                    $prevCx = 0;
                }
            }

            $totalPrevCx += $prevCx;

            $ops[] = [
                'numero_op' => $opRow->numero_op,
                'sku' => $opRow->sku,
                'descricao_produto' => $opRow->descricao_produto,
                'quantidade' => $quantidadeReal,
                'inicio_previsto' => $opRow->inicio_previsto,
                'fim_previsto' => $opRow->fim_previsto,
                'turnos' => $this->turnosQueRodamNoDia($intervalosMaquina, $turnoIdsSelecionados, $inicioOverlap, $fimOverlap, $data),
                'prev_cx' => $prevCx,
            ];
        }

        return ['ops' => $ops, 'total_prev_cx' => $totalPrevCx];
    }

    /**
     * Para cada programação com o expand aberto, monta os dias disponíveis,
     * a data selecionada (hoje por padrão, se disponível) e o detalhe de OPs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function detalhesExpandidos(iterable $programacoes): array
    {
        $resultado = [];
        $hojeStr = Carbon::today()->format('Y-m-d');

        foreach ($programacoes as $prog) {
            if (! in_array($prog->id, $this->linhasExpandidas, true)) {
                continue;
            }

            $diasDisponiveis = $this->diasDisponiveis($prog);

            $dataSelecionada = $this->dataSelecionadaPorProgramacao[$prog->id] ?? null;

            if ($dataSelecionada === null || ! in_array($dataSelecionada, $diasDisponiveis, true)) {
                $dataSelecionada = in_array($hojeStr, $diasDisponiveis, true)
                    ? $hojeStr
                    : ($diasDisponiveis[0] ?? null);
            }

            if ($dataSelecionada === null) {
                $resultado[$prog->id] = [
                    'dias_disponiveis' => [],
                    'data_selecionada' => null,
                    'ops' => [],
                    'total_prev_cx' => 0,
                ];
                continue;
            }

            $detalhe = $this->detalhesLinha($prog, $dataSelecionada);

            $resultado[$prog->id] = [
                'dias_disponiveis' => $diasDisponiveis,
                'data_selecionada' => $dataSelecionada,
                'ops' => $detalhe['ops'],
                'total_prev_cx' => $detalhe['total_prev_cx'],
            ];
        }

        return $resultado;
    }

    /**
     * Soma o previsto de hoje (taxa_por_hora × eficiência × minutos úteis na
     * janela 06:00→06:00, capado pela quantidade da OP) para TODAS as
     * programações confirmadas do sistema — mesma fórmula do Envase
     * (ListaProgramacoes::totalEstimadoHoje), adaptada às tabelas do Sopro.
     */
    private function totalEstimadoHoje(): int
    {
        $hoje = Carbon::today();
        $amanha = $hoje->copy()->addDay();
        $inicioDia = new \DateTimeImmutable($hoje->format('Y-m-d') . ' 06:00:00');
        $fimDia = new \DateTimeImmutable($amanha->format('Y-m-d') . ' 06:00:00');

        $ops = DB::table('itens_programacao_sopro as ip')
            ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
            ->join('maquinas as m', 'm.id', '=', 'p.maquina_id')
            ->leftJoin('codi_eficiencia_sopro as ce', function ($j) {
                $j->on('ce.numero_op', '=', 'ip.numero_op')
                  ->on('ce.programacao_sopro_id', '=', 'p.id');
            })
            ->leftJoin('calendarios_sopro as cal', 'cal.maquina_id', '=', 'm.id')
            ->leftJoin('frascos as frs', DB::raw('frs.sku COLLATE utf8mb4_unicode_ci'), '=', 'ip.sku')
            ->where('p.status', 'confirmada')
            ->where('m.ativo', true)
            ->whereNotNull('ce.inicio_previsto')
            ->whereNotNull('ce.fim_previsto')
            ->where('ce.fim_previsto', '>', $inicioDia->format('Y-m-d H:i:s'))
            ->where('ce.inicio_previsto', '<', $fimDia->format('Y-m-d H:i:s'))
            ->select('ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto',
                     'p.eficiencia', 'p.dias_selecionados', 'cal.id as calendario_id',
                     'frs.taxa_por_hora')
            ->get();

        $calendarioService = app(CalendarioSoproService::class);
        $total = 0;

        foreach ($ops as $opRow) {
            if (! $opRow->calendario_id || ! $opRow->taxa_por_hora) {
                continue;
            }

            $diasSel = json_decode($opRow->dias_selecionados ?? '[]', true);
            $inicioOp = new \DateTimeImmutable($opRow->inicio_previsto);
            $fimOp = new \DateTimeImmutable($opRow->fim_previsto);
            $inicioOverlap = $inicioOp < $inicioDia ? $inicioDia : $inicioOp;
            $fimOverlap = $fimOp > $fimDia ? $fimDia : $fimOp;

            if ($fimOverlap <= $inicioOverlap) {
                continue;
            }

            try {
                $minUteis = $calendarioService->minutosUteisEntre($inicioOverlap, $fimOverlap, $opRow->calendario_id, $diasSel);

                if ($minUteis <= 0) {
                    continue;
                }

                $eficiencia = max(0.0, (float) $opRow->eficiencia) / 100;
                $ritmoOp = (float) $opRow->taxa_por_hora * $eficiencia;
                // itens_programacao_sopro.quantidade vem em milheiros — ×1000
                $total += min((int) ($opRow->quantidade * 1000), (int) round($ritmoOp * $minUteis / 60));
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $total;
    }

    private function carregarHistorico(): array
    {
        return ProgramacaoSopro::with(['itens', 'maquina'])
            ->historico()
            ->join('maquinas', 'maquinas.id', '=', 'programacoes_sopro.maquina_id')
            ->reorder('maquinas.codigo', 'asc')
            ->orderByDesc('programacoes_sopro.arquivada_em')
            ->select('programacoes_sopro.*')
            ->when($this->filtroMaquinaId, fn ($q) => $q->where('programacoes_sopro.maquina_id', $this->filtroMaquinaId))
            ->get()
            ->groupBy('maquina_id')
            ->map(fn ($group) => $group->values())
            ->toArray();
    }

    public function render()
    {
        $programacoes = ProgramacaoSopro::with(['maquina.calendarioSopro.intervalos'])
            ->join('maquinas', 'maquinas.id', '=', 'programacoes_sopro.maquina_id')
            ->select('programacoes_sopro.*')
            ->withCount(['resultados'])
            ->addSelect([
                'itens_sequenciados_count' => ResultadoSequenciaSopro::selectRaw('COUNT(DISTINCT item_id)')
                    ->whereColumn('programacao_sopro_id', 'programacoes_sopro.id')
                    ->where('tipo', 'producao'),
            ])
            ->whereNotIn('status', ['rascunho', 'arquivada'])
            ->orderBy('maquinas.codigo', 'asc')
            ->when($this->filtroMaquinaId, fn ($q) => $q->where('programacoes_sopro.maquina_id', $this->filtroMaquinaId))
            ->paginate(10);

        $historico = $this->carregarHistorico();
        $maquinas = Maquina::where('ativo', true)->orderBy('codigo')->get();

        $turnosNoturnos = $this->turnosNoturnosDeHoje($programacoes);
        $turnosUnicos = $this->turnosUnicos($programacoes);
        $gradeTurnos = $this->gradeTurnosPorProgramacao($programacoes, $turnosUnicos);
        $detalhesExpandidos = $this->detalhesExpandidos($programacoes);

        // Soma os previstos de todas as linhas atualmente expandidas (cada uma
        // na sua própria data selecionada) — não é um total do dia único.
        $totalGeralPrevCx = array_sum(array_column($detalhesExpandidos, 'total_prev_cx'));

        // Produção estimada do dia: soma de TODAS as programações confirmadas.
        $totalEstimadoHoje = $this->totalEstimadoHoje();

        return view('livewire.sopro.lista-programacoes-sopro', compact(
            'programacoes',
            'historico',
            'maquinas',
            'turnosNoturnos',
            'turnosUnicos',
            'gradeTurnos',
            'detalhesExpandidos',
            'totalGeralPrevCx',
            'totalEstimadoHoje'
        ));
    }
}
