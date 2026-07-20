@extends('layouts.app')

@section('titulo', 'Relatório de Desempenho')

@section('conteudo')
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
    .barra-outer { background: #e5e7eb; border-radius: 3px; height: 6px; min-width: 60px; }
    .barra-inner { height: 6px; border-radius: 3px; }
</style>

<div class="p-6">

    {{-- CABEÇALHO DA PÁGINA --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Relatório de Desempenho</h2>
            <p class="text-sm text-gray-500 mt-0.5">Controle e Sequenciamento PCP</p>
        </div>
        <a href="{{ route('relatorios.desempenho.print', request()->query()) }}" target="_blank"
           style="font-size:12px; padding:6px 14px; background:#1e3a8a; color:#fff; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            🖨️ Imprimir
        </a>
    </div>

    {{-- FILTROS --}}
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-6">
        <form method="GET" action="{{ route('relatorios.desempenho') }}" class="flex items-end gap-4 flex-wrap">
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
                Data início
                <input type="date" name="data_inicio" value="{{ $dataInicio }}"
                       class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm text-slate-800 bg-white">
            </label>
            <label class="flex flex-col gap-1 text-xs text-slate-600 font-medium">
                Data fim
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
            <span class="text-xs uppercase text-slate-400 tracking-wide">Período</span>
            <span class="font-semibold text-slate-800">
                {{ \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') }} até {{ \Carbon\Carbon::parse($dataFim)->format('d/m/Y') }}
            </span>
        </div>
        <div class="flex flex-col">
            <span class="text-xs uppercase text-slate-400 tracking-wide">Gerado em</span>
            <span class="font-semibold text-slate-800">{{ $gerado }}</span>
        </div>
        <div class="flex flex-col">
            <span class="text-xs uppercase text-slate-400 tracking-wide">Linhas no resultado</span>
            <span class="font-semibold text-slate-800">{{ count($resultado) }}</span>
        </div>
    </div>

    {{-- TABELA --}}
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-4">
    @if(count($resultado) > 0)
    <table class="rel-table">
        <thead>
            <tr>
                <th>Linha</th>
                <th class="center">OPs</th>
                <th class="right">Cx previstas</th>
                <th class="right">Cx realizadas</th>
                <th class="center">% Realizado</th>
                <th class="center">Atraso médio início</th>
                <th class="center">OEE médio</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resultado as $row)
            @php
                $pct       = $row['pct'];
                $corBarra  = $pct === null ? '#d1d5db'
                           : ($pct >= 85 ? '#16a34a' : ($pct >= 60 ? '#f59e0b' : '#ef4444'));
                $largBarra = $pct !== null ? min(100, $pct) : 0;
                $atraso    = $row['atraso_medio'];
                $corAtraso = $atraso === null ? '#aaa'
                           : ($atraso <= 0 ? '#16a34a' : ($atraso <= 30 ? '#f59e0b' : '#ef4444'));
                $oee       = $row['oee_medio'];
                $corOee    = $oee === null ? '#aaa'
                           : ($oee >= 75 ? '#16a34a' : ($oee >= 50 ? '#f59e0b' : '#ef4444'));
            @endphp
            <tr>
                <td style="font-weight:600;">{{ $row['linha'] }}</td>
                <td class="center">{{ $row['total_ops'] }}</td>
                <td class="right">{{ number_format($row['previsto'], 0, ',', '.') }}</td>
                <td class="right">{{ number_format($row['realizado'], 0, ',', '.') }}</td>
                <td class="center">
                    @if($pct !== null)
                        <div style="display:flex; align-items:center; gap:6px; justify-content:center;">
                            <div class="barra-outer" style="width:60px;">
                                <div class="barra-inner" style="width:{{ $largBarra }}%; background:{{ $corBarra }};"></div>
                            </div>
                            <span style="color:{{ $corBarra }}; font-weight:600;">{{ $pct }}%</span>
                        </div>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td class="center">
                    @if($atraso !== null)
                        <span style="color:{{ $corAtraso }}; font-weight:600;">
                            {{ $atraso > 0 ? '+' : '' }}{{ $atraso }} min
                        </span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td class="center">
                    @if($oee !== null)
                        <span style="color:{{ $corOee }}; font-weight:600;">{{ $oee }}%</span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background:#1e3a8a;color:white;">
                <td style="padding:8px 12px;font-weight:700;">Total</td>
                <td style="padding:8px 12px;text-align:center;">{{ collect($resultado)->sum('total_ops') }}</td>
                <td style="padding:8px 12px;text-align:right;">{{ number_format(collect($resultado)->sum('previsto'), 0, ',', '.') }}</td>
                <td style="padding:8px 12px;text-align:right;">{{ number_format(collect($resultado)->sum('realizado'), 0, ',', '.') }}</td>
                <td style="padding:8px 12px;text-align:center;" colspan="3">—</td>
            </tr>
        </tfoot>
    </table>
    @else
    <div class="py-16 text-center text-slate-400 text-sm">
        Nenhum dado encontrado para os filtros selecionados.
    </div>
    @endif
    </div>

    {{-- TOTAIS --}}
    @if(count($resultado) > 0)
    @php
        $totalPrevisto  = array_sum(array_column($resultado, 'previsto'));
        $totalRealizado = array_sum(array_column($resultado, 'realizado'));
        $pctGeral       = $totalPrevisto > 0 ? round($totalRealizado / $totalPrevisto * 100, 1) : null;
        $oeeValidos     = array_filter(array_column($resultado, 'oee_medio'), fn($v) => $v !== null);
        $oeeMedioGeral  = count($oeeValidos) > 0 ? round(array_sum($oeeValidos) / count($oeeValidos), 1) : null;
    @endphp
    <div style="background:#1e293b; color:white; border-radius:8px; padding:10px 20px; display:flex; justify-content:space-between;">
        <div style="display:flex; flex-direction:column; align-items:center;">
            <span style="font-size:10px; text-transform:uppercase; color:#94a3b8; letter-spacing:0.04em;">Cx previstas total</span>
            <span style="font-size:16px; font-weight:600; margin-top:2px;">{{ number_format($totalPrevisto, 0, ',', '.') }}</span>
        </div>
        <div style="display:flex; flex-direction:column; align-items:center;">
            <span style="font-size:10px; text-transform:uppercase; color:#94a3b8; letter-spacing:0.04em;">Cx realizadas total</span>
            <span style="font-size:16px; font-weight:600; margin-top:2px;">{{ number_format($totalRealizado, 0, ',', '.') }}</span>
        </div>
        <div style="display:flex; flex-direction:column; align-items:center;">
            <span style="font-size:10px; text-transform:uppercase; color:#94a3b8; letter-spacing:0.04em;">% geral</span>
            <span style="font-size:16px; font-weight:600; margin-top:2px;">{{ $pctGeral !== null ? $pctGeral.'%' : '—' }}</span>
        </div>
        <div style="display:flex; flex-direction:column; align-items:center;">
            <span style="font-size:10px; text-transform:uppercase; color:#94a3b8; letter-spacing:0.04em;">OEE médio geral</span>
            <span style="font-size:16px; font-weight:600; margin-top:2px;">{{ $oeeMedioGeral !== null ? $oeeMedioGeral.'%' : '—' }}</span>
        </div>
    </div>
    @endif

</div>
@endsection
