<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ControlePCP — TV</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:#0d1117;color:#e6edf3;font-family:'Segoe UI',Arial,sans-serif;height:100%;overflow:hidden}
body{padding:12px;display:flex;flex-direction:column;gap:10px}

/* Header */
.header{display:flex;justify-content:space-between;align-items:center}
.logo{font-size:22px;font-weight:600;letter-spacing:-0.3px}
.logo span{color:#58a6ff}
.header-right{display:flex;align-items:center;gap:20px}
.clock{font-size:28px;font-weight:500;font-variant-numeric:tabular-nums;text-align:right}
.date{font-size:14px;color:#8b949e;text-align:right}
.refresh{display:flex;align-items:center;gap:6px;font-size:13px;color:#8b949e}
.pulse{width:10px;height:10px;border-radius:50%;background:#39d353;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* KPIs */
.kpi-grid{display:grid;grid-template-columns:repeat(8,1fr);gap:8px}
.kpi{background:#161b22;border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:10px 14px}
.kpi-label{font-size:11px;color:#8b949e;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px}
.kpi-value{font-size:26px;font-weight:600;font-variant-numeric:tabular-nums;line-height:1}
.kpi-sub{font-size:12px;color:#8b949e;margin-top:4px}
.c-green{color:#39d353}.c-amber{color:#e3b341}.c-red{color:#f85149}.c-blue{color:#58a6ff}.c-muted{color:#8b949e}

/* Grid linhas */
.linhas-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;flex:1;min-height:0}
.linha-card{background:#161b22;border-radius:10px;padding:12px 14px;border-left:4px solid #334155;display:flex;flex-direction:column;gap:6px}
.linha-card.verde{border-left-color:#39d353}
.linha-card.vermelho{border-left-color:#f85149}
.linha-card.laranja{border-left-color:#f97316}
.linha-card.amarelo{border-left-color:#e3b341}
.linha-card.cinza{border-left-color:#475569}

.linha-header{display:flex;justify-content:space-between;align-items:center}
.linha-nome{font-size:32px;font-weight:600;color:#e6edf3}
.status-pill{font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600}
.pill-verde{background:rgba(57,211,83,.15);color:#39d353;border:1px solid rgba(57,211,83,.3)}
.pill-vermelho{background:rgba(248,81,73,.15);color:#f85149;border:1px solid rgba(248,81,73,.3)}
.pill-laranja{background:rgba(249,115,22,.15);color:#f97316;border:1px solid rgba(249,115,22,.3)}
.pill-amarelo{background:rgba(227,179,65,.15);color:#e3b341;border:1px solid rgba(227,179,65,.3)}
.pill-cinza{background:rgba(71,85,105,.2);color:#8b949e;border:1px solid rgba(71,85,105,.3)}

.op-info{font-size:12px;color:#8b949e}
.produto{font-size:14px;color:#e2e8f0;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cx-valor{font-size:24px;font-weight:600;color:#fff;font-variant-numeric:tabular-nums}
.cx-meta{font-size:13px;color:#8b949e}
.barra-wrap{background:rgba(255,255,255,.07);border-radius:4px;height:8px;overflow:hidden}
.barra{height:100%;border-radius:4px;transition:width .5s}
.card-footer{display:flex;justify-content:space-between;font-size:12px;color:#8b949e;margin-top:2px}
</style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="logo">Controle<span>PCP</span>
        <span style="font-size:13px;font-weight:400;color:#8b949e;margin-left:10px">Aquafast</span>
    </div>
    <div class="header-right">
        <div class="refresh">
            <div class="pulse"></div>
            <span>Atualiza em <span id="cd">60</span>s</span>
        </div>
        <div>
            <div class="clock" id="clock">{{ now()->format('H:i:s') }}</div>
            <div class="date">{{ now()->isoFormat('dddd, D [de] MMM') }}</div>
        </div>
    </div>
</div>

{{-- KPIs --}}
<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-label">Linhas ativas</div>
        <div class="kpi-value c-blue">{{ $kpis['total_linhas'] }}</div>
        <div class="kpi-sub">
            @if($kpis['em_alerta'] > 0)
                <span style="color:#f85149">{{ $kpis['em_alerta'] }} em alerta</span>
            @else
                todas operando
            @endif
        </div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Produção hoje</div>
        <div class="kpi-value c-green">{{ number_format($kpis['producao_hoje'], 0, ',', '.') }}</div>
        <div class="kpi-sub">de {{ number_format($kpis['total_prog'], 0, ',', '.') }} cx · {{ $kpis['pct_geral'] }}%</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Situação</div>
        <div class="kpi-value" style="font-size:15px;padding-top:4px;line-height:2">
            <div><span style="color:#39d353;font-size:22px;font-weight:600">{{ $kpis['em_dia'] }}</span> <span style="color:#8b949e;font-size:12px">Em dia</span></div>
            <div><span style="color:#f85149;font-size:22px;font-weight:600">{{ $kpis['atrasadas'] }}</span> <span style="color:#8b949e;font-size:12px">Atrasadas</span></div>
        </div>
    </div>
    <div class="kpi">
        <div class="kpi-label">OEE Médio</div>
        <div class="kpi-value {{ ($kpis['oee_medio'] ?? 0) >= 75 ? 'c-green' : (($kpis['oee_medio'] ?? 0) >= 60 ? 'c-amber' : 'c-red') }}">
            {{ $kpis['oee_medio'] !== null ? number_format($kpis['oee_medio'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Disponibilidade</div>
        <div class="kpi-value {{ ($kpis['disp_media'] ?? 0) >= 85 ? 'c-green' : (($kpis['disp_media'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
            {{ $kpis['disp_media'] !== null ? number_format($kpis['disp_media'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Performance</div>
        <div class="kpi-value {{ ($kpis['perf_media'] ?? 0) >= 85 ? 'c-green' : (($kpis['perf_media'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
            {{ $kpis['perf_media'] !== null ? number_format($kpis['perf_media'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Qualidade</div>
        <div class="kpi-value c-green">100,0%</div>
        <div class="kpi-sub">sem refugo</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Data</div>
        <div class="kpi-value" style="font-size:18px;color:#8b949e;margin-top:4px">{{ now()->format('d/m/Y') }}</div>
        <div class="kpi-sub">{{ now()->format('H:i') }} — sync auto</div>
    </div>
</div>

{{-- Grid de linhas --}}
<div class="linhas-grid">
    @foreach($dados as $l)
    @php
        $cor       = $l['cor'] ?? 'cinza';
        $pillClass = match($cor) {
            'verde'    => 'pill-verde',
            'vermelho' => 'pill-vermelho',
            'laranja'  => 'pill-laranja',
            'amarelo'  => 'pill-amarelo',
            default    => 'pill-cinza',
        };
        $barCor = match($cor) {
            'verde'    => '#39d353',
            'vermelho' => '#f85149',
            'laranja'  => '#f97316',
            'amarelo'  => '#e3b341',
            default    => '#475569',
        };
        $pct = min(100, $l['pct'] ?? 0);
    @endphp
    <div class="linha-card {{ $cor }}">
        <div class="linha-header">
            <div class="linha-nome">{{ $l['nome'] }}</div>
            <span class="status-pill {{ $pillClass }}">{{ $l['status'] }}</span>
        </div>
        @if($l['op'])
            <div class="op-info">OP {{ $l['op'] }} · {{ $l['sku'] }}</div>
            <div class="produto">{{ $l['produto'] ?? '—' }}</div>
            <div>
                <span class="cx-valor">{{ number_format($l['qtd_real'], 0, ',', '.') }}</span>
                <span class="cx-meta"> / {{ number_format($l['qtd_prog'], 0, ',', '.') }} cx</span>
            </div>
            <div class="barra-wrap">
                <div class="barra" style="width:{{ $pct }}%;background:{{ $barCor }}"></div>
            </div>
            <div class="card-footer">
                <span>{{ $pct }}%</span>
                @if(($l['atraso_min'] ?? null) !== null && $l['atraso_min'] > 0)
                    @php $h = intdiv($l['atraso_min'], 60); $m = $l['atraso_min'] % 60; @endphp
                    <span style="color:#f85149">+{{ $h > 0 ? $h.'h '.$m.'min' : $m.'min' }} atraso</span>
                @elseif(($l['atraso_min'] ?? null) !== null)
                    <span style="color:#39d353">no prazo</span>
                @endif
            </div>
        @else
            <div class="produto" style="color:#475569">Sem OP em andamento</div>
            <div class="cx-valor" style="color:#475569">—</div>
            <div class="barra-wrap"><div class="barra" style="width:0%"></div></div>
            <div class="card-footer">
                <span>—</span>
                @if(isset($l['inicio_prev']))
                    <span>previsto {{ $l['inicio_prev'] }}</span>
                @endif
            </div>
        @endif
    </div>
    @endforeach
</div>

<script>
function pad(n){return String(n).padStart(2,'0');}
function tick(){
    var now=new Date();
    document.getElementById('clock').textContent=pad(now.getHours())+':'+pad(now.getMinutes())+':'+pad(now.getSeconds());
}
tick();setInterval(tick,1000);
var cd=60;
setInterval(function(){cd--;if(cd<=0)cd=60;document.getElementById('cd').textContent=cd;},1000);
setInterval(function(){location.reload();},60000);
</script>
</body>
</html>
