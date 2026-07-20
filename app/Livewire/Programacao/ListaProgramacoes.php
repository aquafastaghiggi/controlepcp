<?php

declare(strict_types=1);

namespace App\Livewire\Programacao;

use App\Models\Intervalo;
use App\Models\Programacao;
use App\Models\ResultadoSequencia;
use App\Services\CalendarioService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista paginada de programações com filtros por linha e status.
 * Atualiza automaticamente quando uma nova programação é calculada.
 */
class ListaProgramacoes extends Component
{
    use WithPagination;

    public int $filtroLinhaId = 0;

    /** IDs das programações arquivadas com detalhes expandidos */
    public array $historicoExpandido = [];

    /** IDs das programações confirmadas com o expand de OPs por dia aberto */
    public array $linhasExpandidas = [];

    /** programacao_id => data (Y-m-d) selecionada no expand de OPs por dia */
    public array $dataSelecionadaPorProgramacao = [];

    protected $listeners = ['programacao-calculada' => '$refresh'];

    /** A partir deste horário (ou antes deste, na madrugada) um turno é considerado noturno */
    private const HORA_LIMITE_NOTURNO = '17:45';
    private const HORA_LIMITE_MADRUGADA = '06:00';

    /** Turnos fixos exibidos como colunas na grade, identificados pelo horário real do calendário */
    private const TURNOS_FIXOS = [
        ['label' => 'T1', 'inicio' => '07:05', 'fim' => '11:30', 'noturno' => false],
        ['label' => 'T2', 'inicio' => '13:27', 'fim' => '17:45', 'noturno' => false],
        ['label' => 'T3', 'inicio' => '17:45', 'fim' => '22:00', 'noturno' => true],
        ['label' => 'T4', 'inicio' => '23:00', 'fim' => '03:00', 'noturno' => true],
    ];

    public function updatingFiltroLinhaId(): void
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
     * dinâmica (programacao_id), mais confiável nesse cenário.
     */
    public function selecionarData(int $programacaoId, string $data): void
    {
        $this->dataSelecionadaPorProgramacao[$programacaoId] = $data;
    }

    /**
     * Extrai os IDs de turno selecionados para hoje a partir de dias_selecionados,
     * suportando os dois formatos gerados pelo formulário ao longo do tempo
     * (mesma normalização usada em SequenciadorService::normalizarDiasOverride):
     *
     * Formato data (novo):  ['Y-m-d' => ['dia_semana' => int, 'turnos' => [id, ...]]]
     * Formato legado:       [diaSemanaIso => [turnoId, ...]] (1=Segunda … 7=Domingo)
     *
     * @return array<int, int>
     */
    private function turnosSelecionadosHoje(?array $diasSelecionados, string $hojeData, int $hojeIso): array
    {
        if (empty($diasSelecionados)) {
            return [];
        }

        $primeiraChave = (string) array_key_first($diasSelecionados);

        $turnos = strlen($primeiraChave) === 10
            ? ($diasSelecionados[$hojeData]['turnos'] ?? [])
            : ($diasSelecionados[$hojeIso] ?? $diasSelecionados[(string) $hojeIso] ?? []);

        return is_array($turnos) ? array_values(array_map('intval', $turnos)) : [];
    }

    /**
     * Para as programações confirmadas, verifica se algum turno selecionado
     * para hoje roda à noite (início >= 17:45 ou fim <= 06:00, overnight).
     *
     * @return array<int, string> programacao_id => horário do turno noturno (ex: "17:45–22:00")
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

        $intervalos = Intervalo::whereIn('id', array_unique($todosTurnoIds))->get()->keyBy('id');

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
     * Monta, para cada programação confirmada, se cada um dos 4 turnos fixos
     * (T1–T4) do calendário da linha está selecionado para hoje.
     *
     * @return array<int, array<int, bool>> programacao_id => [0 => bool (T1), 1 => bool (T2), ...]
     */
    private function gradeTurnosPorProgramacao(iterable $programacoes): array
    {
        $hoje = Carbon::today();
        $hojeData = $hoje->format('Y-m-d');
        $hojeIso = $hoje->isoWeekday();

        $resultado = [];

        foreach ($programacoes as $prog) {
            $grade = array_fill(0, count(self::TURNOS_FIXOS), false);

            if ($prog->status === 'confirmada') {
                $turnoIdsHoje = $this->turnosSelecionadosHoje($prog->dias_selecionados, $hojeData, $hojeIso);
                $intervalosLinha = $prog->linha?->calendario?->intervalos ?? collect();

                if (! empty($turnoIdsHoje)) {
                    foreach (self::TURNOS_FIXOS as $indice => $turnoFixo) {
                        $intervalo = $intervalosLinha->first(
                            fn ($int) => substr((string) $int->hora_inicio, 0, 5) === $turnoFixo['inicio']
                                && substr((string) $int->hora_fim, 0, 5) === $turnoFixo['fim']
                        );

                        $grade[$indice] = $intervalo !== null && in_array($intervalo->id, $turnoIdsHoje, true);
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
     * Usa as chaves de dias_selecionados quando no formato por data (novo).
     * Formato legado/vazio: deriva do intervalo real das OPs (inicio/fim previsto
     * em codi_eficiencia), já que não há datas explícitas nesse formato.
     *
     * @return array<int, string>
     */
    private function diasDisponiveis(Programacao $prog): array
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

        $range = DB::table('itens_programacao as ip')
            ->join('codi_eficiencia as ce', function ($j) use ($prog) {
                $j->on('ce.numero_op', '=', 'ip.numero_op')
                  ->where('ce.programacao_id', '=', $prog->id);
            })
            ->where('ip.programacao_id', $prog->id)
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
     * Entre os TURNOS_FIXOS selecionados pelo Colemar naquela data, retorna
     * apenas os que realmente coincidem com a janela de execução da OP.
     *
     * @param array<int, int> $turnoIdsSelecionados
     * @return array<int, array{label: string, inicio: string, fim: string, noturno: bool}>
     */
    private function turnosQueRodamNoDia(
        iterable $intervalosLinha,
        array $turnoIdsSelecionados,
        \DateTimeImmutable $inicioOverlapOp,
        \DateTimeImmutable $fimOverlapOp,
        string $data
    ): array {
        if (empty($turnoIdsSelecionados)) {
            return [];
        }

        $ativos = [];

        foreach (self::TURNOS_FIXOS as $turnoFixo) {
            $intervalo = collect($intervalosLinha)->first(
                fn ($int) => substr((string) $int->hora_inicio, 0, 5) === $turnoFixo['inicio']
                    && substr((string) $int->hora_fim, 0, 5) === $turnoFixo['fim']
            );

            if (! $intervalo || ! in_array($intervalo->id, $turnoIdsSelecionados, true)) {
                continue;
            }

            [$hIni, $mIni] = array_map('intval', explode(':', $turnoFixo['inicio']));
            [$hFim, $mFim] = array_map('intval', explode(':', $turnoFixo['fim']));

            $diaBase = Carbon::parse($data);
            $turnoInicio = $diaBase->copy()->setTime($hIni, $mIni, 0);
            $turnoFim = $diaBase->copy()->setTime($hFim, $mFim, 0);

            if ($turnoFim->lessThanOrEqualTo($turnoInicio)) {
                $turnoFim->addDay();
            }

            // Turno só conta se sua janela real (nessa data) cruza a janela da OP
            if ($turnoInicio->lt($fimOverlapOp) && $turnoFim->gt($inicioOverlapOp)) {
                $ativos[] = $turnoFixo;
            }
        }

        return $ativos;
    }

    /**
     * Monta as OPs da programação que cruzam a janela 06:00→03:00 da data
     * informada, com o previsto (cx) de cada uma via taxa_por_hora × eficiência.
     *
     * @return array{ops: array<int, array<string, mixed>>, total_prev_cx: int}
     */
    private function detalhesLinha(Programacao $prog, string $data): array
    {
        $diasSelecionados = $prog->dias_selecionados ?? [];
        $diaIso = Carbon::parse($data)->isoWeekday();
        $turnoIdsSelecionados = $this->turnosSelecionadosHoje($diasSelecionados, $data, $diaIso);

        $inicioDia = new \DateTimeImmutable($data . ' 06:00:00');
        $fimDia = new \DateTimeImmutable(Carbon::parse($data)->addDay()->format('Y-m-d') . ' 03:00:00');

        $calendarioId = $prog->linha?->calendario?->id;
        $intervalosLinha = $prog->linha?->calendario?->intervalos ?? collect();

        $opsRaw = DB::table('itens_programacao as ip')
            ->leftJoin('codi_eficiencia as ce', function ($j) use ($prog) {
                $j->on('ce.numero_op', '=', 'ip.numero_op')
                  ->where('ce.programacao_id', '=', $prog->id);
            })
            ->leftJoin('produtos as prod', 'prod.sku', '=', 'ip.sku')
            ->where('ip.programacao_id', $prog->id)
            ->whereNotNull('ce.inicio_previsto')
            ->whereNotNull('ce.fim_previsto')
            ->where('ce.fim_previsto', '>', $inicioDia->format('Y-m-d H:i:s'))
            ->where('ce.inicio_previsto', '<', $fimDia->format('Y-m-d H:i:s'))
            ->orderBy('ce.inicio_previsto')
            ->select('ip.numero_op', 'ip.sku', 'ip.descricao_produto', 'ip.quantidade',
                     'ce.inicio_previsto', 'ce.fim_previsto', 'prod.taxa_por_hora')
            ->get();

        $calendarioService = app(CalendarioService::class);
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

            if ($calendarioId && $opRow->taxa_por_hora) {
                try {
                    $minUteis = $calendarioService->minutosUteisEntre($inicioOverlap, $fimOverlap, $calendarioId, $diasSelecionados);
                    $ritmoOp = (float) $opRow->taxa_por_hora * $eficiencia;
                    $prevCx = min((int) $opRow->quantidade, (int) round($ritmoOp * $minUteis / 60));
                } catch (\Throwable $e) {
                    $prevCx = 0;
                }
            }

            $totalPrevCx += $prevCx;

            $ops[] = [
                'numero_op' => $opRow->numero_op,
                'sku' => $opRow->sku,
                'descricao_produto' => $opRow->descricao_produto,
                'quantidade' => (float) $opRow->quantidade,
                'inicio_previsto' => $opRow->inicio_previsto,
                'fim_previsto' => $opRow->fim_previsto,
                'turnos' => $this->turnosQueRodamNoDia($intervalosLinha, $turnoIdsSelecionados, $inicioOverlap, $fimOverlap, $data),
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
     * janela 06:00→03:00, capado pela quantidade da OP) para TODAS as
     * programações confirmadas do sistema — não só as da página atual nem só
     * as expandidas. Mesma fórmula usada em TvStaticController::index().
     */
    private function totalEstimadoHoje(): int
    {
        $hoje = Carbon::today();
        $amanha = $hoje->copy()->addDay();
        $inicioDia = new \DateTimeImmutable($hoje->format('Y-m-d') . ' 06:00:00');
        $fimDia = new \DateTimeImmutable($amanha->format('Y-m-d') . ' 03:00:00');

        $ops = DB::table('itens_programacao as ip')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->leftJoin('codi_eficiencia as ce', function ($j) {
                $j->on('ce.numero_op', '=', 'ip.numero_op')
                  ->on('ce.programacao_id', '=', 'p.id');
            })
            ->leftJoin('calendarios as cal', 'cal.linha_id', '=', 'l.id')
            ->leftJoin('produtos as prod', 'prod.sku', '=', 'ip.sku')
            ->where('p.status', 'confirmada')
            ->where('l.ativo', true)
            ->whereNotNull('ce.inicio_previsto')
            ->whereNotNull('ce.fim_previsto')
            ->where('ce.fim_previsto', '>', $inicioDia->format('Y-m-d H:i:s'))
            ->where('ce.inicio_previsto', '<', $fimDia->format('Y-m-d H:i:s'))
            ->select('ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto',
                     'p.eficiencia', 'p.dias_selecionados', 'cal.id as calendario_id',
                     'prod.taxa_por_hora')
            ->get();

        $calendarioService = app(CalendarioService::class);
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
                $total += min((int) $opRow->quantidade, (int) round($ritmoOp * $minUteis / 60));
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $total;
    }

    private function carregarHistorico(): array
    {
        return Programacao::with(['itens', 'linha'])
            ->historico()
            ->join('linhas', 'linhas.id', '=', 'programacoes.linha_id')
            ->reorder('linhas.codigo', 'asc')
            ->orderByDesc('programacoes.arquivada_em')
            ->select('programacoes.*')
            ->when($this->filtroLinhaId, fn ($q) => $q->where('programacoes.linha_id', $this->filtroLinhaId))
            ->get()
            ->groupBy('linha_id')
            ->map(fn ($group) => $group->values())
            ->toArray();
    }

    public function render()
    {
        $programacoes = Programacao::with(['linha.calendario.intervalos'])
            // select() antes de withCount/addSelect para não resetar as subqueries.
            // O join exige 'programacoes.*' explícito para evitar coluna 'id' ambígua.
            ->join('linhas', 'linhas.id', '=', 'programacoes.linha_id')
            ->select('programacoes.*')
            // P11: withCount() gera subqueries COUNT no banco; elimina N+1 de
            // with(['itens','resultados']) + ->count() em PHP por page load.
            ->withCount(['resultados'])
            // P15: COUNT(DISTINCT item_id) conta itens únicos sequenciados.
            // withCount() simples contaria blocos — um item que atravessa turnos
            // gera múltiplos blocos 'producao' com o mesmo item_id.
            ->addSelect([
                'itens_sequenciados_count' => ResultadoSequencia::selectRaw('COUNT(DISTINCT item_id)')
                    ->whereColumn('programacao_id', 'programacoes.id')
                    ->where('tipo', 'producao'),
            ])
            // P14: rascunhos e arquivadas ficam fora da tabela principal.
            // Arquivadas aparecem na seção de histórico abaixo.
            ->whereNotIn('status', ['rascunho', 'arquivada'])
            ->orderBy('linhas.codigo', 'asc')
            ->when($this->filtroLinhaId, fn ($q) => $q->where('programacoes.linha_id', $this->filtroLinhaId))
            ->paginate(10);

        $historico = $this->carregarHistorico();
        $turnosNoturnos = $this->turnosNoturnosDeHoje($programacoes);
        $gradeTurnos = $this->gradeTurnosPorProgramacao($programacoes);
        $turnosFixos = self::TURNOS_FIXOS;
        $detalhesExpandidos = $this->detalhesExpandidos($programacoes);

        // Soma os previstos de todas as linhas atualmente expandidas (cada uma
        // na sua própria data selecionada) — não é um total do dia único, e
        // sim o somatório do que está visível na tela neste momento.
        $totalGeralPrevCx = array_sum(array_column($detalhesExpandidos, 'total_prev_cx'));

        // Produção estimada do dia: soma de TODAS as programações confirmadas
        // (não só a página atual, não só as expandidas), sempre para hoje.
        $totalEstimadoHoje = $this->totalEstimadoHoje();

        return view('livewire.programacao.lista-programacoes', compact(
            'programacoes',
            'historico',
            'turnosNoturnos',
            'gradeTurnos',
            'turnosFixos',
            'detalhesExpandidos',
            'totalGeralPrevCx',
            'totalEstimadoHoje'
        ));
    }
}
