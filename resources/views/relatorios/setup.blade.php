@extends('layouts.app')

@section('titulo', 'Relatório de Setup')

@section('conteudo')
<style>
    .rel-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rel-table thead tr { background: #1e3a8a; color: white; }
    .rel-table thead th { padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; white-space: nowrap; }
    .rel-table thead th.right { text-align: right; }
    .rel-table thead th.center { text-align: center; }
    .rel-table tbody tr.linha-dados:nth-child(4n+1) td,
    .rel-table tbody tr.linha-dados:nth-child(4n+2) td { background: #f9fafb; }
    .rel-table tbody tr.linha-dados:hover td { background: #f1f5f9; cursor: pointer; }
    .rel-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .rel-table td.right { text-align: right; }
    .rel-table td.center { text-align: center; }
    .barra-outer { background: #e5e7eb; border-radius: 3px; height: 6px; width: 70px; display:inline-block; }
    .barra-inner { height: 6px; border-radius: 3px; }
    .accordion-row { display: none; }
    .accordion-row.aberto { display: table-row; }
    .accordion-inner { padding: 12px 16px 16px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .sub-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .sub-table th { background: #334155; color: white; padding: 5px 10px; text-align: left; font-size: 11px; }
    .sub-table th.right { text-align: right; }
    .sub-table td { padding: 5px 10px; border-bottom: 1px solid #e9ecef; }
    .sub-table td.right { text-align: right; }
    .sub-table tr:last-child td { border-bottom: none; }
    .chevron { display: inline-block; transition: transform 0.2s; font-size: 10px; color: #94a3b8; margin-right: 4px; }
    .chevron.open { transform: rotate(90deg); }
</style>

<div class="p-6">

    {{-- CABEÇALHO --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Relatório de Setup</h2>
            <p class="text-sm text-gray-500 mt-0.5">Setup planejado × realizado por linha de produção</p>
        </div>
        <a href="{{ route('relatorios.setup.print', request()->query()) }}" target="_blank"
           style="font-size:12px; padding:6px 14px; background:#1e3a8a; color:#fff; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            🖨️ Imprimir
        </a>
    </div>

    {{-- FILTROS --}}
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-6">
        <form method="GET" action="{{ route('relatorios.setup') }}" class="flex items-end gap-4 flex-wrap">
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

    {{-- TABELA --}}
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    @if($porLinha->count() > 0)
    <table class="rel-table">
        <thead>
            <tr>
                <th>Linha</th>
                <th class="right">Tempo produção</th>
                <th class="right">Setup previsto</th>
                <th class="right">Setup realizado</th>
                <th class="center">Desvio</th>
                <th class="center">Nº trocas prev.</th>
                <th class="center">Média prev.</th>
                <th class="center">% setup do total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($porLinha as $i => $row)
            @php
                $pct      = $row['pct_setup'];
                $corPct   = $pct === null ? '#aaa'
                          : ($pct < 8 ? '#639922' : ($pct <= 15 ? '#EF9F27' : '#E24B4A'));
                $largBarra = $pct !== null ? min(100, $pct * 4) : 0;
                $desvio    = $row['desvio_min'];
                $corDesvio = $desvio === null ? '#aaa'
                           : ($desvio <= 0 ? '#27500A' : '#791F1F');
                $temDetalhe = count($row['plan_detalhes']) > 0 || count($row['real_detalhes']) > 0;
            @endphp
            <tr class="linha-dados" onclick="{{ $temDetalhe ? 'toggleAccordion('.$i.')' : '' }}" id="row-{{ $i }}">
                <td style="font-weight:600;">
                    @if($temDetalhe)
                        <span class="chevron" id="chevron-{{ $i }}">▶</span>
                    @endif
                    {{ $row['linha'] }}
                </td>
                <td class="right">
                    {{ $row['prod_horas'] }}h
                    <div style="font-size:11px;color:#94a3b8;">{{ $row['prod_min'] }}min</div>
                </td>
                <td class="right">
                    {{ $row['setup_horas'] }}h
                    <div style="font-size:11px;color:#94a3b8;">{{ $row['setup_min'] }}min</div>
                </td>
                <td class="right">
                    @if($row['real_horas'] !== null)
                        {{ $row['real_horas'] }}h
                        <div style="font-size:11px;color:#94a3b8;">{{ $row['real_min'] }}min</div>
                    @else
                        <span style="color:#aaa;">—</span>
                    @endif
                </td>
                <td class="center">
                    @if($desvio !== null)
                        <span style="color:{{ $corDesvio }}; font-weight:600;">
                            {{ $desvio > 0 ? '+' : '' }}{{ round($desvio / 60, 1) }}h
                        </span>
                    @else
                        <span style="color:#aaa;">—</span>
                    @endif
                </td>
                <td class="center" style="font-weight:600;">{{ $row['qtd_trocas'] }}</td>
                <td class="center">{{ $row['media_min'] }}min</td>
                <td class="center">
                    @if($pct !== null)
                        <div style="display:flex; align-items:center; gap:6px; justify-content:center;">
                            <div class="barra-outer">
                                <div class="barra-inner" style="width:{{ $largBarra }}%; background:{{ $corPct }};"></div>
                            </div>
                            <span style="color:{{ $corPct }}; font-weight:600;">{{ $pct }}%</span>
                        </div>
                    @else
                        <span style="color:#aaa;">—</span>
                    @endif
                </td>
            </tr>

            @if($temDetalhe)
            <tr class="accordion-row" id="accordion-{{ $i }}">
                <td colspan="8" style="padding:0;">
                    <div class="accordion-inner">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">

                            {{-- PLANEJADO --}}
                            <div>
                                <p style="font-size:11px; font-weight:700; color:#1e3a8a; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 6px;">
                                    Setup Planejado ({{ count($row['plan_detalhes']) }} trocas)
                                </p>
                                @if(count($row['plan_detalhes']) > 0)
                                <table class="sub-table">
                                    <thead>
                                        <tr>
                                            <th>Início</th>
                                            <th>Fim</th>
                                            <th class="right">Duração</th>
                                            <th>SKU</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($row['plan_detalhes'] as $p)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($p['inicio'])->format('d/m H:i') }}</td>
                                            <td>{{ \Carbon\Carbon::parse($p['fim'])->format('d/m H:i') }}</td>
                                            <td class="right">{{ $p['duracao'] }}min</td>
                                            <td style="color:#64748b;">{{ $p['sku'] ?? '—' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @else
                                <p style="font-size:12px;color:#94a3b8;">Sem registros planejados.</p>
                                @endif
                            </div>

                            {{-- REALIZADO --}}
                            <div>
                                <p style="font-size:11px; font-weight:700; color:#27500A; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 6px;">
                                    Setup Realizado CODI ({{ count($row['real_detalhes']) }} eventos)
                                </p>
                                @if(count($row['real_detalhes']) > 0)
                                <table class="sub-table">
                                    <thead>
                                        <tr>
                                            <th>Início</th>
                                            <th>Fim</th>
                                            <th class="right">Duração</th>
                                            <th>Tipo parada</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($row['real_detalhes'] as $r)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($r['inicio'])->format('d/m H:i') }}</td>
                                            <td>{{ \Carbon\Carbon::parse($r['fim'])->format('d/m H:i') }}</td>
                                            <td class="right">{{ $r['duracao'] }}min</td>
                                            <td style="color:#64748b;">{{ $r['nome_parada'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @else
                                <p style="font-size:12px;color:#94a3b8;">Sem eventos registrados no CODI para o período.</p>
                                @endif
                            </div>

                        </div>
                    </div>
                </td>
            </tr>
            @endif

            @endforeach
        </tbody>
        <tfoot>
            <tr style="background:#1e3a8a; color:#fff;">
                <td style="padding:8px 12px; font-weight:700; font-size:11px; text-transform:uppercase;">Total</td>
                <td style="padding:8px 12px; text-align:right;">{{ $totais['prod_horas'] }}h</td>
                <td style="padding:8px 12px; text-align:right;">{{ $totais['setup_horas'] }}h</td>
                <td style="padding:8px 12px; text-align:right;">
                    @php $realTotalMin = $porLinha->sum('real_min'); @endphp
                    @if($realTotalMin > 0)
                        {{ round($realTotalMin / 60, 1) }}h
                    @else
                        —
                    @endif
                </td>
                <td style="padding:8px 12px; text-align:right;">
                    @php $desvioTotalMin = $realTotalMin - $porLinha->sum('setup_min'); @endphp
                    @if($realTotalMin > 0)
                        @php $cor = $desvioTotalMin > 0 ? '#ff9999' : '#99ffcc'; @endphp
                        <span style="color:{{ $cor }}; font-weight:600;">
                            {{ $desvioTotalMin > 0 ? '+' : '' }}{{ round($desvioTotalMin / 60, 1) }}h
                        </span>
                    @else
                        —
                    @endif
                </td>
                <td style="padding:8px 12px; text-align:center;">{{ $totais['qtd_trocas'] }}</td>
                <td style="padding:8px 12px; text-align:center;">{{ $totais['media_min'] }}min</td>
                <td style="padding:8px 12px;">{{ $totais['pct_setup'] !== null ? $totais['pct_setup'].'%' : '—' }}</td>
            </tr>
        </tfoot>
    </table>
    @else
    <div class="py-16 text-center text-slate-400 text-sm">
        Nenhum dado de setup encontrado para os filtros selecionados.
    </div>
    @endif
    </div>

</div>

<script>
function toggleAccordion(i) {
    const acc = document.getElementById('accordion-' + i);
    const chv = document.getElementById('chevron-' + i);
    if (!acc) return;
    const aberto = acc.classList.contains('aberto');
    acc.classList.toggle('aberto', !aberto);
    if (chv) chv.classList.toggle('open', !aberto);
}
</script>
@endsection
