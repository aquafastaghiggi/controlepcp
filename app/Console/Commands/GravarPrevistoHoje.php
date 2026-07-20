<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GravarPrevistoHoje extends Command
{
    protected $signature   = 'pcp:gravar-previsto-hoje';
    protected $description = 'Grava o previsto de hoje na tabela kpis_diarios — deve rodar às 06:00';

    public function handle(): void
    {
        $hoje       = today()->format('Y-m-d');
        $inicioDia  = Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        $fimDiaUtil = Carbon::tomorrow()->setHour(3)->setMinute(0)->setSecond(0);

        // Verifica se já foi gravado hoje
        $jaGravado = DB::table('kpis_diarios')
            ->where('data', $hoje)
            ->where('modulo', 'envase')
            ->exists();

        if ($jaGravado) {
            $this->info("Previsto de hoje ($hoje) já está gravado. Nada a fazer.");
        } else {
            // Calcula previsto: saldo das OPs que terminam até 22:00 hoje
            // Saldo = quantidade da OP − o que já foi produzido antes das 06:00
            $inicioDiaStr  = $inicioDia->format('Y-m-d H:i:s');
            $fimDiaUtilStr = $fimDiaUtil->format('Y-m-d H:i:s');

            $ops = DB::table('itens_programacao as ip')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->join('codi_eficiencia as ce', function ($join) {
                    $join->on('ce.numero_op', '=', 'ip.numero_op')
                         ->on('ce.programacao_id', '=', 'p.id');
                })
                ->leftJoin('produtos as pr', 'pr.sku', '=', 'ip.sku')
                ->where('p.status', 'confirmada')
                ->where('ce.inicio_previsto', '<', $fimDiaUtilStr)
                ->where('ce.fim_previsto', '>', $inicioDiaStr)
                ->whereNotNull('ip.numero_op')
                ->select(
                    'ip.numero_op', 'ip.quantidade',
                    'ce.inicio_previsto', 'ce.fim_previsto',
                    'pr.taxa_por_hora', 'p.eficiencia',
                    DB::raw('(SELECT COALESCE(SUM(ce2.quantidade), 0) FROM codi_eventos ce2
                              WHERE ce2.ordem_producao = ip.numero_op
                              AND ce2.tipo_evento = "PRODUCAO"
                              AND ce2.inicio_evento < ?) as produzido_ate_06h')
                )
                ->addBinding($inicioDiaStr, 'select')
                ->get();

            $previsto = 0;
            foreach ($ops as $op) {
                $saldo = max(0, $op->quantidade - ($op->produzido_ate_06h ?? 0));
                if ($saldo <= 0) continue;

                $fimPrev    = Carbon::parse($op->fim_previsto);
                $inicioPrev = Carbon::parse($op->inicio_previsto);

                if ($fimPrev <= $fimDiaUtil) {
                    // OP termina hoje — saldo completo
                    $previsto += $saldo;
                } else {
                    // OP multi-dia — contribui proporcional às horas dentro da janela 06:00-22:00
                    $inicioEfetivo  = $inicioPrev->max($inicioDia);
                    $fimEfetivo     = $fimPrev->min($fimDiaUtil);
                    $horasHoje      = max(0, $inicioEfetivo->diffInMinutes($fimEfetivo) / 60);
                    $taxaEfetiva    = ($op->taxa_por_hora ?? 0) * (($op->eficiencia ?? 70) / 100);
                    $capacidade     = $taxaEfetiva * $horasHoje;
                    $previsto      += min($saldo, $capacidade);
                }
            }
            $previsto = (int) round($previsto);

            // Grava no banco — imutável durante o dia
            DB::table('kpis_diarios')->updateOrInsert(
                ['data' => $hoje, 'modulo' => 'envase'],
                ['previsto_hoje' => $previsto, 'calculado_em' => now(), 'updated_at' => now(), 'created_at' => now()]
            );

            // Grava também no cache como backup
            $segundosAteMeiaNoite = Carbon::tomorrow()->startOfDay()->diffInSeconds(now());
            cache()->put('previsto_hoje_' . $hoje, $previsto, $segundosAteMeiaNoite);

            $this->info("Previsto gravado: $previsto caixas para $hoje (modulo: envase)");
        }

        // ── Sopro ──────────────────────────────────────────────────────
        $jaGravadoSopro = DB::table('kpis_diarios')
            ->where('data', $hoje)
            ->where('modulo', 'sopro')
            ->exists();

        if (!$jaGravadoSopro) {
            $opsSopro = DB::table('itens_programacao_sopro as ip')
                ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
                ->join('codi_eficiencia_sopro as ce', function ($join) {
                    $join->on('ce.numero_op', '=', 'ip.numero_op')
                         ->on('ce.programacao_sopro_id', '=', 'p.id');
                })
                ->where('p.status', 'confirmada')
                ->where('ce.inicio_previsto', '<', $fimDiaUtil->format('Y-m-d H:i:s'))
                ->where('ce.fim_previsto', '>', $inicioDia->format('Y-m-d H:i:s'))
                ->whereNotNull('ip.numero_op')
                ->select('ip.numero_op', 'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto')
                ->get();

            $previstoSopro = 0;
            foreach ($opsSopro as $op) {
                $fimPrev = Carbon::parse($op->fim_previsto);
                if ($fimPrev <= $fimDiaUtil) {
                    // quantidade em milheiros → unidades
                    $previstoSopro += max(0, $op->quantidade * 1000);
                }
            }
            $previstoSopro = (int) round($previstoSopro);

            DB::table('kpis_diarios')->updateOrInsert(
                ['data' => $hoje, 'modulo' => 'sopro'],
                ['previsto_hoje' => $previstoSopro, 'calculado_em' => now(), 'updated_at' => now(), 'created_at' => now()]
            );

            $segundosAteMeiaNoite = Carbon::tomorrow()->startOfDay()->diffInSeconds(now());
            cache()->put('previsto_hoje_sopro_' . $hoje, $previstoSopro, $segundosAteMeiaNoite);

            $this->info("Previsto Sopro gravado: $previstoSopro unidades para $hoje");
        } else {
            $this->info("Previsto Sopro de hoje ($hoje) já está gravado.");
        }
    }
}
