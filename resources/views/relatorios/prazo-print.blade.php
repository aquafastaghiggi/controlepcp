<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>ControlePCP — Cumprimento de Prazo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #1a1a1a; background: white; }

        /* CABEÇALHO */
        .header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 20px 8px;
            border-bottom: 3px solid #1e3a8a;
            margin-bottom: 10px;
        }
        .header-logo img { height: 36px; width: auto; }
        .header-titulo { text-align: center; flex: 1; padding: 0 20px; }
        .header-titulo h1 { font-size: 16px; font-weight: bold; color: #1e3a8a; letter-spacing: 0.5px; }
        .header-titulo p { font-size: 10px; color: #666; margin-top: 2px; }
        .header-info { text-align: right; font-size: 10px; color: #555; line-height: 1.6; }
        .header-info strong { color: #1a1a1a; }

        /* FILTROS */
        .filtros-form {
            margin: 0 20px 12px;
            padding: 10px 16px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;
        }
        .filtros-form label { font-size: 10px; color: #555; display: flex; flex-direction: column; gap: 3px; }
        .filtros-form select,
        .filtros-form input[type="date"] {
            padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px;
            font-size: 11px; color: #1a1a1a; background: white;
        }
        .filtros-form button {
            padding: 5px 16px; background: #1e3a8a; color: white;
            border: none; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;
        }
        .filtros-form button:hover { background: #1e40af; }

        /* METADADOS */
        .meta {
            display: flex; gap: 24px; padding: 5px 20px;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
            margin: 0 20px 10px; font-size: 11px;
        }
        .meta-item { display: flex; flex-direction: column; }
        .meta-label { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
        .meta-value { font-weight: bold; color: #1a1a1a; margin-top: 1px; }

        /* CARDS DE RESUMO */
        .cards {
            display: flex; gap: 12px; padding: 0 20px; margin-bottom: 14px; flex-wrap: wrap;
        }
        .card {
            flex: 1; min-width: 140px;
            padding: 10px 14px;
            border-radius: 8px; border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .card-label { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: 0.4px; }
        .card-value { font-size: 20px; font-weight: bold; margin: 3px 0 1px; color: #1a1a1a; }
        .card-sub { font-size: 10px; color: #666; }
        .card.verde { border-color: #bbf7d0; background: #f0fdf4; }
        .card.verde .card-value { color: #16a34a; }
        .card.vermelho { border-color: #fecaca; background: #fef2f2; }
        .card.vermelho .card-value { color: #dc2626; }
        .card.azul { border-color: #bfdbfe; background: #eff6ff; }
        .card.azul .card-value { color: #1e3a8a; }

        /* TABELA */
        .tabela-wrapper { padding: 0 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        thead tr { background: #1e3a8a; color: white; }
        thead th { padding: 5px 8px; text-align: left; font-size: 9.5px; font-weight: 600; letter-spacing: 0.3px; white-space: nowrap; }
        thead th.right { text-align: right; }
        thead th.center { text-align: center; }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        td { padding: 5px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        td.right { text-align: right; }
        td.center { text-align: center; }
        td.muted { color: #aaa; }

        /* BADGE STATUS */
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 4px;
            font-size: 9px; font-weight: 700; white-space: nowrap;
        }
        .badge-prazo    { background: #EAF3DE; color: #3B6D11; }
        .badge-atrasada { background: #FCEBEB; color: #A32D2D; }

        /* DESVIO */
        .desvio-ok      { color: #16a34a; font-weight: 600; }
        .desvio-late    { color: #dc2626; font-weight: 600; }

        /* RESUMO POR LINHA */
        .resumo-wrapper { padding: 0 20px; margin-top: 20px; }
        .resumo-titulo {
            font-size: 11px; font-weight: bold; color: #1a1a1a;
            margin-bottom: 6px; padding-left: 2px;
        }
        .resumo-table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .resumo-table thead tr { background: #334155; color: white; }
        .resumo-table thead th { padding: 4px 8px; text-align: left; font-size: 9.5px; }
        .resumo-table thead th.right { text-align: right; }
        .resumo-table td { padding: 4px 8px; border-bottom: 1px solid #f0f0f0; }
        .resumo-table td.right { text-align: right; }

        /* RODAPÉ */
        .rodape {
            margin-top: 16px; padding: 8px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex; justify-content: space-between;
            font-size: 9px; color: #aaa;
        }

        /* BOTÕES */
        .acoes { position: fixed; top: 16px; right: 16px; display: flex; gap: 8px; z-index: 999; }
        .btn-imprimir {
            padding: 10px 20px; background: #1e3a8a; color: white;
            border: none; cursor: pointer; border-radius: 8px; font-size: 13px; font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .btn-imprimir:hover { background: #1e40af; }
        .btn-voltar {
            padding: 10px 16px; background: white; color: #555;
            border: 1px solid #d1d5db; cursor: pointer; border-radius: 8px;
            font-size: 13px; font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-decoration: none; display: flex; align-items: center;
        }
        .btn-voltar:hover { background: #f9fafb; }

        @media print {
            @page { size: A4 landscape; margin: 6mm 8mm; }

            body { font-size: 8px !important; margin: 0; }

            .acoes { display: none; }
            .filtros-form { display: none; }

            /* Header compacto */
            .header { padding: 4px 8px 4px; margin-bottom: 5px; }
            .header-logo img { height: 24px; }
            .header-titulo h1 { font-size: 11px; }
            .header-titulo p { font-size: 7px; }
            .header-info { font-size: 7px; }

            /* Meta compacto */
            .meta { padding: 3px 8px; margin: 0 0 5px; gap: 12px; }
            .meta-label { font-size: 7px; }
            .meta-value { font-size: 8px; }

            /* Cards compactos */
            .cards { padding: 0; gap: 6px !important; margin-bottom: 6px !important; }
            .card { padding: 5px 8px !important; min-width: 0; }
            .card-label { font-size: 7px; }
            .card-value { font-size: 13px !important; margin: 1px 0; }
            .card-sub { font-size: 7px; }

            /* Tabela compacta */
            .tabela-wrapper { padding: 0; }
            table { font-size: 8px !important; }
            thead th { padding: 3px 5px !important; font-size: 7px !important; }
            td { padding: 2px 5px !important; }
            .badge { font-size: 7px; padding: 1px 4px; }

            /* Resumo por linha compacto */
            .resumo-wrapper { padding: 0; margin-top: 6px; }
            .resumo-titulo { font-size: 8px; margin-bottom: 3px; }
            .resumo-table { font-size: 8px !important; }
            .resumo-table thead th { padding: 2px 5px !important; font-size: 7px !important; }
            .resumo-table td { padding: 2px 5px !important; }

            /* Rodapé */
            .rodape { margin-top: 6px; padding: 4px 8px; font-size: 7px; }

            /* Evita quebra de página dentro de blocos */
            .cards, .resumo-wrapper, thead { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

@php
function formatarDesvio(int $minutos): string {
    $abs  = abs($minutos);
    $h    = intdiv($abs, 60);
    $m    = $abs % 60;
    $sinal = $minutos <= 0 ? '−' : '+';
    return $h > 0 ? "{$sinal}{$h}h {$m}m" : "{$sinal}{$m}m";
}
@endphp

{{-- BOTÕES DE AÇÃO --}}
<div class="acoes">
    <a href="{{ route('relatorios.prazo') }}" class="btn-voltar">← Voltar</a>
    <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir</button>
</div>

{{-- CABEÇALHO --}}
<div class="header">
    <div class="header-logo">
        <img src="{{ asset('images/logo.jpg') }}" alt="Aquafast">
    </div>
    <div class="header-titulo">
        <h1>Relatório de Cumprimento de Prazo</h1>
        <p>Ordens de produção concluídas — previsto × realizado</p>
    </div>
    <div class="header-info">
        <div><strong>Gerado em:</strong> {{ now()->format('d/m/Y H:i') }}</div>
        <div><strong>Usuário:</strong> {{ auth()->user()->name ?? 'Sistema' }}</div>
    </div>
</div>

{{-- FILTROS --}}
<form method="GET" action="{{ route('relatorios.prazo.print') }}" class="filtros-form">
    <label>
        Linha
        <select name="linha_id">
            <option value="">Todas as linhas</option>
            @foreach($linhas as $l)
                <option value="{{ $l->id }}" {{ $linhaId == $l->id ? 'selected' : '' }}>
                    {{ $l->codigo }} — {{ $l->nome }}
                </option>
            @endforeach
        </select>
    </label>
    <label>
        Conclusão — de
        <input type="date" name="data_inicio" value="{{ $dataInicio }}">
    </label>
    <label>
        até
        <input type="date" name="data_fim" value="{{ $dataFim }}">
    </label>
    <button type="submit">Filtrar</button>
</form>

{{-- METADADOS --}}
<div class="meta">
    <div class="meta-item">
        <span class="meta-label">Linha</span>
        <span class="meta-value">
            @if($linhaId)
                {{ $linhas->firstWhere('id', $linhaId)?->codigo }} — {{ $linhas->firstWhere('id', $linhaId)?->nome }}
            @else
                Todas
            @endif
        </span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Período (conclusão)</span>
        <span class="meta-value">
            {{ \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') }}
            até
            {{ \Carbon\Carbon::parse($dataFim)->format('d/m/Y') }}
        </span>
    </div>
    <div class="meta-item">
        <span class="meta-label">OPs no resultado</span>
        <span class="meta-value">{{ $totais['total'] }}</span>
    </div>
</div>

{{-- CARDS DE RESUMO --}}
@if($totais['total'] > 0)
<div class="cards">
    <div class="card azul">
        <div class="card-label">OPs concluídas</div>
        <div class="card-value">{{ $totais['total'] }}</div>
        <div class="card-sub">no período filtrado</div>
    </div>
    <div class="card verde">
        <div class="card-label">No prazo</div>
        <div class="card-value">{{ $totais['no_prazo'] }}</div>
        <div class="card-sub">{{ $totais['pct_prazo'] !== null ? $totais['pct_prazo'].'%' : '—' }} do total</div>
    </div>
    <div class="card {{ $totais['atrasadas'] > 0 ? 'vermelho' : 'verde' }}">
        <div class="card-label">Atrasadas</div>
        <div class="card-value">{{ $totais['atrasadas'] }}</div>
        <div class="card-sub">
            {{ $totais['total'] > 0 ? round($totais['atrasadas'] / $totais['total'] * 100, 1) : 0 }}% do total
        </div>
    </div>
    <div class="card {{ $totais['maior_atraso'] ? 'vermelho' : 'verde' }}">
        <div class="card-label">Maior atraso</div>
        @if($totais['maior_atraso'])
            @php $ma = $totais['maior_atraso']; @endphp
            <div class="card-value">{{ formatarDesvio((int) $ma->desvio_min) }}</div>
            <div class="card-sub">OP {{ $ma->numero_op }} — {{ $ma->linha }}</div>
        @else
            <div class="card-value" style="font-size:14px;">—</div>
            <div class="card-sub">nenhum atraso</div>
        @endif
    </div>
</div>
@endif

{{-- TABELA DETALHADA --}}
<div class="tabela-wrapper">
@if($ops->count() > 0)
<table>
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
            $desvio    = (int) $op->desvio_min;
            $noPrazo   = $desvio <= 0;
            $produto   = $op->produto ?? $op->descricao_produto ?? $op->sku;
            $pctReal   = $op->quantidade > 0 && $op->quantidade_realizada !== null
                ? round($op->quantidade_realizada / $op->quantidade * 100, 1)
                : null;
        @endphp
        <tr>
            <td style="font-weight:600;">{{ $op->linha }}</td>
            <td style="font-family:monospace;">{{ $op->numero_op }}</td>
            <td>
                <div>{{ $produto }}</div>
                @if($op->sku)<div style="font-size:9px;color:#888;">{{ $op->sku }}</div>@endif
            </td>
            <td class="right">{{ number_format($op->quantidade, 0, ',', '.') }}</td>
            <td class="right">
                {{ $op->quantidade_realizada !== null ? number_format($op->quantidade_realizada, 0, ',', '.') : '—' }}
                @if($pctReal !== null)
                    <div style="font-size:9px;color:#888;">{{ $pctReal }}%</div>
                @endif
            </td>
            <td class="center">{{ \Carbon\Carbon::parse($op->fim_previsto)->format('d/m/Y H:i') }}</td>
            <td class="center">{{ \Carbon\Carbon::parse($op->fim_real)->format('d/m/Y H:i') }}</td>
            <td class="center">
                <span class="{{ $noPrazo ? 'desvio-ok' : 'desvio-late' }}">
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
<p style="padding:24px 0;text-align:center;color:#888;font-size:11px;">
    Nenhuma OP concluída encontrada para os filtros selecionados.
</p>
@endif
</div>

{{-- RESUMO POR LINHA --}}
@if($resumoPorLinha->count() > 0)
<div class="resumo-wrapper">
    <div class="resumo-titulo">Resumo por linha</div>
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
                <td><strong>Total</strong></td>
                <td class="right">{{ $totais['total'] }}</td>
                <td class="right">{{ $totais['no_prazo'] }}</td>
                <td class="right">{{ $totais['atrasadas'] }}</td>
                <td class="right">{{ $totais['pct_prazo'] !== null ? $totais['pct_prazo'].'%' : '—' }}</td>
                <td class="right">—</td>
            </tr>
        </tfoot>
    </table>
</div>
@endif

{{-- RODAPÉ --}}
<div class="rodape">
    <span>ControlePCP v2.0 — Aquafast</span>
    <span>{{ now()->format('d/m/Y H:i') }}</span>
</div>

</body>
</html>
