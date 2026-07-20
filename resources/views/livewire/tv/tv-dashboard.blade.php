<div wire:poll.60s="carregarDados" wire:poll.10s="verificarParadas" style="padding:12px 16px;display:flex;flex-direction:column;gap:12px;height:100vh;width:100vw;max-width:100vw;overflow:hidden;background:#0d1117;color:#e6edf3;font-family:'Segoe UI',Arial,sans-serif;">

<style>
*{box-sizing:border-box}
.header{display:flex;justify-content:space-between;align-items:center}
.logo{font-size:22px;font-weight:600;letter-spacing:-0.3px;color:#e6edf3}
.logo span{color:#58a6ff}
.header-right{display:flex;align-items:center;gap:20px}
.clock{font-size:28px;font-weight:500;font-variant-numeric:tabular-nums;text-align:right;color:#e6edf3}
.date{font-size:14px;color:#8b949e;text-align:right}
.refresh{display:flex;align-items:center;gap:6px;font-size:13px;color:#8b949e}
.pulse{width:10px;height:10px;border-radius:50%;background:#39d353;animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.kpi-grid{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:20px;width:100%}
.kpi{background:#252d3d;border-radius:12px;padding:10px 14px;border-top:1px solid rgba(255,255,255,0.15);border-right:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08);border-left:1px solid rgba(255,255,255,0.08);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.1)}
.kpi-label{font-size:16px;color:#8b949e;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px}
.kpi-value{font-size:39px;font-weight:600;line-height:1}
.kpi-sub{font-size:18px;color:#8b949e;margin-top:4px}
.kpi-oee{background:#1e2a1e;border-top:1px solid rgba(255,255,255,0.15);border-right:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08);border-left:1px solid rgba(255,255,255,0.08);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.1)}
.kpi-oee-mini{background:#1a2518;border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:5px 8px;text-align:center;flex:1;box-shadow:0 2px 8px rgba(0,0,0,0.4)}
.kpi-oee-mini .kpi-label-mini{color:#8b949e;font-size:13px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;}
.kpi-oee-mini .kpi-val-mini{font-size:24px;font-weight:600}

.linhas-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px;flex:1;min-height:0;overflow:hidden}
.linha-card {
    position:relative;width:100%;padding:20px 20px 16px 32px;border-radius:22px;
    overflow:hidden;
    background:radial-gradient(circle at 72% 38%,rgba(92,255,93,.18),transparent 28%),
        linear-gradient(145deg,#182131 0%,#101827 48%,#0c1420 100%);
    border:1px solid rgba(148,163,184,.18);
    box-shadow:0 24px 60px rgba(0,0,0,.42),inset 0 0 0 1px rgba(255,255,255,.04);
    color:#f8fafc;font-family:Inter,"Segoe UI",Arial,sans-serif;
}
.linha-card.green{border-left:4px solid #39d353;border-top:1px solid rgba(57,211,83,0.25);border-right:1px solid rgba(57,211,83,0.12);border-bottom:1px solid rgba(57,211,83,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(57,211,83,0.12),inset 0 1px 0 rgba(255,255,255,0.1)}
.linha-card.red{border-left:4px solid #f85149;border-top:1px solid rgba(248,81,73,0.25);border-right:1px solid rgba(248,81,73,0.12);border-bottom:1px solid rgba(248,81,73,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(248,81,73,0.15),inset 0 1px 0 rgba(255,255,255,0.1)}
.linha-card.orange{border-left:4px solid #f97316;border-top:1px solid rgba(249,115,22,0.25);border-right:1px solid rgba(249,115,22,0.12);border-bottom:1px solid rgba(249,115,22,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(249,115,22,0.15),inset 0 1px 0 rgba(255,255,255,0.1)}
.linha-card.yellow{border-left:4px solid #e3b341;border-top:1px solid rgba(227,179,65,0.25);border-right:1px solid rgba(227,179,65,0.12);border-bottom:1px solid rgba(227,179,65,0.12);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),0 0 0 1px rgba(227,179,65,0.15),inset 0 1px 0 rgba(255,255,255,0.1)}
.linha-card.gray{border-left:4px solid #475569;border-top:1px solid rgba(255,255,255,0.13);border-right:1px solid rgba(255,255,255,0.07);border-bottom:1px solid rgba(255,255,255,0.07);box-shadow:0 8px 32px rgba(0,0,0,0.7),0 2px 8px rgba(0,0,0,0.5),inset 0 1px 0 rgba(255,255,255,0.08)}

.linha-nome {
    font-size:clamp(36px,5vw,58px);line-height:1;letter-spacing:1px;font-weight:800;
    color:#f8fafc;text-transform:uppercase;text-shadow:0 6px 22px rgba(0,0,0,.38);
    white-space:nowrap;
}
.linha-header{display:flex;justify-content:space-between;align-items:center}
.status-pill {
    position:absolute;top:16px;right:16px;z-index:10;display:inline-flex;align-items:center;
    gap:8px;padding:8px 16px;border-radius:999px;background:rgba(42,86,48,.55);
    border:1px solid rgba(111,255,94,.32);color:#8dff73;font-size:16px;line-height:1;
    font-weight:800;
}
.pill-green{background:rgba(57,211,83,.15);color:#39d353;border:1px solid rgba(57,211,83,.3)}
.pill-red{background:rgba(248,81,73,.15);color:#f85149;border:1px solid rgba(248,81,73,.3)}
.pill-orange{background:rgba(249,115,22,.15);color:#f97316;border:1px solid rgba(249,115,22,.3)}
.pill-yellow{background:rgba(227,179,65,.15);color:#e3b341;border:1px solid rgba(227,179,65,.3)}
.pill-gray{background:rgba(71,85,105,.2);color:#8b949e;border:1px solid rgba(71,85,105,.3)}

.op-info{font-size:18px;color:#8b949e}
.produto {
    position:relative;z-index:2;margin-top:8px;font-size:18px;line-height:1.25;
    font-weight:700;color:#ffffff;letter-spacing:-0.3px;
    text-shadow:0 4px 18px rgba(0,0,0,.45);
    display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:visible;
}
.cx-valor {
    font-size:clamp(22px,2.85vw,36px);line-height:1;font-weight:850;color:#ffffff;
    letter-spacing:-2px;text-shadow:0 8px 28px rgba(0,0,0,.45);display:inline;
}
.cx-meta {
    font-size:clamp(12px,1.2vw,15px);line-height:1;font-weight:500;color:#a9b3c5;display:inline;margin-left:5px;
}
.cx-meta span { color:#6ee75f;padding:0 4px; }
.barra-wrap {
    position:relative;width:100%;height:10px;margin-top:10px;border-radius:999px;
    overflow:hidden;background:rgba(148,163,184,.18);
}
.barra{height:100%;border-radius:4px}
.card-footer{display:flex;justify-content:space-between;font-size:13px;color:#8b949e}

.c-green{color:#39d353}.c-amber{color:#e3b341}.c-red{color:#f85149}.c-blue{color:#58a6ff}.c-muted{color:#8b949e}

@keyframes border-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(249,115,22,0.4); }
    50%       { box-shadow: 0 0 0 6px rgba(249,115,22,0); }
}
@keyframes border-pulse-red {
    0%, 100% { box-shadow: 0 0 0 0 rgba(248,81,73,0.4); }
    50%       { box-shadow: 0 0 0 6px rgba(248,81,73,0); }
}
.linha-card.pulsing-orange { animation: border-pulse 2s ease-in-out infinite; }
.linha-card.pulsing-red    { animation: border-pulse-red 2s ease-in-out infinite; }

.linha-card::before {
    content:"";position:absolute;left:18px;top:34px;width:5px;height:290px;border-radius:999px;
    background:linear-gradient(180deg,#9cff6d,#47e05f);box-shadow:0 0 18px rgba(103,255,103,.45);
}
.linha-card::after {
    content:"";position:absolute;right:65px;top:130px;width:360px;height:260px;opacity:.24;
    background-image:radial-gradient(rgba(109,255,94,.55) 1px,transparent 1px);
    background-size:14px 14px;pointer-events:none;
}
.linha-topo {
    position:relative;z-index:2;display:grid;grid-template-columns:1fr 160px;gap:10px;align-items:start;min-height:180px;
}
.produto-img-wrap {
    position:relative;height:170px;width:160px;display:flex;align-items:flex-end;
    justify-content:center;overflow:visible;padding-top:0;
}
.produto-img-wrap::after {
    content:"";position:absolute;bottom:0;left:50%;transform:translateX(-50%);
    width:120px;height:24px;border-radius:50%;
    background:rgba(0,0,0,.65);border-bottom:3px solid #55ef6f;
    box-shadow:0 0 16px rgba(85,239,111,.55);z-index:1;
}
.produto-img-wrap::before {
    content:"";position:absolute;left:50%;bottom:28px;transform:translateX(-50%);
    width:150px;height:150px;border-radius:50%;
    background:radial-gradient(
        circle,
        rgba(95,255,120,.30) 0%,
        rgba(95,255,120,.18) 35%,
        rgba(95,255,120,.08) 55%,
        transparent 78%
    );
    filter:blur(8px);z-index:0;pointer-events:none;
}
.produto-img {
    position:relative;z-index:2;max-height:150px;max-width:125px;width:auto;height:auto;
    object-fit:contain;object-position:bottom center;
    filter:drop-shadow(0 12px 12px rgba(0,0,0,.35));
}
.produto-logo-placeholder {
    position:relative;z-index:2;max-width:90px;max-height:50px;object-fit:contain;
    opacity:.18;filter:brightness(0) invert(1);margin-bottom:24px;
}
.producao-area {
    position:relative;z-index:3;margin-top:4px;
}
.cx-label { display:none; }
.total-dia {
    padding:11px 10px;border-radius:12px;background:rgba(15,23,35,.72);
    border:1px solid rgba(109,255,93,.26);text-align:center;
}
.total-label { font-size:12px;font-weight:800;color:#8fe874;letter-spacing:1px; }
.total-valor { margin-top:7px;font-size:36px;line-height:1;font-weight:850;color:#fff;letter-spacing:-1px; }
.total-meta { margin-top:4px;font-size:12px;color:#a8b3c7; }
.indicadores {
    position:relative;z-index:3;display:grid;grid-template-columns:repeat(3,1fr);
    gap:8px;margin-top:12px;
}
.indicador {
    padding:10px 12px;border-radius:14px;background:rgba(31,42,58,.84);
    border:1px solid rgba(148,163,184,.12);
}
.indicador-label { font-size:11px;line-height:1;font-weight:700;color:#a8b3c7;letter-spacing:1px; }
.indicador-valor { margin-top:6px;font-size:22px;line-height:1;font-weight:850; }
.indicador-valor.verde { color:#39d353; }
.indicador-valor.amarelo { color:#ffc83d; }
.indicador-valor.vermelho { color:#ff514f; }
.indicador-valor.neutro { color:#8b949e; }
.oee-card { background:rgba(16,31,19,.72);border-color:rgba(109,255,93,.18); }
</style>

{{-- Header --}}
<div class="header">
    <img src="{{ asset('images/aquafast-logo.svg') }}"
         alt="Aquafast"
         style="height:60px;width:auto;filter:brightness(0) invert(1);">
    <div class="header-right">
        <div class="refresh">
            <div class="pulse"></div>
            <span>Atualiza a cada 60s</span>
        </div>
        <div>
            <div class="clock" id="tv-clock">{{ now()->format('H:i:s') }}</div>
            <div class="date">{{ now()->isoFormat('dddd, D [de] MMM [de] Y') }}</div>
        </div>
    </div>
</div>

{{-- KPIs --}}
<div class="kpi-grid">
    {{-- 1. Linhas ativas --}}
    <div class="kpi">
        <div class="kpi-label">Linhas ativas</div>
        <div class="kpi-value c-blue">{{ $kpis['total_linhas'] ?? '—' }}</div>
        <div class="kpi-sub">
            @if(($kpis['em_alerta'] ?? 0) > 0)
                <span class="c-red">{{ $kpis['em_alerta'] }} em alerta</span>
            @else
                todas operando
            @endif
        </div>
    </div>
    {{-- 2. Situação --}}
    <div class="kpi">
        <div class="kpi-label">Situação</div>
        <div style="display:flex;gap:16px;margin-top:4px">
            <div style="display:flex;flex-direction:column;align-items:flex-start">
                <span class="kpi-value c-green">{{ $kpis['situacao_geral']['em_dia'] ?? 0 }}</span>
                <span class="kpi-sub">Em dia</span>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-start">
                <span class="kpi-value c-red">{{ $kpis['situacao_geral']['atrasadas'] ?? 0 }}</span>
                <span class="kpi-sub">Atrasadas</span>
            </div>
        </div>
    </div>
    {{-- 3. Produção do dia --}}
    <div class="kpi">
        <div class="kpi-label">Produção do dia</div>
        <div class="kpi-value c-green">{{ number_format($kpis['produzido_hoje'] ?? 0, 0, ',', '.') }}</div>
        <div class="kpi-sub">de {{ number_format($kpis['previsto_hoje'] ?? 0, 0, ',', '.') }} cx · {{ $kpis['pct_hoje'] ?? 0 }}%</div>
    </div>
    {{-- 4. Previsão de hoje --}}
    <div class="kpi">
        <div class="kpi-label">Previsão de hoje</div>
        <div class="kpi-value {{ ($kpis['pct_proj'] ?? 0) >= 90 ? 'c-green' : (($kpis['pct_proj'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
            {{ number_format($kpis['projecao'] ?? 0, 0, ',', '.') }}
        </div>
        <div class="kpi-sub">
            {{ $kpis['pct_proj'] ?? 0 }}% de {{ number_format($kpis['previsto_hoje'] ?? 0, 0, ',', '.') }} cx
        </div>
    </div>
    {{-- 5. Diferença --}}
    <div class="kpi">
        <div class="kpi-label">Diferença Tot. Prev.</div>
        @php $dif = $kpis['diferenca'] ?? 0; @endphp
        <div class="kpi-value {{ $dif >= 0 ? 'c-green' : 'c-red' }}">
            {{ $dif >= 0 ? '+' : '' }}{{ number_format($dif, 0, ',', '.') }}
        </div>
        <div class="kpi-sub">Caixas hoje</div>
    </div>
    {{-- 6. Disponibilidade --}}
    <div class="kpi">
        <div class="kpi-label">Disponibilidade</div>
        <div class="kpi-value {{ ($kpis['disp_media'] ?? 0) >= 85 ? 'c-green' : (($kpis['disp_media'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
            {{ isset($kpis['disp_media']) ? number_format($kpis['disp_media'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
    {{-- 7. Performance --}}
    <div class="kpi">
        <div class="kpi-label">Performance</div>
        <div class="kpi-value {{ ($kpis['perf_media'] ?? 0) >= 85 ? 'c-green' : (($kpis['perf_media'] ?? 0) >= 70 ? 'c-amber' : 'c-red') }}">
            {{ isset($kpis['perf_media']) ? number_format($kpis['perf_media'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
    {{-- 8. OEE Médio --}}
    <div class="kpi kpi-oee">
        <div class="kpi-label">OEE Médio</div>
        <div class="kpi-value {{ ($kpis['oee_medio'] ?? 0) >= 75 ? 'c-green' : (($kpis['oee_medio'] ?? 0) >= 60 ? 'c-amber' : 'c-red') }}" style="font-size:46px;">
            {{ isset($kpis['oee_medio']) ? number_format($kpis['oee_medio'], 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="kpi-sub">média do dia</div>
    </div>
</div>

{{-- Grid de linhas --}}
<div class="linhas-grid">
    @forelse($linhas as $linha)
    @php
        $cor       = $linha['cor'] ?? 'gray';
        $pillClass = match($cor) {
            'green'  => 'pill-green',
            'red'    => 'pill-red',
            'orange' => 'pill-orange',
            'yellow' => 'pill-yellow',
            default  => 'pill-gray',
        };
        $barCor = match($cor) {
            'green'  => '#39d353',
            'red'    => '#f85149',
            'orange' => '#f97316',
            'yellow' => '#e3b341',
            default  => '#475569',
        };
        $op  = $linha['op_atual'] ?? null;
        $pct = min(100, $op['pct'] ?? 0);

        $temParada = str_contains($linha['estado'] ?? '', 'Parada')
                  || !empty($linha['parada_aberta'])
                  || in_array($linha['codigo'], $linhasComParada);

        $pulsingClass = '';
        if ($temParada) {
            $pulsingClass = $linha['cor'] === 'orange' ? 'pulsing-orange' : 'pulsing-red';
        }
    @endphp
    <div class="linha-card {{ $cor }} {{ $pulsingClass }}">
        <span class="status-pill {{ $pillClass }}">
            <span></span>{{ $linha['estado'] }}
        </span>

        @php
            $fotoProduto = \Illuminate\Support\Facades\DB::table('produtos')
                ->where('sku', $op['sku'] ?? '')
                ->value('foto');
            $oeeReal  = $linha['oee_tempo_real']['oee']            ?? null;
            $dispReal = $linha['oee_tempo_real']['disponibilidade'] ?? null;
            $perfReal = $linha['oee_tempo_real']['performance']     ?? null;
            $oeeColor  = is_null($oeeReal)  ? 'neutro' : ($oeeReal  >= 75 ? 'verde' : ($oeeReal  >= 60 ? 'amarelo' : 'vermelho'));
            $dispColor = is_null($dispReal) ? 'neutro' : ($dispReal >= 85 ? 'verde' : ($dispReal >= 70 ? 'amarelo' : 'vermelho'));
            $perfColor = is_null($perfReal) ? 'neutro' : ($perfReal >= 85 ? 'verde' : ($perfReal >= 70 ? 'amarelo' : 'vermelho'));
        @endphp

        <div class="linha-topo">
            <div>
                <div class="linha-nome">{{ $linha['nome'] }}</div>
                @if($op)
                    <div class="op-info">OP {{ $op['numero_op'] }}</div>
                    <div class="produto">{{ $op['descricao'] }}</div>
                    @if($linha['cor'] === 'red' && ($op['atraso_inicio_min'] ?? 0) > 0)
                    @php
                        $aMin=$op['atraso_inicio_min'];
                        $aHhmm=str_pad(intdiv($aMin,60),2,'0',STR_PAD_LEFT).':'.str_pad($aMin%60,2,'0',STR_PAD_LEFT);
                    @endphp
                    <div style="font-size:16px;font-weight:700;color:#f85149;margin-top:6px;">{{ $aHhmm }} de atraso</div>
                    @endif
                @else
                    <div class="op-info" style="margin-top:16px;">Aguardando início</div>
                @endif
            </div>
            <div class="produto-img-wrap">
                @if($fotoProduto)
                    <img src="{{ asset('fotos-produtos/' . $fotoProduto) }}"
                         class="produto-img" alt="">
                @else
                    <img src="{{ asset('images/aquafast-logo.svg') }}"
                         class="produto-logo-placeholder" alt="">
                @endif
            </div>
        </div>

        @if($op)
        <div class="producao-area">
            <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;">
                <div>
                    <div class="cx-valor">{{ number_format($op['realizado'], 0, ',', '.') }}</div>
                    <div class="cx-meta">
                        @if(($op['programado'] ?? 0) > 0)
                            / {{ number_format($op['programado'], 0, ',', '.') }} cx <span>•</span> {{ $pct }}%
                        @else
                            cx
                        @endif
                    </div>
                </div>
                <div class="total-dia" style="flex-shrink:0;">
                    <div class="total-label">TOTAL DIA</div>
                    <div class="total-valor">{{ number_format($linha['total_hoje'] ?? 0, 0, ',', '.') }}</div>
                    <div class="total-meta">cx produzidas</div>
                </div>
            </div>
            <div class="barra-wrap">
                <div class="barra" style="width:{{ $pct }}%;background:{{ $barCor }}"></div>
            </div>
        </div>
        @endif

        <div class="indicadores">
            <div class="indicador">
                <div class="indicador-label">DISPONIBILIDADE</div>
                <div class="indicador-valor {{ $dispColor }}">
                    {{ !is_null($dispReal) ? number_format($dispReal,1,',','.').'%' : '—' }}
                </div>
            </div>
            <div class="indicador">
                <div class="indicador-label">PERFORMANCE</div>
                <div class="indicador-valor {{ $perfColor }}">
                    {{ !is_null($perfReal) ? number_format($perfReal,1,',','.').'%' : '—' }}
                </div>
            </div>
            <div class="indicador oee-card">
                <div class="indicador-label">OEE</div>
                <div class="indicador-valor {{ $oeeColor }}">
                    {{ !is_null($oeeReal) ? number_format($oeeReal,1,',','.').'%' : '—' }}
                </div>
            </div>
        </div>
    </div>
    @empty
        <div style="color:#8b949e;grid-column:1/-1;text-align:center;padding:40px">Nenhuma linha ativa</div>
    @endforelse
</div>

<script>
function pad(n){return String(n).padStart(2,'0');}
function tick(){
    var now=new Date();
    var el=document.getElementById('tv-clock');
    if(el) el.textContent=pad(now.getHours())+':'+pad(now.getMinutes())+':'+pad(now.getSeconds());
}
tick();setInterval(tick,1000);
</script>
</div>
