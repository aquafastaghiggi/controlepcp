<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

/**
 * Verifica se alguma OP do Sopro produziu além da quantidade real cadastrada
 * no CODI (endpoint ordemProducao), dentro da janela de um dia produtivo:
 * 06:30 de ontem até 06:30 de hoje.
 *
 * Roda diariamente às 06:35, verificando o dia que ACABOU DE FECHAR.
 */
class VerificarExcessoProducaoSopro extends Command
{
    protected $signature   = 'sopro:verificar-excesso-producao';
    protected $description = 'Verifica OPs do Sopro que produziram além da quantidade real (CODI ordemProducao) no dia produtivo 06:30-06:30';

    private const CODI_BASE = 'http://192.168.8.246:8080';
    private const CODI_USER = 'Aghiggi';
    private const CODI_PASS = '@Ag0351@';

    private const ORACLE_DSN  = '192.168.8.190:1521/prod.aquafast.com.br';
    private const ORACLE_USER = 'cigam';
    private const ORACLE_PASS = 'CIGAM';

    public function handle(): void
    {
        $inicioJanela = Carbon::yesterday()->setHour(6)->setMinute(30)->setSecond(0);
        $fimJanela    = Carbon::today()->setHour(6)->setMinute(30)->setSecond(0);

        $this->info("Verificando janela: {$inicioJanela->format('d/m H:i')} → {$fimJanela->format('d/m H:i')}");

        $divergencias = [];

        $maquinas = DB::table('maquinas')
            ->where('ativo', true)
            ->whereNotNull('codigo_recurso')
            ->orderBy('codigo')
            ->get();

        foreach ($maquinas as $maquina) {
            $opsNaJanela = DB::table('codi_eventos')
                ->where('codigo_recurso', $maquina->codigo_recurso)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioJanela)
                ->where('inicio_evento', '<', $fimJanela)
                ->distinct()
                ->pluck('ordem_producao');

            foreach ($opsNaJanela as $numeroOp) {
                // Quantidade produzida no CODI na janela
                $produzidoCodi = (float) DB::table('codi_eventos')
                    ->where('codigo_recurso', $maquina->codigo_recurso)
                    ->where('ordem_producao', $numeroOp)
                    ->where('tipo_evento', 'PRODUCAO')
                    ->where('inicio_evento', '>=', $inicioJanela)
                    ->where('inicio_evento', '<', $fimJanela)
                    ->sum('quantidade');

                // Quantidade da OP cadastrada no CODI (referência)
                $dadosOp = $this->buscarQuantidadeReal($numeroOp);
                if (!$dadosOp) continue;

                $quantidadeOp     = (float) ($dadosOp['quantidade'] ?? 0);
                $descricaoProduto = $dadosOp['descricao'] ?? '';

                // Quantidade apontada no CIGAM (Oracle)
                $produzidoCigam = $this->buscarRealizadoCigam($numeroOp, $inicioJanela, $fimJanela);

                // Só alerta se CODI e CIGAM divergirem
                if ($produzidoCigam === null) {
                    $this->line("OP {$numeroOp} — CIGAM indisponível, pulando.");
                    continue;
                }

                $diferenca = $produzidoCodi - $produzidoCigam;

                // Tolerância de 50 unidades para evitar alertas por arredondamento
                if (abs($diferenca) <= 50) continue;

                $divergencias[] = [
                    'maquina'         => $maquina->nome,
                    'codigo'          => $maquina->codigo,
                    'op_rodando'      => $numeroOp,
                    'prod_rodando'    => $descricaoProduto,
                    'qtd_op'          => $quantidadeOp,
                    'qtd_codi'        => $produzidoCodi,
                    'qtd_cigam'       => $produzidoCigam,
                    'diferenca'       => $diferenca,
                    'turno'           => $this->turnoPredominante((string) $maquina->codigo_recurso, $numeroOp, $inicioJanela, $fimJanela),
                ];

                $this->warn("{$maquina->codigo} — OP {$numeroOp} | CODI: {$produzidoCodi} | CIGAM: {$produzidoCigam} | Diferença: {$diferenca}");
            }
        }

        foreach ($divergencias as $d) {
            $jaExiste = DB::table('divergencias_op')
                ->where('modulo', 'sopro')
                ->where('tipo', 'divergencia_codi_cigam')
                ->where('op_rodando', $d['op_rodando'])
                ->whereDate('detectado_em', Carbon::today())
                ->exists();

            if (!$jaExiste) {
                DB::table('divergencias_op')->insert([
                    'modulo'                    => 'sopro',
                    'tipo'                      => 'divergencia_codi_cigam',
                    'linha_nome'                => $d['maquina'],
                    'linha_codigo'              => $d['codigo'],
                    'op_esperada'               => (string) $d['qtd_op'],
                    'prod_esperada'             => $d['prod_rodando'],
                    'op_rodando'                => $d['op_rodando'],
                    'prod_rodando'              => $d['prod_rodando'],
                    'quantidade_prevista'       => $d['qtd_op'],
                    'quantidade_realizada'      => $d['qtd_codi'],
                    'quantidade_realizada_cigam'=> $d['qtd_cigam'],
                    'quantidade_excesso'        => $d['diferenca'],
                    'turno_predominante'        => $d['turno'],
                    'detectado_em'              => now(),
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ]);
            }
        }

        if (!empty($divergencias)) {
            $this->enviarEmail($divergencias);
            $this->info(count($divergencias) . ' divergência(s) detectadas — e-mail enviado.');
        } else {
            $this->info('Nenhuma divergência CODI vs CIGAM encontrada na janela.');
        }
    }

    /**
     * Busca o codigoOrdemProducao no dados_raw do CODI e consulta o endpoint
     * /ordemProducao/{id} para obter a quantidade real cadastrada.
     */
    /**
     * Determina em qual turno do Sopro ocorreu a maior parte da produção
     * dentro da janela analisada.
     * Turnos: T1 05:30-14:30 | T2 13:30-22:30 | T3 21:30-06:30 (overnight)
     */
    private function turnoPredominante(string $codigoRecurso, string $numeroOp, Carbon $inicioJanela, Carbon $fimJanela): ?string
    {
        $eventos = DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecurso)
            ->where('ordem_producao', $numeroOp)
            ->where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioJanela)
            ->where('inicio_evento', '<', $fimJanela)
            ->get(['inicio_evento', 'quantidade']);

        if ($eventos->isEmpty()) {
            return null;
        }

        $porTurno = ['T1' => 0.0, 'T2' => 0.0, 'T3' => 0.0];

        foreach ($eventos as $e) {
            $hora = Carbon::parse($e->inicio_evento)->format('H:i');
            $turno = match (true) {
                $hora >= '05:30' && $hora < '13:30' => 'T1',
                $hora >= '13:30' && $hora < '21:30' => 'T2',
                default => 'T3',
            };
            $porTurno[$turno] += (float) $e->quantidade;
        }

        arsort($porTurno);
        return array_key_first($porTurno);
    }

    private function buscarQuantidadeReal(string $numeroOp): ?array
    {
        $row = DB::table('codi_eventos')
            ->where('ordem_producao', $numeroOp)
            ->whereNotNull('dados_raw')
            ->first(['dados_raw']);

        if (!$row) {
            return null;
        }

        $raw = is_array($row->dados_raw) ? $row->dados_raw : json_decode($row->dados_raw, true);
        $codigoOrdemProducao = $raw['ordens'][0]['ordemProducao']['codigo']
            ?? $raw['ordens'][0]['ordemProducao']['codigoOrdemProducao']
            ?? null;

        if (!$codigoOrdemProducao) {
            return null;
        }

        $url = self::CODI_BASE . '/action/ger/webservice/rest/ordemProducao/' . $codigoOrdemProducao;
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERPWD, self::CODI_USER . ':' . self::CODI_PASS);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$resp) {
            return null;
        }

        $dados = json_decode($resp, true);
        if (!isset($dados['quantidade'])) {
            return null;
        }

        return [
            'quantidade' => $dados['quantidade'],
            'descricao'  => $dados['item']['nomeItem'] ?? null,
        ];
    }

    private function buscarRealizadoCigam(string $numeroOp, Carbon $inicioJanela, Carbon $fimJanela): ?float
    {
        try {
            $conn = oci_connect(self::ORACLE_USER, self::ORACLE_PASS, self::ORACLE_DSN);
            if (!$conn) return null;

            $dataInicio = $inicioJanela->format('Y-m-d');
            $dataFim    = $fimJanela->format('Y-m-d');
            $op         = (int) $numeroOp;

            $sql = "SELECT SUM(QUANTIDADE) FROM vw_prod
                    WHERE OP = :op
                    AND CD_TP_OPERACAO = '82000'
                    AND DT_MOVIMENTO >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                    AND DT_MOVIMENTO <= TO_DATE(:data_fim, 'YYYY-MM-DD')";

            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':op', $op);
            oci_bind_by_name($stmt, ':data_inicio', $dataInicio);
            oci_bind_by_name($stmt, ':data_fim', $dataFim);
            oci_execute($stmt);
            $row = oci_fetch_array($stmt, OCI_NUM);
            oci_free_statement($stmt);
            oci_close($conn);

            $totalMilheiros = $row[0] ?? null;
            return $totalMilheiros !== null ? (float) $totalMilheiros * 1000 : null;
        } catch (\Throwable $e) {
            \Log::warning('Oracle VW_PROD falhou', ['op' => $numeroOp, 'erro' => $e->getMessage()]);
            return null;
        }
    }

    private function enviarEmail(array $divergencias): void
    {
        $html  = '<h2 style="color:#E24B4A;font-family:Arial,sans-serif">⚠ Divergência CODI × CIGAM — Sopro</h2>';
        $html .= '<p style="font-family:Arial,sans-serif">As seguintes OPs do Sopro têm quantidade produzida (CODI) divergente do apontado no CIGAM (janela 06:30–06:30):</p>';
        $html .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:13px">';
        $html .= '<tr style="background:#f3f4f6">
                    <th>Máquina</th>
                    <th>OP</th>
                    <th>Frasco</th>
                    <th>Qtd. OP</th>
                    <th>CODI</th>
                    <th>CIGAM</th>
                    <th>Diferença</th>
                  </tr>';

        foreach ($divergencias as $d) {
            $sinal = $d['diferenca'] > 0 ? '+' : '';
            $html .= "<tr>
                <td><strong>{$d['codigo']}</strong></td>
                <td>{$d['op_rodando']}</td>
                <td>{$d['prod_rodando']}</td>
                <td>{$d['qtd_op']}</td>
                <td>{$d['qtd_codi']}</td>
                <td>{$d['qtd_cigam']}</td>
                <td style='color:#E24B4A;font-weight:bold'>{$sinal}{$d['diferenca']}</td>
            </tr>";
        }

        $html .= '</table>';
        $html .= '<p style="color:#6b7280;font-size:11px;margin-top:16px;font-family:Arial,sans-serif">ControlePCP V2 — Aquafast · Verificação diária às 06:35</p>';

        Mail::html($html, function ($msg) use ($divergencias) {
            $msg->to('aghiggi@aquafast.com.br')
                ->subject('⚠ Sopro — ' . count($divergencias) . ' OP(s) com divergência CODI × CIGAM');
        });
    }
}
