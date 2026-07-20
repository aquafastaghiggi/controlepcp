<?php

declare(strict_types=1);

namespace App\Livewire\Programacao;

use App\Actions\CalcularSequenciaAction;
use App\Actions\CriarProgramacaoAction;
use App\Models\Calendario;
use App\Models\Produto;
use App\Models\Programacao;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Formulário de programação orientado a Excel.
 *
 * Fluxo:
 *   1. Usuário faz upload do Excel → ImportarExcel despacha 'ordensImportadas'
 *   2. Usuário configura: data/hora de início, eficiência, dias e turnos de trabalho
 *   3. Calcula → resultado salvo automaticamente → Gantt + tabela
 */
class FormularioProgramacao extends Component
{
    // ── Excel / fonte de dados ───────────────────────────────────────────────

    public bool   $excelCarregado  = false;
    public string $arquivoNome     = '';
    public array  $abasDisponiveis = [];
    public string $abaSelecionada  = '';

    /** Nomes das abas (planilha) já calculadas nesta sessão — exibe badge ✓ nas tabs */
    public array  $abasCalculadas  = [];

    // ── Dados derivados do Excel ─────────────────────────────────────────────

    public array  $itens          = [];
    public array  $realizadoCodi  = [];

    // Indica se alguma OP da programação já tem produção real registrada no CODI
    public function getTemProducaoIniciadaProperty(): bool
    {
        return collect($this->realizadoCodi)->filter(fn($v) => $v > 0)->isNotEmpty();
    }

    public ?int   $linhaId        = null;
    public string $linhaNome = '';

    // ── Configuração ─────────────────────────────────────────────────────────

    public string $dataInicio = '';
    public float  $eficiencia = 100.0;

    /**
     * Turnos cadastrados para a linha selecionada.
     * Cada item: ['id', 'nome', 'hora_inicio', 'hora_fim']
     */
    public array $turnosDisponiveis = [];

    /**
     * Próximos 10 dias úteis (exceto domingo).
     * Cada item: ['data' => 'Y-m-d', 'label_dia' => 'Seg', 'label_data' => '11/06', 'dia_semana' => 1]
     */
    public array $proximosDias = [];

    /**
     * Configuração de dias e turnos, indexada por data 'Y-m-d'.
     *
     * Estrutura:
     *   ['2026-06-11' => [
     *       'ativo'      => bool,
     *       'dia_semana' => int,
     *       'turnos'     => [['id' => int, 'ativo' => bool], ...]
     *   ], ...]
     *
     * Array indexado em 'turnos' (não keyed por id) para evitar
     * problemas de tipo de chave após serialização JSON do Livewire.
     */
    public array $configuracaoDias = [];

    // ── Estado da tela ───────────────────────────────────────────────────────

    public string $etapaAtual  = 'entrada';
    public bool   $processando = false;

    public array  $resultados       = [];
    public array  $resumo           = [];
    public array  $erros            = [];
    public ?int   $programacaoId    = null;
    public ?int   $programacaoSalvaId = null;

    // ─── Inicialização ────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->dataInicio = now()->format('Y-m-d\TH:i');

        $id = request()->query('id');
        if ($id) {
            $this->carregarProgramacaoSalva((int) $id);
        }
    }

    // ─── Carregar programação existente (Ver Resultado) ───────────────────────

    public function carregarProgramacaoSalva(int $id): void
    {
        $programacao = Programacao::with(['itens', 'resultados', 'linha'])->find($id);
        if (! $programacao) {
            return;
        }

        $this->linhaId            = $programacao->linha_id;
        $this->linhaNome          = $programacao->linha?->nome ?? '';
        $this->eficiencia         = (float) $programacao->eficiencia;
        $this->excelCarregado     = true;
        $this->programacaoId      = $programacao->id;
        $this->programacaoSalvaId = $programacao->id;

        $this->itens = $programacao->itens->map(fn ($i) => [
            'sku'        => $i->sku,
            'descricao'  => $i->descricao_produto ?? '',
            'quantidade' => (float) $i->quantidade,
            'sequencia'  => $i->sequencia,
            'prazo'      => null,
            'numero_op'  => $i->numero_op,
        ])->toArray();

        $this->resultados = $programacao->resultados->sortBy('inicio')->map(fn ($r) => [
            'item_id'             => $r->item_id,
            'tipo'                => $r->tipo,
            'sku'                 => $r->sku,
            'descricao'           => null,
            'quantidade'          => null,
            'taxa_efetiva'        => null,
            'duracao_minutos'     => (int) $r->duracao_minutos,
            'inicio'              => $r->inicio instanceof \DateTimeInterface
                ? $r->inicio->format('Y-m-d H:i:s')
                : (string) $r->inicio,
            'fim'                 => $r->fim instanceof \DateTimeInterface
                ? $r->fim->format('Y-m-d H:i:s')
                : (string) $r->fim,
            'quantidade_estimada' => (float) $r->quantidade_estimada,
            'memoria_calculo'     => $r->memoria_calculo ?? '',
        ])->values()->toArray();

        $this->injetarDescricoesNosResultados();
        $this->carregarRealizadoCodi();
        $this->injetarRealizadoNosResultados();

        $ultimoFim = $programacao->resultados->sortByDesc('fim')->first()?->fim;

        $this->resumo = [
            'total_setup_min'    => (int) $programacao->resultados->where('tipo', 'setup')->sum('duracao_minutos'),
            'total_producao_min' => (int) $programacao->resultados->where('tipo', 'producao')->sum('duracao_minutos'),
            'fim_previsto'       => $ultimoFim instanceof \DateTimeInterface
                ? $ultimoFim->format('Y-m-d H:i:s')
                : (string) $ultimoFim,
        ];

        $this->etapaAtual = 'resultado';
        $this->dispatch('gantt-atualizado', resultados: $this->resultados, turnos: $this->turnosPorData());
    }

    // ─── Receber dados do Excel ───────────────────────────────────────────────

    #[On('ordensImportadas')]
    public function receberOrdensExcel(array $payload): void
    {
        $ordens = $payload['ordens'] ?? [];

        $skus = Produto::whereIn('sku', array_column($ordens, 'sku'))
            ->pluck('descricao', 'sku')
            ->toArray();

        $this->itens = array_map(static function (array $o, int $i) use ($skus): array {
            return [
                'sku'        => $o['sku'],
                'descricao'  => $skus[$o['sku']] ?? $o['descricao'],
                'quantidade' => (float) $o['quantidade'],
                'sequencia'  => $i + 1,
                'prazo'      => $o['data_programada'] ?? null,
                'numero_op'  => $o['numero_op'],
            ];
        }, $ordens, array_keys($ordens));

        $this->linhaId         = $payload['linha_id'] ?? null;
        $this->linhaNome       = $payload['linha_nome'] ?? '';
        $this->abasDisponiveis = $payload['todas_abas'] ?? [];
        $this->abaSelecionada  = $payload['aba_selecionada'] ?? '';
        $this->arquivoNome     = $payload['arquivo_nome'] ?? '';
        $this->excelCarregado  = true;

        $this->carregarTurnosDaLinha();

        // Resetar resultado anterior ao trocar de aba
        $this->etapaAtual         = 'entrada';
        $this->resultados         = [];
        $this->resumo             = [];
        $this->erros              = [];
        $this->programacaoId      = null;
        $this->programacaoSalvaId = null;
    }

    // ─── Configuração de dias e turnos ───────────────────────────────────────

    public function carregarTurnosDaLinha(): void
    {
        if (! $this->linhaId) {
            $this->turnosDisponiveis = [];
            $this->configuracaoDias  = [];
            $this->proximosDias      = [];
            return;
        }

        $calendario = Calendario::where('linha_id', $this->linhaId)
            ->with(['intervalosAtivos' => fn ($q) => $q->orderBy('ordem')])
            ->first();

        if (! $calendario) {
            $this->turnosDisponiveis = [];
            $this->configuracaoDias  = [];
            $this->proximosDias      = [];
            return;
        }

        $this->turnosDisponiveis = $calendario->intervalosAtivos
            ->map(fn ($i) => [
                'id'          => $i->id,
                'nome'        => $i->nome,
                'hora_inicio' => $i->hora_inicio,
                'hora_fim'    => $i->hora_fim,
                'ordem'       => $i->ordem,
            ])
            ->values()
            ->toArray();

        $this->inicializarConfiguracaoDias();
    }

    /**
     * Ativa/desativa um dia inteiro (por data 'Y-m-d').
     * Ao ativar, todos os turnos ficam ativos.
     */
    public function toggleDia(string $data): void
    {
        if (! isset($this->configuracaoDias[$data])) {
            return;
        }

        $config             = $this->configuracaoDias;
        $novoEstado         = ! $config[$data]['ativo'];
        $config[$data]['ativo'] = $novoEstado;

        if ($novoEstado) {
            foreach ($config[$data]['turnos'] as $idx => $_) {
                $config[$data]['turnos'][$idx]['ativo'] = true;
            }
        }

        $this->configuracaoDias = $config;
    }

    /**
     * Ativa/desativa um turno específico de um dia (por data 'Y-m-d').
     * Se todos os turnos forem desativados, o dia é desativado automaticamente.
     */
    public function toggleTurnoDia(string $data, int $turnoId): void
    {
        if (! isset($this->configuracaoDias[$data])) {
            return;
        }
        if (! $this->configuracaoDias[$data]['ativo']) {
            return;
        }

        $config = $this->configuracaoDias;

        foreach ($config[$data]['turnos'] as $idx => $t) {
            if ((int) $t['id'] === $turnoId) {
                $config[$data]['turnos'][$idx]['ativo'] = ! $t['ativo'];
                break;
            }
        }

        // Se todos os turnos foram desmarcados, desativar o dia
        $algumAtivo = collect($config[$data]['turnos'])->contains(fn ($t) => $t['ativo']);
        if (! $algumAtivo) {
            $config[$data]['ativo'] = false;
        }

        $this->configuracaoDias = $config;
    }

    // ─── Troca de aba ────────────────────────────────────────────────────────

    public function trocarAba(string $nomeAba): void
    {
        if ($nomeAba === $this->abaSelecionada) {
            return;
        }
        $this->dispatch('trocarAbaExcel', aba: $nomeAba);
    }

    // ─── Cálculo ─────────────────────────────────────────────────────────────

    public function calcular(
        CriarProgramacaoAction  $criar,
        CalcularSequenciaAction $calcularSequencia
    ): void {
        $this->validarParaCalculo();

        $this->processando = true;
        $this->erros       = [];

        try {
            $programacao = $this->resolverProgramacao($criar);

            $resultado = $calcularSequencia->executar(
                $programacao->id,
                false,
                new DateTimeImmutable()
            );

            $this->programacaoId = $resultado['programacao']->id;
            $this->resultados    = $this->serializarResultados($resultado['resultados']);
            $this->injetarDescricoesNosResultados();
            $this->carregarRealizadoCodi();
            $this->injetarRealizadoNosResultados();
            $this->resumo        = $resultado['resumo'];
            $this->erros         = [];
            $this->etapaAtual    = 'resultado';

            // Auto-salva como confirmada
            $this->salvar();

            $this->dispatch('gantt-atualizado', resultados: $this->resultados, turnos: $this->turnosPorData());

        } catch (Throwable $e) {
            $this->erros = [$e->getMessage()];
        } finally {
            $this->processando = false;
        }
    }

    public function salvar(): void
    {
        if (! $this->programacaoId) {
            return;
        }

        $programacaoId = $this->programacaoId;

        DB::transaction(function () use ($programacaoId): void {
            $programacao = Programacao::lockForUpdate()->find($programacaoId);

            if ($programacao) {
                // Arquiva a programação confirmada anterior desta linha antes de confirmar a nova
                Programacao::where('linha_id', $programacao->linha_id)
                    ->where('status', 'confirmada')
                    ->where('id', '!=', $programacao->id)
                    ->update(['status' => 'arquivada', 'arquivada_em' => now()]);
            }

            Programacao::where('id', $programacaoId)->update([
                'status'       => 'confirmada',
                'calculado_em' => now(),
            ]);
        });

        // Popula codi_eficiencia para os KPIs funcionarem imediatamente após confirmação.
        // Não bloqueia o fluxo — programação já foi confirmada pela transação acima.
        try {
            app(\App\Services\Codi\EficienciaCalculator::class)
                ->calcularParaProgramacao($this->programacaoId);
        } catch (\Throwable $e) {
            \Log::warning('EficienciaCalculator falhou ao confirmar programação', [
                'programacao_id' => $this->programacaoId,
                'erro'           => $e->getMessage(),
            ]);
        }

        $this->programacaoSalvaId = $this->programacaoId;
        if ($this->abaSelecionada && ! in_array($this->abaSelecionada, $this->abasCalculadas, true)) {
            $this->abasCalculadas[] = $this->abaSelecionada;
        }
        session()->flash('sucesso', "✅ Programação #{$this->programacaoId} salva.");
    }

    public function recalcular(): void
    {
        // Bloqueia recálculo se já houver produção real registrada no CODI
        if ($this->temProducaoIniciada) {
            $this->dispatch('notify', tipo: 'erro', mensagem: 'Recalcular bloqueado — já há produção iniciada no CODI para esta programação.');
            return;
        }

        $this->etapaAtual         = 'entrada';
        $this->resultados         = [];
        $this->resumo             = [];
        $this->erros              = [];
        $this->programacaoId      = null;
        $this->programacaoSalvaId = null;
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.programacao.formulario-programacao');
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    /**
     * Resolve qual programação usar para o cálculo, sem risco de duplicate entry:
     * 1. Se já há um ID salvo na sessão → atualiza e limpa resultados anteriores.
     * 2. Se existe no banco pela chave (linha_id + numero_op) → reaproveita.
     * 3. Caso contrário → cria nova via CriarProgramacaoAction.
     */
    private function resolverProgramacao(CriarProgramacaoAction $criar): Programacao
    {
        if ($this->programacaoSalvaId) {
            $prog = Programacao::findOrFail($this->programacaoSalvaId);
            $prog->update([
                'data_inicio_planejada' => $this->dataInicio,
                'eficiencia'            => $this->eficiencia,
                'dias_selecionados'     => $this->montarDiasSelecionados(),
                'status'                => 'rascunho',
            ]);
            $prog->resultados()->delete();
            return $prog;
        }

        $primeiraOp = $this->itens[0]['numero_op'] ?? null;
        if ($primeiraOp) {
            $existente = Programacao::where('linha_id', $this->linhaId)
                ->where('numero_op', $primeiraOp)
                ->first();

            if ($existente) {
                $this->programacaoSalvaId = $existente->id;
                $existente->update([
                    'data_inicio_planejada' => $this->dataInicio,
                    'eficiencia'            => $this->eficiencia,
                    'dias_selecionados'     => $this->montarDiasSelecionados(),
                    'status'                => 'rascunho',
                ]);
                $existente->resultados()->delete();
                return $existente;
            }
        }

        return $criar->executar([
            'linha_id'              => $this->linhaId,
            'numero_op'             => $primeiraOp,
            'data_inicio_planejada' => $this->dataInicio,
            'eficiencia'            => $this->eficiencia,
            'dias_selecionados'     => $this->montarDiasSelecionados(),
            'origem'                => 'excel',
            'itens'                 => $this->itens,
        ]);
    }

    /**
     * Gera os próximos 10 dias úteis a partir de hoje, excluindo domingos.
     */
    private function gerarProximosDias(): array
    {
        $dias      = [];
        $data      = now()->startOfDay()->copy();
        $count     = 0;
        $nomesDias = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

        while ($count < 10) {
            $carbonDow = (int) $data->dayOfWeek; // 0=Dom, 1=Seg…6=Sáb
            $isoDow    = $carbonDow === 0 ? 7 : $carbonDow; // 1=Seg…7=Dom

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

    /**
     * Inicializa $configuracaoDias com os próximos 10 dias úteis (exceto domingo).
     * Sábado começa inativo; demais dias ativos com todos os turnos.
     */
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
                // Seg–Sex: apenas T1 e T2 (ordem <= 2) ativos por padrão; T3/T4 inativos.
                // Sábado: todos inativos ($ativo = false sobrepõe tudo).
                $turnoAtivo = $ativo && (($turno['ordem'] ?? 99) <= 2);

                $turnos[] = [
                    'id'    => $turno['id'],
                    'ativo' => $turnoAtivo,
                ];
            }

            $config[$diaInfo['data']] = [
                'ativo'      => $ativo,
                'dia_semana' => $diaInfo['dia_semana'],
                'turnos'     => $turnos,
            ];
        }

        $this->configuracaoDias = $config;
    }

    /**
     * Gera mapa de janelas produtivas por data para o plugin de faixas do Gantt.
     * Cruza $configuracaoDias (flags ativo/inativo por dia e turno) com
     * $turnosDisponiveis (hora_inicio/hora_fim de cada turno).
     * Zero queries adicionais — usa apenas dados já em memória.
     *
     * Retorna: ['2026-06-16' => [['inicio'=>'07:10','fim'=>'11:28'], ...], ...]
     */
    private function turnosPorData(): array
    {
        if (empty($this->configuracaoDias) || empty($this->turnosDisponiveis)) {
            return [];
        }

        // Index por id para lookup O(1)
        $horasPorId = collect($this->turnosDisponiveis)->keyBy('id')->toArray();
        $resultado  = [];

        foreach ($this->configuracaoDias as $data => $cfg) {
            if (! ($cfg['ativo'] ?? false)) {
                continue;
            }

            $janelas = [];
            foreach (($cfg['turnos'] ?? []) as $t) {
                if (! ($t['ativo'] ?? false)) {
                    continue;
                }
                $h = $horasPorId[$t['id']] ?? null;
                if (! $h) {
                    continue;
                }
                // substr para garantir formato 'HH:MM' mesmo que hora_inicio venha como 'HH:MM:SS'
                $janelas[] = [
                    'inicio' => substr((string) $h['hora_inicio'], 0, 5),
                    'fim'    => substr((string) $h['hora_fim'], 0, 5),
                ];
            }

            if (! empty($janelas)) {
                $resultado[$data] = $janelas;
            }
        }

        return $resultado;
    }

    /**
     * Converte $configuracaoDias para o formato de datas reais esperado pelo CalendarioService.
     *
     * Retorna: ['Y-m-d' => ['dia_semana' => int, 'turnos' => [turnoId, ...]], ...]
     * Apenas dias ativos com pelo menos um turno ativo.
     */
    private function montarDiasSelecionados(): array
    {
        $resultado = [];

        foreach ($this->configuracaoDias as $data => $config) {
            if (! $config['ativo']) {
                continue;
            }

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

    private function validarParaCalculo(): void
    {
        $this->validate([
            'linhaId'    => 'required|integer|min:1',
            'dataInicio' => 'required|date',
            'eficiencia' => 'required|numeric|min:1|max:150',
            'itens'      => 'required|array|min:1',
        ], [
            'linhaId.required'    => 'Selecione uma linha de produção.',
            'linhaId.min'         => 'Nenhuma linha associada ao Excel. Selecione outra aba.',
            'dataInicio.required' => 'Informe a data e hora de início.',
            'dataInicio.date'     => 'Data de início inválida.',
            'eficiencia.min'      => 'Eficiência mínima é 1%.',
            'eficiencia.max'      => 'Eficiência máxima é 150%.',
            'itens.min'           => 'Importe o Excel primeiro.',
        ]);
    }

    private function injetarDescricoesNosResultados(): void
    {
        $skus = collect($this->resultados)->pluck('sku')->filter()->unique()->values()->toArray();
        if (empty($skus)) return;
        $descricoes = \App\Models\Produto::whereIn('sku', $skus)->pluck('descricao', 'sku')->toArray();
        $this->resultados = array_map(function (array $r) use ($descricoes): array {
            $r['descricao'] = $descricoes[$r['sku']] ?? null;
            return $r;
        }, $this->resultados);
    }

    private function carregarRealizadoCodi(): void
    {
        if (empty($this->itens)) return;
        $ops = collect($this->itens)->pluck('numero_op')->filter()->toArray();
        if (empty($ops)) return;

        // P2: Restringe ao período desta programação para não acumular eventos de
        // execuções anteriores da mesma OP (re-programações, cancelamentos, retrabalho).
        $inicio = collect($this->resultados)->where('tipo', 'producao')->min('inicio');
        $fim    = collect($this->resultados)->where('tipo', 'producao')->max('fim');

        $this->realizadoCodi = \App\Models\Codi\CodiEvento::whereIn('ordem_producao', $ops)
            ->where('tipo_evento', 'PRODUCAO')
            ->when($inicio !== null && $fim !== null, fn ($q) => $q
                ->where('inicio_evento', '>=', $inicio)
                ->where('inicio_evento', '<=', $fim)
            )
            ->selectRaw('ordem_producao, SUM(quantidade) as total')
            ->groupBy('ordem_producao')
            ->pluck('total', 'ordem_producao')
            ->map(fn ($v) => round((float) $v))
            ->toArray();
    }

    private function injetarRealizadoNosResultados(): void
    {
        // P1: Monta lookup item_id → numero_op direto do banco.
        // firstWhere('sku') falha quando o mesmo SKU aparece mais de uma vez na
        // programação — todos os blocos recebiam o realizado da primeira OP encontrada.
        $itemIdParaOp = $this->programacaoId
            ? \App\Models\ItemProgramacao::where('programacao_id', $this->programacaoId)
                ->pluck('numero_op', 'id')
                ->toArray()
            : [];

        $this->resultados = array_map(function (array $r) use ($itemIdParaOp): array {
            if ($r['tipo'] !== 'producao') return $r;

            // P1: Prioriza item_id (exato); cai para busca por SKU apenas se item_id
            // não estiver disponível (programação ainda não salva — situação transitória).
            $itemId   = $r['item_id'] ?? null;
            $numeroOp = ($itemId !== null && isset($itemIdParaOp[$itemId]))
                ? $itemIdParaOp[$itemId]
                : ((collect($this->itens)->firstWhere('sku', $r['sku']) ?? [])['numero_op'] ?? null);

            $r['numero_op']      = $numeroOp;

            $realizado = $numeroOp ? ($this->realizadoCodi[$numeroOp] ?? null) : null;

            // P3: Usa quantidade_estimada (calculada pelo SequenciadorService com eficiência
            // e turnos aplicados) em vez da quantidade bruta do Excel.
            // Fallback para Excel (quantidade_estimada = 0 indica bloco truncado pelo sequenciador).
            $itemFallback = (collect($this->itens)->firstWhere('sku', $r['sku']) ?? []);
            $programado   = (float) $r['quantidade_estimada'] > 0
                ? (float) $r['quantidade_estimada']
                : ($itemFallback['quantidade'] ?? null);

            $r['realizado_codi'] = $realizado;
            $r['programado']     = $programado;
            $r['pct_realizado']  = ($realizado !== null && $programado !== null && $programado > 0)
                ? min(100, round(($realizado / $programado) * 100))
                : null;

            // Barra de realizado proporcional ao tempo — usada pelo Gantt para sobreposição visual
            $proporcao    = min(1.0, ($r['pct_realizado'] ?? 0) / 100);
            $inicioTs     = strtotime($r['inicio']);
            $fimTs        = strtotime($r['fim']);
            $duracaoTotal = $fimTs - $inicioTs;

            $r['inicio_realizado'] = $r['inicio'];
            $r['fim_realizado']    = date('Y-m-d H:i:s', $inicioTs + intval($duracaoTotal * $proporcao));
            $r['status_gantt']     = match(true) {
                ($r['pct_realizado'] ?? 0) >= 90 => 'concluido',
                ($r['pct_realizado'] ?? 0) >= 60 => 'atencao',
                ($r['pct_realizado'] ?? 0) > 0   => 'atrasado',
                default                          => 'nao_iniciado',
            };

            return $r;
        }, $this->resultados);
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
