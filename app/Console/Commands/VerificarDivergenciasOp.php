<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class VerificarDivergenciasOp extends Command
{
    protected $signature   = 'pcp:verificar-divergencias';
    protected $description = 'Verifica divergências entre OPs esperadas no PCP e OPs rodando no CODI';

    public function handle(): void
    {
        $agora     = Carbon::now();
        $diaSemana = $agora->dayOfWeek; // 0=domingo, 6=sábado
        $hora      = $agora->hour;

        // Não enviar nos fins de semana antes das 06:00
        if (($diaSemana === 0 || $diaSemana === 6) && $hora < 6) {
            return;
        }

        $inicioDia    = Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        $divergencias = [];

        $linhas = DB::table('linhas')
            ->where('ativo', true)
            ->whereNotNull('codigo_recurso')
            ->orderBy('codigo')
            ->get();

        foreach ($linhas as $linha) {

            // OP mais recente rodando no CODI via codigo_recurso
            $opRodando = DB::table('codi_eventos')
                ->where('codigo_recurso', $linha->codigo_recurso)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia)
                ->orderByDesc('inicio_evento')
                ->value('ordem_producao');

            if (!$opRodando) continue;

            // Verifica se essa OP existe na programação confirmada da linha
            $opNaProgramacao = DB::table('itens_programacao as ip')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->where('p.linha_id', $linha->id)
                ->where('p.status', 'confirmada')
                ->where('ip.numero_op', $opRodando)
                ->exists();

            if (!$opNaProgramacao) {
                // Busca descrição do produto
                $descricao = DB::table('itens_programacao')
                    ->where('numero_op', $opRodando)
                    ->value('descricao_produto');

                // Se não encontrou no PCP, busca no dados_raw do CODI
                if (!$descricao) {
                    $eventoRaw = DB::table('codi_eventos')
                        ->where('ordem_producao', $opRodando)
                        ->whereNotNull('dados_raw')
                        ->first(['dados_raw']);
                    if ($eventoRaw) {
                        $raw = is_array($eventoRaw->dados_raw)
                            ? $eventoRaw->dados_raw
                            : json_decode($eventoRaw->dados_raw, true);
                        $descricao = $raw['ordens'][0]['ordemProducao']['item']['nomeItem'] ?? '';
                    }
                }

                $divergencias[] = [
                    'linha'         => $linha->nome,
                    'codigo'        => $linha->codigo,
                    'op_esperada'   => 'Ver programação',
                    'prod_esperada' => 'OP não está na programação da linha',
                    'op_rodando'    => $opRodando,
                    'prod_rodando'  => $descricao ?? '',
                    'horario'       => Carbon::now()->format('d/m/Y H:i'),
                ];

                $this->warn("{$linha->nome} — OP {$opRodando} não está na programação confirmada!");
            }
        }

        // Marcar como resolvidas as que não aparecem mais (apenas Envase)
        $linhasComDivergencia = collect($divergencias)->pluck('linha')->toArray();
        DB::table('divergencias_op')
            ->where('modulo', 'envase')
            ->whereNull('resolvida_em')
            ->whereNotIn('linha_nome', $linhasComDivergencia)
            ->update(['resolvida_em' => now()]);

        // Fechar registros antigos quando a OP mudou (apenas Envase)
        foreach ($divergencias as $d) {
            DB::table('divergencias_op')
                ->where('modulo', 'envase')
                ->whereNull('resolvida_em')
                ->where('linha_nome', $d['linha'])
                ->where('op_rodando', '!=', $d['op_rodando'])
                ->update(['resolvida_em' => now()]);
        }

        // Inserir novas divergências
        foreach ($divergencias as $d) {
            $jaExiste = DB::table('divergencias_op')
                ->where('linha_nome', $d['linha'])
                ->where('op_rodando', $d['op_rodando'])
                ->whereNull('resolvida_em')
                ->exists();

            if (!$jaExiste) {
                DB::table('divergencias_op')->insert([
                    'linha_nome'    => $d['linha'],
                    'linha_codigo'  => $d['codigo'],
                    'op_esperada'   => $d['op_esperada'],
                    'prod_esperada' => $d['prod_esperada'],
                    'op_rodando'    => $d['op_rodando'],
                    'prod_rodando'  => $d['prod_rodando'],
                    'detectado_em'  => now(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        // Busca todas ativas para o e-mail
        $todasAtivas = DB::table('divergencias_op')
            ->whereNull('resolvida_em')
            ->get()
            ->map(fn($d) => [
                'linha'         => $d->linha_nome,
                'codigo'        => $d->linha_codigo,
                'op_esperada'   => $d->op_esperada,
                'prod_esperada' => $d->prod_esperada,
                'op_rodando'    => $d->op_rodando,
                'prod_rodando'  => $d->prod_rodando,
                'horario'       => Carbon::parse($d->detectado_em)->format('d/m/Y H:i'),
            ])->toArray();

        if (!empty($todasAtivas)) {
            $this->enviarEmail($todasAtivas);
            $this->info(count($todasAtivas) . ' divergência(s) ativas — e-mail enviado.');
        } else {
            $this->info('Nenhuma divergência encontrada.');
        }
    }

    private function enviarEmail(array $divergencias): void
    {
        $html  = '<h2 style="color:#E24B4A;font-family:Arial,sans-serif">⚠ Divergências de OP — ControlePCP V2</h2>';
        $html .= '<p style="font-family:Arial,sans-serif">As seguintes linhas estão com divergência entre o planejado (PCP) e o executado (CODI):</p>';
        $html .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:13px">';
        $html .= '<tr style="background:#f3f4f6">
                    <th>Linha</th>
                    <th>OP Esperada (PCP)</th>
                    <th>Produto Esperado</th>
                    <th>OP Rodando (CODI)</th>
                    <th>Produto Rodando</th>
                    <th>Detectado em</th>
                  </tr>';

        foreach ($divergencias as $d) {
            $html .= "<tr>
                <td><strong>{$d['linha']}</strong></td>
                <td style='color:#639922;font-weight:bold'>{$d['op_esperada']}</td>
                <td style='color:#639922'>{$d['prod_esperada']}</td>
                <td style='color:#E24B4A;font-weight:bold'>{$d['op_rodando']}</td>
                <td style='color:#E24B4A'>{$d['prod_rodando']}</td>
                <td style='color:#6b7280'>{$d['horario']}</td>
            </tr>";
        }

        $html .= '</table>';
        $html .= '<p style="color:#6b7280;font-size:11px;margin-top:16px;font-family:Arial,sans-serif">ControlePCP V2 — Aquafast · Verificação automática a cada 11 minutos</p>';

        Mail::html($html, function($msg) use ($divergencias) {
            $msg->to('aghiggi@aquafast.com.br')
                ->subject('⚠ ControlePCP — ' . count($divergencias) . ' divergência(s) detectada(s)');
        });
    }
}
