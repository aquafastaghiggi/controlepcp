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
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px 8px;
            border-bottom: 3px solid #1e3a8a;
            margin-bottom: 10px;
        }
        .header-logo img {
            height: 36px;
            width: auto;
        }
        .header-titulo {
            text-align: center;
            flex: 1;
            padding: 0 20px;
        }
        .header-titulo h1 {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 0.5px;
        }
        .header-titulo p {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }
        .header-info {
            text-align: right;
            font-size: 10px;
            color: #555;
            line-height: 1.6;
        }
        .header-info strong {
            color: #1a1a1a;
        }

        /* METADADOS */
        .meta {
            display: flex;
            gap: 24px;
            padding: 5px 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin: 0 20px 10px;
            font-size: 11px;
        }
        .meta-item { display: flex; flex-direction: column; }
        .meta-label { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
        .meta-value { font-weight: bold; color: #1a1a1a; margin-top: 1px; }

        /* TABELA */
        .tabela-wrapper { padding: 0 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 10.5px; }

        thead tr {
            background: #1e3a8a;
            color: white;
        }
        thead th {
            padding: 5px 6px;
            text-align: left;
            font-size: 9.5px;
            font-weight: 600;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        thead th.right { text-align: right; }
        thead th.center { text-align: center; }

        tbody tr.producao td { background: white; }
        tbody tr.producao:nth-child(even) td { background: #f9fafb; }
        tbody tr.setup td {
            background: #fef9f0;
            color: #92400e;
            font-size: 10px;
        }

        td {
            padding: 3px 6px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        td.right { text-align: right; }
        td.center { text-align: center; }
        td.mono { font-family: monospace; font-size: 10px; color: #444; }

        /* TOTAIS */
        .totais {
            margin: 8px 20px 0;
            padding: 7px 16px;
            background: #1e3a8a;
            color: white;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }
        .totais-item { display: flex; flex-direction: column; align-items: center; }
        .totais-label { font-size: 9px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.3px; }
        .totais-value { font-weight: bold; font-size: 12px; margin-top: 2px; }

        /* RODAPÉ */
        .rodape {
            margin-top: 16px;
            padding: 8px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #aaa;
        }

        /* BOTÃO IMPRIMIR */
        .btn-imprimir {
            position: fixed;
            top: 16px;
            right: 16px;
            padding: 10px 20px;
            background: #1e3a8a;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 999;
        }
        .btn-imprimir:hover { background: #1e40af; }

        @media print {
            .btn-imprimir { display: none; }
            body { margin: 0; }
            @page { margin: 0.5cm 12mm; size: A4 landscape; }
        }
    </style>
</head>
<body>

<button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir</button>

{{-- CABEÇALHO --}}
<div class="header">
    <div class="header-logo">
        <img src="{{ asset('images/logo.jpg') }}" alt="Aquafast">
    </div>
    <div class="header-titulo">
        <p>Controle e Sequenciamento PCP</p>
    </div>
    <div class="header-info">
        <div><strong>Emitido em:</strong> {{ now()->locale('pt_BR')->isoFormat('DD/MM/YYYY [às] HH:mm') }}</div>
        <div><strong>Usuário:</strong> {{ auth()->user()->name ?? 'Sistema' }}</div>
        <div><strong>Doc:</strong> {{ $programacao->numero_op ?? '#'.$programacao->id }}</div>
    </div>
</div>

{{-- METADADOS --}}
<div class="meta">
    <div class="meta-item">
        <span class="meta-label">Linha</span>
        <span class="meta-value">{{ $programacao->linha->nome ?? '—' }}</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Início base</span>
        <span class="meta-value">{{ \Carbon\Carbon::parse($programacao->data_inicio_planejada)->format('d/m/Y H:i') }}</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Eficiência</span>
        <span class="meta-value">{{ number_format($programacao->eficiencia, 2) }}%</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Total de OPs</span>
        <span class="meta-value">{{ $programacao->itens->count() }}</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Status</span>
        <span class="meta-value">{{ ucfirst($programacao->status) }}</span>
    </div>
</div>

{{-- TABELA --}}
<div class="tabela-wrapper">
<table>
    <thead>
        <tr>
            <th>OP</th>
            <th class="center">Seq</th>
            <th>SKU</th>
            <th>Descrição</th>
            <th class="right">Quantidade</th>
            <th class="center">Duração</th>
            <th class="center">Data</th>
            <th>Início</th>
            <th>Fim</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalSetupMin    = 0;
            $totalProducaoMin = 0;
            $fimPrevisto      = null;
            $fmtDur = fn($m) => intdiv($m, 60) . ':' . str_pad($m % 60, 2, '0', STR_PAD_LEFT);
        @endphp

        @foreach($programacao->resultados->sortBy('inicio') as $resultado)
        @php
            $durMin  = (int) $resultado->duracao_minutos;
            $inicio  = \Carbon\Carbon::parse($resultado->inicio);
            $fim     = \Carbon\Carbon::parse($resultado->fim);
            $fimPrevisto = $fim;
            $item    = $programacao->itens->firstWhere('sku', $resultado->sku);

            if ($resultado->tipo === 'setup') $totalSetupMin += $durMin;
            else $totalProducaoMin += $durMin;
        @endphp

        @if($resultado->tipo === 'producao')
        <tr class="producao">
            <td class="mono">{{ $item?->numero_op ?? '—' }}</td>
            <td class="center">{{ $item?->sequencia ?? '—' }}</td>
            <td class="mono">{{ $resultado->sku }}</td>
            <td>{{ $item?->descricao_produto ?? $resultado->sku }}</td>
            <td class="right">{{ number_format((float)($item?->quantidade ?? 0), 0, ',', '.') }}</td>
            <td class="center">{{ $fmtDur($durMin) }}</td>
            <td class="center">{{ $inicio->format('d/m') }}</td>
            <td>{{ $inicio->locale('pt_BR')->isoFormat('ddd HH:mm') }}</td>
            <td>{{ $fim->locale('pt_BR')->isoFormat('ddd HH:mm') }}</td>
        </tr>
        @else
        <tr class="setup">
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align:right; font-style:italic; font-size:10px; color:#92400e;">Setup</td>
            <td class="right">{{ $fmtDur($durMin) }}</td>
            <td class="center"></td>
            <td class="center">{{ $inicio->format('d/m') }}</td>
            <td>{{ $inicio->locale('pt_BR')->isoFormat('ddd HH:mm') }}</td>
            <td>{{ $fim->locale('pt_BR')->isoFormat('ddd HH:mm') }}</td>
        </tr>
        @endif
        @endforeach
    </tbody>
</table>
</div>

{{-- TOTAIS --}}
@php
    $fmtMin = fn($m) => intdiv($m, 60) . 'h ' . str_pad($m % 60, 2, '0', STR_PAD_LEFT) . 'min';
@endphp
<div class="totais">
    <div class="totais-item">
        <span class="totais-label">Setup total</span>
        <span class="totais-value">{{ $fmtMin($totalSetupMin) }}</span>
    </div>
    <div class="totais-item">
        <span class="totais-label">Produção total</span>
        <span class="totais-value">{{ $fmtMin($totalProducaoMin) }}</span>
    </div>
    <div class="totais-item">
        <span class="totais-label">Fim previsto</span>
        <span class="totais-value">{{ $fimPrevisto?->locale('pt_BR')->isoFormat('ddd DD/MM/YYYY HH:mm') ?? '—' }}</span>
    </div>
</div>

{{-- RODAPÉ --}}
<div class="rodape">
    <span>ControlePCP v2.0 — Aquafast</span>
    <span>{{ now()->format('d/m/Y H:i') }}</span>
</div>

</body>
</html>
