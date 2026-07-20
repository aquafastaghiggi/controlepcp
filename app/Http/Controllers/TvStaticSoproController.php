<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Livewire\Sopro\AcompanharProducaoSopro;
use App\Services\CalendarioSoproService;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class TvStaticSoproController extends Controller
{
    public function index(): View
    {
        $painel = new AcompanharProducaoSopro();
        $painel->carregarDados();

        $maquinas = $painel->maquinas;
        $kpis     = $painel->kpis;

        // Janela: 06:00 hoje → 06:00 amanhã (cobre T1/T2/T3 incluindo overnight)
        $inicioDia = Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        $fimDia = Carbon::tomorrow()->setHour(6)->setMinute(0)->setSecond(0);
        // Ajuste rolante: se ainda não passou das 06:00, usar janela do dia anterior
        if (Carbon::now()->lt($inicioDia)) {
            $inicioDia = $inicioDia->copy()->subDay();
            $fimDia = $fimDia->copy()->subDay();
        }

        // Total produzido hoje por máquina (desde 06:00)
        $maquinas = array_map(function ($maquina) use ($inicioDia) {
            $totalHoje = (int) DB::table('codi_eventos')
                ->where('codigo_recurso', $maquina['codigo_recurso'])
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia)
                ->sum('quantidade');
            $maquina['total_hoje'] = $totalHoje;
            return $maquina;
        }, $maquinas);

        // Previsto dinâmico proporcional via CalendarioSoproService — o serviço
        // exige DateTimeImmutable (tipagem estrita), então mantemos uma versão
        // separada dos limites do dia só pra essas chamadas.
        $inicioDiaImm = new DateTimeImmutable($inicioDia->format('Y-m-d H:i:s'));
        $fimDiaImm    = new DateTimeImmutable($fimDia->format('Y-m-d H:i:s'));
        $agoraImm  = new DateTimeImmutable(Carbon::now()->format('Y-m-d H:i:s'));

        $calendarioService = app(CalendarioSoproService::class);

        $programacoes = DB::table('itens_programacao_sopro as ip')
            ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
            ->join('maquinas as m', 'm.id', '=', 'p.maquina_id')
            ->leftJoin('codi_eficiencia_sopro as ce', function ($j) {
                $j->on('ce.numero_op', '=', 'ip.numero_op')
                  ->on('ce.programacao_sopro_id', '=', 'p.id');
            })
            ->leftJoin('calendarios_sopro as cal', 'cal.maquina_id', '=', 'm.id')
            // frascos.sku (utf8mb4_general_ci) e itens_programacao_sopro.sku
            // (utf8mb4_unicode_ci) têm collations diferentes — join direto por
            // coluna gera "Illegal mix of collations"; força COLLATE no join.
            ->leftJoin('frascos as frs', DB::raw('frs.sku COLLATE utf8mb4_unicode_ci'), '=', 'ip.sku')
            ->where('p.status', 'confirmada')
            ->where('m.ativo', true)
            ->whereNotNull('ce.inicio_previsto')
            ->whereNotNull('ce.fim_previsto')
            ->where('ce.fim_previsto', '>', $inicioDia->format('Y-m-d H:i:s'))
            ->where('ce.inicio_previsto', '<', $fimDia->format('Y-m-d H:i:s'))
            ->select('m.id as maquina_id', 'm.codigo', 'm.codigo_recurso', 'ip.numero_op',
                     'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto',
                     'p.dias_selecionados', 'p.eficiencia', 'cal.id as calendario_id',
                     'frs.taxa_por_hora')
            ->get();

        // Máquinas cujo último evento é uma Parada Programada em andamento — excluídas
        // do cálculo de previsto/dia, já que não vão produzir enquanto durar a parada
        $maquinasEmParadaProgramada = DB::table('codi_eventos as ce')
            ->join('maquinas as m', 'm.codigo_recurso', '=', 'ce.codigo_recurso')
            ->whereIn('ce.id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('codi_eventos')
                    ->groupBy('codigo_recurso');
            })
            ->where('ce.tipo_evento', 'PARADA')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ce.dados_raw, '$.parada.nomeParada')) LIKE '%PARADA PROGRAMADA%'")
            ->where('m.ativo', true)
            ->pluck('m.id')
            ->toArray();

        $previstoTotal = 0;
        $projecaoTotal = 0;

        $porMaquina = [];
        foreach ($programacoes as $prog) {
            if (in_array($prog->maquina_id, $maquinasEmParadaProgramada)) continue;
            if (!$prog->calendario_id || !$prog->taxa_por_hora) continue;
            $diasSel    = json_decode($prog->dias_selecionados ?? '[]', true);
            $inicioOp   = new DateTimeImmutable($prog->inicio_previsto);
            $fimOp      = new DateTimeImmutable($prog->fim_previsto);
            $inicioCalc = $inicioOp < $inicioDiaImm ? $inicioDiaImm : $inicioOp;
            $fimCalc    = $fimOp > $fimDiaImm ? $fimDiaImm : $fimOp;

            if ($fimCalc <= $inicioCalc) continue;

            try {
                $minUteisDia = $calendarioService->minutosUteisEntre($inicioCalc, $fimCalc, $prog->calendario_id, $diasSel);
                if ($minUteisDia <= 0) continue;

                // Previsto = taxa cadastrada × eficiência da programação × horas úteis
                // na janela de hoje, nunca ultrapassando a quantidade total da própria OP.
                // itens_programacao_sopro.quantidade vem do Colemar em milheiros (ex.: 7 = 7.000
                // frascos) — precisa de ×1000 pra comparar com valores em unidade (taxa_por_hora,
                // produção real do CODI). Mesma conversão já usada em GravarPrevistoHoje.php.
                $eficiencia = max(0.0, (float) $prog->eficiencia) / 100;
                $ritmoOp    = (float) $prog->taxa_por_hora * $eficiencia;
                $qtdHoje    = min((int) ($prog->quantidade * 1000), (int) round($ritmoOp * $minUteisDia / 60));

                $previstoTotal += $qtdHoje;
                $porMaquina[$prog->codigo_recurso][] = [
                    'numero_op'         => $prog->numero_op,
                    'qtd_hoje'          => $qtdHoje,
                    'calendario_id'     => $prog->calendario_id,
                    'dias_selecionados' => $diasSel,
                ];
            } catch (Throwable $e) {
                continue;
            }
        }

        // Máquinas reprogramadas hoje: a programação anterior foi arquivada durante
        // a janela produtiva. OPs que rodaram e terminaram sob a agenda antiga podem
        // não aparecer mais na confirmada — soma o que foi REALMENTE produzido dessas
        // OPs desde 06:30, sem duplicar (só entra quem não está já contabilizado acima).
        $numeroOpsContabilizadosPorMaquina = [];
        foreach ($programacoes as $prog) {
            $numeroOpsContabilizadosPorMaquina[$prog->maquina_id][] = $prog->numero_op;
        }

        $itensArquivadosHoje = DB::table('itens_programacao_sopro as ip')
            ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
            ->join('maquinas as m', 'm.id', '=', 'p.maquina_id')
            ->where('p.status', 'arquivada')
            ->where('p.arquivada_em', '>=', $inicioDia)
            ->where('m.ativo', true)
            ->select('m.id as maquina_id', 'm.codigo_recurso', 'ip.numero_op')
            ->get();

        $numeroOpsJaSomadosDeArquivada = [];
        foreach ($itensArquivadosHoje as $item) {
            if (in_array($item->maquina_id, $maquinasEmParadaProgramada)) continue;

            $chave = $item->maquina_id . '|' . $item->numero_op;
            if (isset($numeroOpsJaSomadosDeArquivada[$chave])) continue;

            $jaContabilizada = in_array(
                $item->numero_op,
                $numeroOpsContabilizadosPorMaquina[$item->maquina_id] ?? [],
                true
            );
            if ($jaContabilizada) continue;

            $numeroOpsJaSomadosDeArquivada[$chave] = true;

            $produzidoOp = (int) round((float) DB::table('codi_eventos')
                ->where('codigo_recurso', $item->codigo_recurso)
                ->where('ordem_producao', $item->numero_op)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia)
                ->sum('quantidade'));

            if ($produzidoOp > 0) {
                $previstoTotal += $produzidoOp;
            }
        }

        // Projeção com CalendarioSoproService por máquina
        foreach ($porMaquina as $codigoRecurso => $ops) {
            $prodMaquina = (float) DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecurso)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia)
                ->sum('quantidade');

            $minTrabalhados = (float) DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecurso)
                ->where('inicio_evento', '>=', $inicioDia)
                ->sum('duracao_minutos');
            $horasRodando = max(0.1, $minTrabalhados / 60);
            $ritmo = $prodMaquina / $horasRodando;

            $op = $ops[0]; // usa calendário/turnos da primeira OP da máquina
            try {
                $minRestantes   = $calendarioService->minutosUteisEntre($agoraImm, $fimDiaImm, $op['calendario_id'], $op['dias_selecionados']);
                $horasRestantes = $minRestantes / 60;
            } catch (Throwable $e) {
                $horasRestantes = 0;
            }

            // Capacidade teórica = já produzido + projeção do ritmo atual pelo
            // tempo restante — equivalente a ritmo × jornada inteira (06:30→06:30).
            $capacidadeTeorica = $prodMaquina + ($ritmo * $horasRestantes);

            $somaOpsHoje = array_sum(array_column($ops, 'qtd_hoje'));

            $prevXRealVal = $capacidadeTeorica - $somaOpsHoje;

            // Projeção da máquina: se atrasada usa a capacidade teórica (menor que
            // o programado); se adiantada, trava no programado — equivalente a
            // min($capacidadeTeorica, $somaOpsHoje).
            $projecaoMaquina = $prevXRealVal < 0 ? $capacidadeTeorica : $somaOpsHoje;

            $projecaoTotal += $projecaoMaquina;
        }

        $totalProg = $previstoTotal;
        $projecao  = (int) round($projecaoTotal);
        $pctProj   = $totalProg > 0 ? round($projecao / $totalProg * 100, 1) : 0;
        $diferenca = $projecao - $totalProg;

        $kpis['previsto_hoje'] = $totalProg;
        $kpis['projecao']      = $projecao;
        $kpis['pct_proj']      = $pctProj;
        $kpis['diferenca']     = $diferenca;

        $kpis['pct_hoje'] = $totalProg > 0
            ? round(($kpis['produzido_hoje'] ?? 0) / $totalProg * 100, 1)
            : 0;

        // Total paradas
        $opsConfirmadas = DB::table('itens_programacao_sopro as ip')
            ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
            ->join('maquinas as m', 'm.id', '=', 'p.maquina_id')
            ->where('p.status', 'confirmada')
            ->where('m.ativo', true)
            ->pluck('ip.numero_op')->unique();

        $totalParadaMin = (int) DB::table('codi_eventos')
            ->whereIn('ordem_producao', $opsConfirmadas)
            ->where('tipo_evento', 'PARADA')
            ->where('inicio_evento', '>=', $inicioDia)
            ->whereRaw("TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW())) < 240")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.tipoParada.nomeTipoParada')) != 'Intervalo'")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.nomeParada')) != 'PARADA PROGRAMADA'")
            ->selectRaw("SUM(TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW()))) as total")
            ->value('total');

        $kpis['total_parada_min'] = $totalParadaMin;

        // maquinasComParada necessário para a view (substitui propriedade Livewire)
        $maquinasComParada = [];

        // Exposto separadamente pro KPI "Total programado" do topo da TV.
        $totalProgramado = $totalProg;

        return view('tv.static-sopro', compact(
            'maquinas', 'kpis', 'maquinasComParada', 'totalProgramado'
        ));
    }
}
