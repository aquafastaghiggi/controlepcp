<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>ControlePCP</title>
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

        /* FILTROS (oculto no print) */
        .filtros-form {
            margin: 0 20px 12px;
            padding: 10px 16px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }
        .filtros-form label { font-size: 10px; color: #555; display: flex; flex-direction: column; gap: 3px; }
        .filtros-form select,
        .filtros-form input[type="date"] {
            padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px;
            font-size: 11px; color: #1a1a1a; background: white;
        }
        .filtros-form button {
            padding: 5px 16px;
            background: #1e3a8a; color: white;
            border: none; border-radius: 4px; font-size: 11px;
            cursor: pointer; font-weight: 600;
        }
        .filtros-form button:hover { background: #1e40af; }

        /* METADADOS */
        .meta {
            display: flex; gap: 24px;
            padding: 5px 20px;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
            margin: 0 20px 10px;
            font-size: 11px;
        }
        .meta-item { display: flex; flex-direction: column; }
        .meta-label { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
        .meta-value { font-weight: bold; color: #1a1a1a; margin-top: 1px; }

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

        /* BARRA DE PROGRESSO */
        .barra-outer { background: #e5e7eb; border-radius: 3px; height: 6px; min-width: 60px; }
        .barra-inner { height: 6px; border-radius: 3px; }

        /* TOTAIS */
        .totais {
            margin: 8px 20px 0;
            padding: 7px 16px;
            background: #1e3a8a; color: white; border-radius: 6px;
            display: flex; justify-content: space-between; font-size: 11px;
        }
        .totais-item { display: flex; flex-direction: column; align-items: center; }
        .totais-label { font-size: 9px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.3px; }
        .totais-value { font-weight: bold; font-size: 12px; margin-top: 2px; }

        /* TÍTULO DE SEÇÃO */
        .secao-titulo {
            display: flex; align-items: center; gap: 0;
            padding: 0 20px; margin-bottom: 8px;
            font-size: 12px; color: #1a1a1a;
        }
        .secao-titulo .subtitulo { font-size: 10px; color: #888; margin-left: 6px; font-weight: normal; }

        /* RODAPÉ */
        .rodape {
            margin-top: 16px; padding: 8px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex; justify-content: space-between;
            font-size: 9px; color: #aaa;
        }

        /* BOTÕES DE AÇÃO (somem no print) */
        .acoes {
            position: fixed; top: 16px; right: 16px;
            display: flex; gap: 8px; z-index: 999;
        }
        .btn-imprimir {
            padding: 10px 20px;
            background: #1e3a8a; color: white;
            border: none; cursor: pointer; border-radius: 8px;
            font-size: 13px; font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .btn-imprimir:hover { background: #1e40af; }
        .btn-voltar {
            padding: 10px 16px;
            background: white; color: #555;
            border: 1px solid #d1d5db; cursor: pointer; border-radius: 8px;
            font-size: 13px; font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-decoration: none; display: flex; align-items: center;
        }
        .btn-voltar:hover { background: #f9fafb; }

        @media print {
            .acoes { display: none; }
            .filtros-form { display: none; }
            body { margin: 0; }
            @page { margin: 0.5cm 12mm; size: A4 landscape; }
        }
    </style>
</head>
<body>

{{-- BOTÕES DE AÇÃO --}}
<div class="acoes">
    <a href="{{ route('relatorios.desempenho') }}" class="btn-voltar">← Voltar</a>
    <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir</button>
</div>

{{-- CABEÇALHO --}}
<div class="header">
    <div class="header-logo">
        <img src="{{ asset('images/logo.jpg') }}" alt="Aquafast">
    </div>
    <div class="header-titulo">
        <h1>Relatório de Desempenho</h1>
        <p>Controle e Sequenciamento PCP</p>
    </div>
    <div class="header-info">
        <div><strong>Gerado em:</strong> {{ $gerado }}</div>
        <div><strong>Usuário:</strong> {{ auth()->user()->name ?? 'Sistema' }}</div>
    </div>
</div>

{{-- FORMULÁRIO DE FILTROS (oculto no print) --}}
<form method="GET" action="{{ route('relatorios.desempenho.print') }}" class="filtros-form">
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
        Data início
        <input type="date" name="data_inicio" value="{{ $dataInicio }}">
    </label>
    <label>
        Data fim
        <input type="date" name="data_fim" value="{{ $dataFim }}">
    </label>
    <button type="submit">Filtrar</button>
</form>

{{-- FAIXA DE METADADOS (filtros aplicados — aparece no print) --}}
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
        <span class="meta-label">Período</span>
        <span class="meta-value">
            {{ \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') }}
            até
            {{ \Carbon\Carbon::parse($dataFim)->format('d/m/Y') }}
        </span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Linhas no resultado</span>
        <span class="meta-value">{{ count($resultado) }}</span>
    </div>
</div>

{{-- TABELA --}}
<div class="tabela-wrapper">
@if(count($resultado) > 0)
<table>
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
            $pct        = $row['pct'];
            $corBarra   = $pct === null ? '#d1d5db'
                        : ($pct >= 85 ? '#16a34a' : ($pct >= 60 ? '#f59e0b' : '#ef4444'));
            $largBarra  = $pct !== null ? min(100, $pct) : 0;
            $atraso     = $row['atraso_medio'];
            $corAtraso  = $atraso === null ? '#aaa'
                        : ($atraso <= 0 ? '#16a34a' : ($atraso <= 30 ? '#f59e0b' : '#ef4444'));
            $oee        = $row['oee_medio'];
            $corOee     = $oee === null ? '#aaa'
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
                        <div class="barra-outer" style="width:50px;">
                            <div class="barra-inner" style="width:{{ $largBarra }}%; background:{{ $corBarra }};"></div>
                        </div>
                        <span style="color:{{ $corBarra }}; font-weight:600;">{{ $pct }}%</span>
                    </div>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="center">
                @if($atraso !== null)
                    <span style="color:{{ $corAtraso }}; font-weight:600;">
                        {{ $atraso > 0 ? '+' : '' }}{{ $atraso }} min
                    </span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="center">
                @if($oee !== null)
                    <span style="color:{{ $corOee }}; font-weight:600;">{{ $oee }}%</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background:#1e3a8a;color:white;">
            <td><strong>Total</strong></td>
            <td class="center">{{ collect($resultado)->sum('total_ops') }}</td>
            <td class="right">{{ number_format(collect($resultado)->sum('previsto'), 0, ',', '.') }}</td>
            <td class="right">{{ number_format(collect($resultado)->sum('realizado'), 0, ',', '.') }}</td>
            <td class="center" colspan="3">—</td>
        </tr>
    </tfoot>
</table>
@else
<p style="padding: 24px 0; text-align:center; color:#888; font-size:11px;">
    Nenhum dado encontrado para os filtros selecionados.
</p>
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
<div class="totais">
    <div class="totais-item">
        <span class="totais-label">Cx previstas total</span>
        <span class="totais-value">{{ number_format($totalPrevisto, 0, ',', '.') }}</span>
    </div>
    <div class="totais-item">
        <span class="totais-label">Cx realizadas total</span>
        <span class="totais-value">{{ number_format($totalRealizado, 0, ',', '.') }}</span>
    </div>
    <div class="totais-item">
        <span class="totais-label">% geral</span>
        <span class="totais-value">{{ $pctGeral !== null ? $pctGeral . '%' : '—' }}</span>
    </div>
    <div class="totais-item">
        <span class="totais-label">OEE médio geral</span>
        <span class="totais-value">{{ $oeeMedioGeral !== null ? $oeeMedioGeral . '%' : '—' }}</span>
    </div>
</div>
@endif

{{-- RODAPÉ --}}
<div class="rodape">
    <span>ControlePCP v2.0 — Aquafast</span>
    <span>{{ now()->format('d/m/Y H:i') }}</span>
</div>

</body>
</html>
