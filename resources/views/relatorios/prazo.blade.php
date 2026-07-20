@extends('layouts.app')

@section('titulo', 'Cumprimento de Prazo')

@section('conteudo')

@php
function formatarDesvio(int $minutos): string {
    $abs   = abs($minutos);
    $h     = intdiv($abs, 60);
    $m     = $abs % 60;
    $sinal = $minutos <= 0 ? '−' : '+';
    return $h > 0 ? "{$sinal}{$h}h {$m}m" : "{$sinal}{$m}m";
}
@endphp

<style>
    .rel-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rel-table thead tr { background: #1e3a8a; color: white; }
    .rel-table thead th { padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; white-space: nowrap; }
    .rel-table thead th.right { text-align: right; }
    .rel-table thead th.center { text-align: center; }
    .rel-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .rel-table tbody tr:hover td { background: #f1f5f9; }
    .rel-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .rel-table td.right { text-align: right; }
    .rel-table td.center { text-align: center; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .badge-prazo    { background: #EAF3DE; color: #3B6D11; }
    .badge-atrasada { background: #FCEBEB; color: #A32D2D; }
    .resumo-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .resumo-table thead tr { background: #334155; color: white; }
    .resumo-table thead th { padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 600; }
    .resumo-table thead th.right { text-align: right; }
    .resumo-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; }
    .resumo-table td.right { text-align: right; }
</style>

<div class="p-6">

    {{-- CABEÇALHO DA PÁGINA --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Relatório de Cumprimento de Prazo</h2>
            <p class="text-sm text-gray-500 mt-0.5">Ordens de produção concluídas — previsto × realizado</p>
        </div>
        <a href="{{ route('relatorios.prazo.print', request()->query()) }}" target="_blank"
           style="font-size:12px; padding:6px 14px; background:#1e3a8a; color:#fff; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            🖨️ Imprimir
        </a>
    </div>

    {{-- FILTROS --}}
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-6">
        <form method="GET" action="{{ route('relatorios.prazo') }}" class="flex items-end gap-4 flex-wrap">
            <label class="flex flex-col gap-1 text-xs text-slate-600 font-medium">
                Linha
                <select name="linha_id" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm text-slate-800 bg-white">
                    <option value="">Todas as linhas</option>
                    @foreach($linhas as $l)
                        <option value="{{ $l->id }}" {{ $linhaId == $l->id ? 'selected' : '' }}>
                            {{ $l->codigo }} — {{ $l->nome }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-1 text-xs text-slate-600 font-medium">
                Conclusão — de
                <input type="date" name="data_inicio" value="{{ $dataInicio }}"
                       class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm text-slate-800 bg-white">
            </label>
            <label class="flex flex-col gap-1 text-xs text-slate-600 font-medium">
                até
                <input type="date" name="data_fim" value="{{ $dataFim }}"
                       class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm text-slate-800 bg-white">
            </label>
            <button type="submit"
                    style="font-size:12px !important; padding:5px 16px !important; background:#1e3a8a !important; color:#fff !important; border:none !important; border-radius:6px !important; cursor:pointer !important; font-family:Arial,sans-serif !important;">
                Filtrar
            </button>
        </form>
    </div>

    {{-- METADADOS --}}
    <div class="flex gap-6 bg-white border border-slate-200 rounded-xl px-5 py-3 mb-6 text-sm">
        <div class="flex flex-col">
            <span class="text-xs uppercase text-slate-400 tracking-wide">Linha</span>
            <span class="font-semibold text-slate-800">
                @if($linhaId)
                    {{ $linhas->firstWhere('id', $linhaId)?->codigo }} — {{ $linhas->firstWhere('id', $linhaId)?->nome }}
                @else
                    Todas
                @endif
            </span>
        </div>
        <div class="flex flex-col">
            <span class="text-xs uppercase text-slate-400 tracking-wide">Período (conclusão)</span>
            <span class="font-semibold text-slate-800">
                {{ \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') }} até {{ \Carbon\Carbon::parse($dataFim)->format('d/m/Y') }}
            </span>
        </div>
        <div class="flex flex-col">
            <span class="text-xs uppercase text-slate-400 tracking-wide">OPs no resultado</span>
            <span class="font-semibold text-slate-800">{{ $totais['total'] }}</span>
        </div>
    </div>

    {{-- CARDS DE RESUMO --}}
    @if($totais['total'] > 0)
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:20px;">

        <div style="background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:8px; padding:10px 12px;">
            <p style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin:0 0 4px;">OPs concluídas</p>
            <p style="font-size:18px; font-weight:600; color:#1a1a1a; margin:0;">{{ $totais['total'] }}</p>
            <p style="font-size:10px; color:#94a3b8; margin:2px 0 0;">no período filtrado</p>
        </div>

        <div style="background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:8px; padding:10px 12px;">
            <p style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin:0 0 4px;">No prazo</p>
            <p style="font-size:18px; font-weight:600; color:#27500A; margin:0;">
                {{ $totais['no_prazo'] }}
                <span style="font-size:11px; color:#3B6D11;">{{ $totais['pct_prazo'] }}%</span>
            </p>
            <p style="font-size:10px; color:#94a3b8; margin:2px 0 0;">do total</p>
        </div>

        <div style="background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:8px; padding:10px 12px;">
            <p style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin:0 0 4px;">Atrasadas</p>
            <p style="font-size:18px; font-weight:600; color:#791F1F; margin:0;">
                {{ $totais['atrasadas'] }}
                @if($totais['total'] > 0)
                    <span style="font-size:11px; color:#A32D2D;">{{ round($totais['atrasadas'] / $totais['total'] * 100, 1) }}%</span>
                @endif
            </p>
            <p style="font-size:10px; color:#94a3b8; margin:2px 0 0;">do total</p>
        </div>

        <div style="background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:8px; padding:10px 12px;">
            <p style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin:0 0 4px;">Maior atraso</p>
            @if($totais['maior_atraso'])
                @php
                    $ma = $totais['maior_atraso'];
                    $h = intdiv(abs($ma->desvio_min), 60);
                    $m = abs($ma->desvio_min) % 60;
                @endphp
                <p style="font-size:18px; font-weight:600; color:#791F1F; margin:0;">+{{ $h }}h {{ $m }}m</p>
                <p style="font-size:10px; color:#94a3b8; margin:2px 0 0;">OP {{ $ma->numero_op }} — {{ $ma->linha }}</p>
            @else
                <p style="font-size:18px; font-weight:600; color:#27500A; margin:0;">—</p>
                <p style="font-size:10px; color:#94a3b8; margin:2px 0 0;">nenhum atraso</p>
            @endif
        </div>
    </div>
    @endif

    {{-- TABELA DETALHADA --}}
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-6">
    @if($ops->count() > 0)
    <table class="rel-table">
        <thead>
            <tr>
                <th>Linha</th>
                <th>OP</th>
                <th>Produto</th>
                <th class="right">Qtd programada</th>
                <th class="right">Qtd realizada</th>
                <th class="center">Fim previsto</th>
                <th class="center">Fim real</th>
                <th class="center">Desvio</th>
                <th class="center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ops as $op)
            @php
                $desvio  = (int) $op->desvio_min;
                $noPrazo = $desvio <= 0;
                $produto = $op->produto ?? $op->descricao_produto ?? $op->sku;
                $pctReal = $op->quantidade > 0 && $op->quantidade_realizada !== null
                    ? round($op->quantidade_realizada / $op->quantidade * 100, 1)
                    : null;
            @endphp
            <tr>
                <td style="font-weight:600;">{{ $op->linha }}</td>
                <td style="font-family:monospace;font-size:12px;">{{ $op->numero_op }}</td>
                <td>
                    <div>{{ $produto }}</div>
                    @if($op->sku)<div style="font-size:11px;color:#94a3b8;">{{ $op->sku }}</div>@endif
                </td>
                <td class="right">{{ number_format($op->quantidade, 0, ',', '.') }}</td>
                <td class="right">
                    {{ $op->quantidade_realizada !== null ? number_format($op->quantidade_realizada, 0, ',', '.') : '—' }}
                    @if($pctReal !== null)
                        <div style="font-size:11px;color:#94a3b8;">{{ $pctReal }}%</div>
                    @endif
                </td>
                <td class="center">{{ \Carbon\Carbon::parse($op->fim_previsto)->format('d/m/Y H:i') }}</td>
                <td class="center">{{ \Carbon\Carbon::parse($op->fim_real)->format('d/m/Y H:i') }}</td>
                <td class="center">
                    <span style="color:{{ $noPrazo ? '#16a34a' : '#dc2626' }};font-weight:600;">
                        {{ formatarDesvio($desvio) }}
                    </span>
                </td>
                <td class="center">
                    <span class="badge {{ $noPrazo ? 'badge-prazo' : 'badge-atrasada' }}">
                        {{ $noPrazo ? 'No prazo' : 'Atrasada' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="py-16 text-center text-slate-400 text-sm">
        Nenhuma OP concluída encontrada para os filtros selecionados.
    </div>
    @endif
    </div>

    {{-- RESUMO POR LINHA --}}
    @if($resumoPorLinha->count() > 0)
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-700 text-sm">Resumo por linha</div>
        <table class="resumo-table">
            <thead>
                <tr>
                    <th>Linha</th>
                    <th class="right">Total OPs</th>
                    <th class="right">No prazo</th>
                    <th class="right">Atrasadas</th>
                    <th class="right">% no prazo</th>
                    <th class="right">Atraso médio (atrasadas)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumoPorLinha as $r)
                @php
                    $pctLinha = $r['total'] > 0 ? round($r['no_prazo'] / $r['total'] * 100, 1) : 0;
                    $corPct   = $pctLinha >= 85 ? '#16a34a' : ($pctLinha >= 60 ? '#d97706' : '#dc2626');
                @endphp
                <tr>
                    <td style="font-weight:600;">{{ $r['linha'] }}</td>
                    <td class="right">{{ $r['total'] }}</td>
                    <td class="right" style="color:#16a34a;font-weight:600;">{{ $r['no_prazo'] }}</td>
                    <td class="right" style="color:{{ $r['atrasadas'] > 0 ? '#dc2626' : '#16a34a' }};font-weight:600;">{{ $r['atrasadas'] }}</td>
                    <td class="right" style="color:{{ $corPct }};font-weight:600;">{{ $pctLinha }}%</td>
                    <td class="right" style="color:#dc2626;">
                        {{ $r['atraso_medio'] !== null ? formatarDesvio((int) round($r['atraso_medio'])) : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#334155;color:white;">
                    <td style="padding:8px 12px;font-weight:700;">Total</td>
                    <td style="padding:8px 12px;text-align:right;">{{ $totais['total'] }}</td>
                    <td style="padding:8px 12px;text-align:right;">{{ $totais['no_prazo'] }}</td>
                    <td style="padding:8px 12px;text-align:right;">{{ $totais['atrasadas'] }}</td>
                    <td style="padding:8px 12px;text-align:right;">{{ $totais['pct_prazo'] !== null ? $totais['pct_prazo'].'%' : '—' }}</td>
                    <td style="padding:8px 12px;text-align:right;">—</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

</div>
@endsection
