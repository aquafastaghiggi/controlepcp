<div wire:poll.30s="refresh">

    {{-- ── Banner de divergências ── --}}
    @php $totalDivergencias = \Illuminate\Support\Facades\DB::table('divergencias_op')->whereNull('resolvida_em')->count(); @endphp
    @if($totalDivergencias > 0)
    <div x-data="{ aberto: true }" x-show="aberto" x-transition
         class="flex items-center gap-3 px-4 py-3 mb-5 rounded-lg border"
         style="background: var(--bg-danger, #FCEBEB); border-color: var(--border-danger, #F09595);">
        <svg class="w-5 h-5 shrink-0" style="color: #A32D2D;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <div class="flex-1">
            <span class="font-medium text-sm" style="color: #A32D2D;">
                {{ $totalDivergencias === 1 ? 'Divergência detectada' : $totalDivergencias . ' divergências ativas' }}
            </span>
            <span class="text-sm ml-2" style="color: #A32D2D;">
                {{ $totalDivergencias === 1 ? 'Uma OP está rodando no CODI fora da programação confirmada.' : 'OPs rodando no CODI fora da programação confirmada.' }}
            </span>
        </div>
        <a href="{{ route('divergencias') }}"
           class="text-xs underline whitespace-nowrap" style="color: #A32D2D;">
            Ver divergências
        </a>
        <button @click="aberto = false" aria-label="Fechar"
                class="ml-1 p-0.5 rounded hover:bg-red-100 transition-colors" style="color: #A32D2D;">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    @endif

    {{-- ── Barra de sincronização ── --}}
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Acompanhar Produção</h2>

        <div class="flex items-center gap-4">
            <span class="text-xs text-gray-400">
                Último sync: <span class="font-medium text-gray-600">{{ $ultimoSync }}</span>
            </span>

            <div
                x-data="{
                    ts: '{{ $syncTimestamp }}',
                    elapsed: '',
                    init() {
                        if (!this.ts) {
                            this.elapsed = '';
                            return;
                        }
                        const update = () => {
                            const diff = Math.floor((Date.now() - new Date(this.ts)) / 1000);
                            const m = Math.floor(diff / 60), s = diff % 60;
                            this.elapsed = m > 0 ? m + 'min ' + s + 's' : s + 's';
                        };
                        update();
                        setInterval(update, 1000);
                    }
                }"
                class="flex items-center gap-1 text-xs text-gray-400"
            >
                <template x-if="ts">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 rounded-full">
                        <span>⏱</span>
                        <span x-text="'há ' + elapsed"></span>
                    </span>
                </template>
                <template x-if="!ts">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 rounded-full text-gray-400">
                        Nunca sincronizado
                    </span>
                </template>
            </div>
        </div>
    </div>

    {{-- ── 8 KPI cards ── --}}
    <div style="display:grid; grid-template-columns: repeat(8, 1fr); gap:10px; margin-bottom:16px;">

        {{-- KPI 1 — Linhas Ativas --}}
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Linhas Ativas</p>
            <p class="text-xl font-bold text-gray-700">{{ $kpis['linhas_ativas'] }}</p>
            @if($kpis['linhas_em_alerta'] > 0)
                <p class="text-xs text-amber-600 mt-0.5">{{ $kpis['linhas_em_alerta'] }} em alerta</p>
            @else
                <p class="text-xs text-gray-400 mt-0.5">todas operando</p>
            @endif
        </div>

        {{-- KPI 2 — Situação Geral --}}
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Situação Geral</p>
            <div class="text-xs space-y-0.5 mt-1">
                <div class="flex justify-between">
                    <span class="text-green-600">● Em dia</span>
                    <span class="font-semibold text-green-600">{{ $kpis['situacao_geral']['em_dia'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-amber-500">● Atenção</span>
                    <span class="font-semibold text-amber-500">{{ $kpis['situacao_geral']['atencao'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-red-600">● Atrasadas</span>
                    <span class="font-semibold text-red-600">{{ $kpis['situacao_geral']['atrasadas'] }}</span>
                </div>
            </div>
        </div>

        {{-- KPI 3 — Produção Ontem (PCP) --}}
        @php
            $corOntem = $kpis['pct_ontem'] !== null
                ? ($kpis['pct_ontem'] >= 80 ? '#639922' : ($kpis['pct_ontem'] >= 50 ? '#EF9F27' : '#E24B4A'))
                : '#9CA3AF';
        @endphp
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Produção Ontem</p>
            <p class="text-xl font-bold text-gray-700">
                {{ number_format($kpis['produzido_ontem'], 0, ',', '.') }} cx
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                de {{ number_format($kpis['previsto_ontem'], 0, ',', '.') }} cx
            </p>
            @if($kpis['pct_ontem'] !== null)
                <div class="mt-1.5 h-1 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-1 rounded-full"
                         style="width: {{ min(100, $kpis['pct_ontem']) }}%; background-color: {{ $corOntem }}">
                    </div>
                </div>
                <p class="text-xs mt-0.5 font-medium" style="color: {{ $corOntem }}">
                    {{ $kpis['pct_ontem'] }}% realizado
                </p>
            @endif
        </div>

        {{-- KPI 4 — Produção Hoje (PCP) --}}
        @php
            $corHoje = $kpis['pct_hoje'] !== null
                ? ($kpis['pct_hoje'] >= 80 ? '#639922' : ($kpis['pct_hoje'] >= 50 ? '#EF9F27' : '#E24B4A'))
                : '#9CA3AF';
        @endphp
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Produção Hoje</p>
            <p class="text-xl font-bold text-gray-700">
                {{ number_format($kpis['produzido_hoje'], 0, ',', '.') }} cx
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                de {{ number_format($kpis['previsto_hoje'], 0, ',', '.') }} cx
            </p>
            @if($kpis['pct_hoje'] !== null)
                <div class="mt-1.5 h-1 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-1 rounded-full"
                         style="width: {{ min(100, $kpis['pct_hoje']) }}%; background-color: {{ $corHoje }}">
                    </div>
                </div>
                <p class="text-xs mt-0.5 font-medium" style="color: {{ $corHoje }}">
                    {{ $kpis['pct_hoje'] }}% realizado
                </p>
            @else
                <p class="text-xs text-gray-400 mt-2">em tempo real</p>
            @endif
        </div>

        {{-- KPI 5 — Disponibilidade --}}
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Disponibilidade</p>
            @if($kpis['disp_media'] !== null)
                @php $dispColor = $kpis['disp_media'] >= 85 ? 'text-green-600' : ($kpis['disp_media'] >= 70 ? 'text-amber-500' : 'text-red-600'); @endphp
                <p class="text-xl font-bold {{ $dispColor }}">{{ number_format($kpis['disp_media'], 1, ',', '.') }}%</p>
            @else
                <p class="text-xl font-bold text-gray-300">—</p>
            @endif
            <p class="text-xs text-gray-400 mt-0.5">média do dia</p>
        </div>

        {{-- KPI 6 — Performance --}}
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Performance</p>
            @if($kpis['perf_media'] !== null)
                @php $perfColor = $kpis['perf_media'] >= 85 ? 'text-green-600' : ($kpis['perf_media'] >= 70 ? 'text-amber-500' : 'text-red-600'); @endphp
                <p class="text-xl font-bold {{ $perfColor }}">{{ number_format($kpis['perf_media'], 1, ',', '.') }}%</p>
            @else
                <p class="text-xl font-bold text-gray-300">—</p>
            @endif
            <p class="text-xs text-gray-400 mt-0.5">média do dia</p>
        </div>

        {{-- KPI 7 — Qualidade --}}
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Qualidade</p>
            <p class="text-xl font-bold text-green-600">100,0%</p>
            <p class="text-xs text-gray-400 mt-0.5">sem dado de refugo</p>
        </div>

        {{-- KPI 8 — OEE Médio --}}
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">OEE Médio do Dia</p>
            @if($kpis['oee_medio'] !== null)
                @php
                    $oeeRaw = $kpis['oee_medio'];
                    $oeeColor = $oeeRaw >= 85 ? 'text-green-600' : ($oeeRaw >= 70 ? 'text-amber-500' : 'text-red-600');
                @endphp
                <p class="text-xl font-bold {{ $oeeColor }}">
                    {{ number_format($kpis['oee_medio'], 1, ',', '.') }}%
                </p>
            @else
                <p class="text-xl font-bold text-gray-300">—</p>
            @endif
            <p class="text-xs text-gray-400 mt-0.5">média do dia</p>
        </div>

    </div>

    {{-- Cards secundários: Motivos de Parada + Performance das Linhas --}}
    <div style="display:grid; grid-template-columns: 337.5px 587.5px; gap:12px; margin-bottom:16px;">
        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm" style="width:337.5px; justify-self:start;">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Top 3 motivos de parada</p>
                <select wire:change="setMotivoLinha($event.target.value)"
                        class="text-xs border border-gray-200 rounded px-1 py-0.5 text-gray-600 bg-white">
                    <option value="" {{ $motivoLinha === '' ? 'selected' : '' }}>Todas as linhas</option>
                    @foreach($linhas as $l)
                        <option value="{{ $l['codigo'] }}" {{ $motivoLinha === $l['codigo'] ? 'selected' : '' }}>
                            {{ $l['nome'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-0.5 mb-2">
                @foreach(['hoje' => 'Hoje', '3d' => '3d', '7d' => '7d', '15d' => '15d'] as $key => $label)
                    <button wire:click="setMotivoPeriodo('{{ $key }}')"
                            class="flex-1 text-xs py-0.5 rounded border transition-colors
                                   {{ $motivoPeriodo === $key
                                      ? 'bg-gray-100 border-gray-300 text-gray-700 font-medium'
                                      : 'border-gray-200 text-gray-400 hover:text-gray-600' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @php
                $motivos     = $this->getMotivosParada();
                $totalParado = $this->getTotalParado();
                $maxMin      = !empty($motivos) ? max(1, (int) ($motivos[0]->total_min ?? 0)) : 1;
                $cores       = ['#E24B4A', '#EF9F27', '#B4B2A9'];
            @endphp

            @if(count($motivos) > 0)
                <div class="flex flex-col gap-1.5">
                    @foreach($motivos as $i => $m)
                        <div>
                            <div class="flex justify-between items-baseline mb-0.5">
                                <span class="text-xs text-gray-600 truncate pr-1" style="max-width:65%;">
                                    {{ mb_convert_case(mb_strtolower($m->motivo, 'UTF-8'), MB_CASE_TITLE, 'UTF-8') }}
                                </span>
                                <span class="text-xs font-medium text-gray-700">
                                    @php $h = intdiv($m->total_min, 60); $min = $m->total_min % 60; @endphp
                                    {{ $h > 0 ? $h.'h'.$min.'m' : $min.'m' }}
                                </span>
                            </div>
                            <div class="h-1 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-1 rounded-full"
                                     style="width:{{ round($m->total_min / $maxMin * 100) }}%; background:{{ $cores[$i] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-gray-400 text-center py-2">Sem paradas no período</p>
            @endif

            <div class="border-t border-gray-100 pt-1.5 mt-1.5 flex justify-between items-center">
                <span class="text-xs text-gray-400">Total parado no período</span>
                @php
                    $hT = intdiv($totalParado, 60);
                    $mT = $totalParado % 60;
                @endphp
                <span class="text-xl font-bold" style="color:#E24B4A;">
                    {{ $hT > 0 ? $hT.'h '.$mT.'m' : $mT.'m' }}
                </span>
            </div>
        </div>
    {{-- Card Performance das Linhas --}}
    <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm" style="width:780px; justify-self:start;">

        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Performance das linhas</p>
            <div class="flex gap-1">
                @foreach(['hoje' => 'Hoje', '3d' => '3d', '7d' => '7d', '15d' => '15d'] as $key => $label)
                    <button wire:click="setPerfPeriodo('{{ $key }}')"
                            class="text-xs px-2 py-0.5 rounded border transition-colors
                                   {{ $perfPeriodo === $key
                                      ? 'bg-gray-100 border-gray-300 text-gray-700 font-medium'
                                      : 'border-gray-200 text-gray-400 hover:text-gray-600' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @php $perfLinhas = $this->getPerformanceLinhas(); @endphp

        <table class="w-full text-xs" style="border-collapse:collapse; flex:1;">
            <thead>
                <tr style="border-bottom:0.5px solid #e5e7eb;">
                    <th class="text-left py-0 text-gray-400 font-medium text-xs">Linha</th>
                    <th class="text-right py-0 px-2 text-gray-400 font-medium text-xs whitespace-nowrap">Cx prod.</th>
                    <th class="text-right py-0 px-2 text-gray-400 font-medium text-xs whitespace-nowrap">Ritmo</th>
                    <th class="text-right py-0 px-2 text-gray-400 font-medium text-xs">Perf.</th>
                    <th class="text-left py-0 pl-2 text-gray-400 font-medium text-xs" style="min-width:75px;">Disponib.</th>
                    <th class="text-right py-0 px-2 text-gray-400 font-medium text-xs whitespace-nowrap">T. Prod</th>
                    <th class="text-right py-0 px-2 text-gray-400 font-medium text-xs whitespace-nowrap">T. Parado</th>
                    <th class="text-center py-0 px-2 text-gray-400 font-medium text-xs whitespace-nowrap">Status</th>
                    <th class="text-right py-0 px-2 text-gray-400 font-medium text-xs whitespace-nowrap">Tempo atraso</th>
                </tr>
            </thead>
            <tbody>
                @foreach($perfLinhas as $pl)
                    @php
                        $d        = $pl['disponib'];
                        $corBarra = $d === null ? '#e5e7eb' : ($d >= 80 ? '#639922' : ($d >= 60 ? '#EF9F27' : '#E24B4A'));
                        $corTexto = $d === null ? '#9ca3af' : ($d >= 80 ? '#3B6D11' : ($d >= 60 ? '#854F0B' : '#A32D2D'));
                        $hhmmProd = minutos_para_hhmm((int) round($pl['tempo_prod_h'] * 60));
                        $hhmmPar  = minutos_para_hhmm((int) round($pl['tempo_parado_h'] * 60));
                    @endphp
                    <tr style="border-bottom:0.5px solid #f3f4f6;">
                        <td class="py-0 text-gray-600 font-medium text-xs whitespace-nowrap">
                            {{ $pl['nome'] }}
                        </td>
                        <td class="py-0 px-2 text-right text-gray-700 text-xs whitespace-nowrap">
                            {{ $pl['cx_prod'] > 0 ? number_format($pl['cx_prod'], 0, ',', '.') : '—' }}
                        </td>
                        <td class="py-0 px-2 text-right text-gray-500 text-xs whitespace-nowrap">
                            {{ $pl['ritmo'] ? $pl['ritmo'].' cx/h' : '—' }}
                        </td>
                        @php
                            $perf    = $pl['performance'];
                            $perfCor = is_null($perf) ? '#9ca3af' : ($perf >= 80 ? '#639922' : ($perf >= 60 ? '#EF9F27' : '#E24B4A'));
                        @endphp
                        <td class="py-0 px-2 text-right text-xs font-medium whitespace-nowrap" style="color:{{ $perfCor }}">
                            {{ !is_null($perf) ? number_format($perf, 1, ',', '.') . '%' : '—' }}
                        </td>
                        <td class="py-0 pl-2 text-xs">
                            <div style="display:flex; align-items:center; gap:5px;">
                                <div style="flex:1; height:3px; background:#f3f4f6; border-radius:2px; overflow:hidden;">
                                    <div style="width:{{ $d ?? 0 }}%; height:100%; background:{{ $corBarra }}; border-radius:2px;"></div>
                                </div>
                                <span class="text-xs" style="color:{{ $corTexto }}; min-width:28px;">
                                    {{ $d !== null ? $d.'%' : '—' }}
                                </span>
                            </div>
                        </td>
                        <td class="py-0 px-2 text-right text-gray-600 text-xs whitespace-nowrap">{{ $hhmmProd }}</td>
                        <td class="py-0 px-2 text-right text-gray-600 text-xs whitespace-nowrap">{{ $hhmmPar }}</td>
                        <td class="py-0 px-2 text-center text-xs font-medium whitespace-nowrap"
                            style="color:{{
                                $pl['status'] === 'Parada Prog.' ? '#F97316' :
                                ($pl['status'] === 'Parada'      ? '#E24B4A' :
                                ($pl['status'] === 'Atrasada'    ? '#E24B4A' :
                                ($pl['status'] === 'Em dia'      ? '#639922' :
                                ($pl['status'] === 'Atenção'     ? '#EF9F27' :
                                ($pl['status'] === 'Sem sinal'   ? '#6B7280' :
                                '#9ca3af')))))
                            }};">
                            {{ $pl['status'] }}
                        </td>
                        @php
                            $taMin = $pl['tempo_atraso_min'];
                            if ($taMin === null) {
                                $taTexto = '—';
                                $taCor   = 'text-gray-400';
                            } else {
                                $taTexto = minutos_para_hhmm($taMin);
                                $taCor   = $taMin > 60 ? 'text-red-600' : 'text-amber-500';
                            }
                        @endphp
                        <td class="py-0 px-2 text-right text-xs font-medium whitespace-nowrap {{ $taCor }}">{{ $taTexto }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200">
                    <td class="py-1 text-xs text-gray-500 font-medium">Total</td>
                    <td class="py-1 px-2 text-right text-xs font-semibold text-gray-700 whitespace-nowrap">
                        {{ number_format(collect($perfLinhas)->sum('cx_prod'), 0, ',', '.') }} cx
                    </td>
                    <td colspan="7"></td>
                </tr>
            </tfoot>
        </table>

        <div style="border-top:0.5px solid #f3f4f6; padding-top:2px; margin-top:1px; display:flex; gap:10px;">
            <span class="text-xs text-gray-400">
                <span style="color:#639922;">●</span> ≥80%
                <span style="margin-left:4px; color:#EF9F27;">●</span> 60–80%
                <span style="margin-left:4px; color:#E24B4A;">●</span> &lt;60%
            </span>
        </div>
    </div>
    <div></div><div></div><div></div>

    </div>

    {{-- ── Legenda de status ── --}}
    <div style="padding: 6px 0 10px; display:flex; flex-wrap:wrap; gap:6px 16px; align-items:center;">
        <p style="font-size:11px; color:var(--color-text-tertiary); margin:0; font-weight:500; letter-spacing:0.04em; text-transform:uppercase; flex-basis:100%;">status das linhas</p>
        <div style="display:flex; align-items:center; gap:6px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#639922;flex-shrink:0;"></span>
            <span style="font-size:11px;color:var(--color-text-secondary);"><span style="font-weight:500;color:var(--color-text-primary);">Em dia</span> — iniciou com até 15min de atraso</span>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#EF9F27;flex-shrink:0;"></span>
            <span style="font-size:11px;color:var(--color-text-secondary);"><span style="font-weight:500;color:var(--color-text-primary);">Atenção</span> — iniciou com mais de 15min de atraso</span>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#E24B4A;flex-shrink:0;"></span>
            <span style="font-size:11px;color:var(--color-text-secondary);"><span style="font-weight:500;color:var(--color-text-primary);">Atrasada</span> — OP na fila não iniciada após 15min do previsto</span>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#B4B2A9;flex-shrink:0;"></span>
            <span style="font-size:11px;color:var(--color-text-secondary);"><span style="font-weight:500;color:var(--color-text-primary);">Aguardando</span> — sem eventos de produção registrados</span>
        </div>
    </div>

    {{-- ── Grid de linhas ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        @forelse($linhas as $linha)
            @php
                $corBorda = ['red' => '#E24B4A', 'yellow' => '#EF9F27', 'green' => '#639922', 'orange' => '#F97316', 'gray' => '#9CA3AF'][$linha['cor']] ?? '#9CA3AF';
                $badgeClasses = ['red' => 'bg-red-100 text-red-700', 'yellow' => 'bg-amber-100 text-amber-700', 'green' => 'bg-green-100 text-green-700', 'orange' => 'bg-orange-100 text-orange-700', 'gray' => 'bg-gray-100 text-gray-500'][$linha['cor']] ?? 'bg-gray-100 text-gray-500';
                $progressColor = ['red' => '#EF4444', 'yellow' => '#FBBF24', 'green' => '#22C55E', 'gray' => '#D1D5DB'][$linha['cor']] ?? '#D1D5DB';
            @endphp

            <div class="bg-white rounded-xl shadow-sm"
                 style="border-left: 3px solid {{ $corBorda }}; border-top: 1px solid #E5E7EB; border-right: 1px solid #E5E7EB; border-bottom: 1px solid #E5E7EB; padding: 10px 14px;">

                {{-- Cabeçalho do card --}}
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-bold text-gray-800 text-sm">{{ $linha['nome'] }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $linha['ops_concluidas'] }}/{{ $linha['total_ops'] }} OPs
                        </p>
                        <p class="text-xs text-gray-400">
                            {{ number_format($linha['qtd_realizada_total'], 0, ',', '.') }} / {{ number_format($linha['qtd_programada_total'], 0, ',', '.') }} cx
                        </p>
                        @if(!empty($linha['estado_atual_codi']))
                            @php
                                $codiEstado = $linha['estado_atual_codi'];
                                $codiBadge = 'bg-gray-100 text-gray-500';
                                if (stripos($codiEstado, 'produz') !== false) {
                                    $codiBadge = 'bg-green-100 text-green-700';
                                } elseif (stripos($codiEstado, 'setup') !== false) {
                                    $codiBadge = 'bg-amber-100 text-amber-700';
                                } elseif (stripos($codiEstado, 'para') !== false || stripos($codiEstado, 'stop') !== false) {
                                    $codiBadge = 'bg-red-100 text-red-700';
                                }
                            @endphp
                            <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium mt-1 {{ $codiBadge }}">
                                {{ $codiEstado }}
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $badgeClasses }}">
                            {{ $linha['estado'] }}
                        </span>
                        @if($linha['cor'] === 'red')
                            @if(!empty($linha['op_atual']['inicio_real_dt']))
                                {{-- OP em andamento com atraso --}}
                                <div style="font-size:11px; color:#991b1b; text-align:right; line-height:1.6;">
                                    <div>Iniciou em: {{ $linha['op_atual']['inicio_real_dt'] }}</div>
                                    @if(!empty($linha['op_atual']['motivo_atraso']))
                                        <div style="font-weight:500;">⚠ {{ $linha['op_atual']['motivo_atraso'] }}</div>
                                    @endif
                                    @php
                                        $op = $linha['op_atual'];
                                        $minRestantes = ($op['tempo_previsto_min'] ?? 0) - ($op['tempo_rodando_min'] ?? 0);
                                        $ritmoNecessario = ($minRestantes > 0 && isset($op['faltam']) && $op['faltam'] > 0)
                                            ? round($op['faltam'] / ($minRestantes / 60))
                                            : null;
                                        $ritmoCxH = $op['ritmo_cxh'] ?? null;
                                    @endphp
                                    @if($ritmoCxH !== null && $ritmoNecessario !== null)
                                        <div style="color:#6b7280;">
                                            Ritmo: <span style="color:#991b1b; font-weight:500;">{{ number_format($ritmoCxH, 0, ',', '.') }} cx/h</span>
                                            · Necessário: <span style="color:#991b1b; font-weight:500;">{{ number_format($ritmoNecessario, 0, ',', '.') }} cx/h</span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                {{-- OP na fila não iniciada --}}
                                <div style="font-size:11px; color:#991b1b; text-align:right; line-height:1.6;">
                                    <div>Deveria iniciar: {{ $linha['op_atual']['inicio_previsto_dt'] ?? '—' }}</div>
                                    <div style="color:#b45309;">Ainda não iniciou</div>
                                </div>
                            @endif
                            <span style="display:none;">{{ $linha['ultimo_sync'] }}</span>

                        @elseif($linha['cor'] === 'green')
                            {{-- EM DIA --}}
                            <div style="font-size:11px; color:#374151; text-align:right; line-height:1.6;">
                                @if(!empty($linha['op_atual']['inicio_real_dt']))
                                    <div>
                                        Iniciou em: {{ $linha['op_atual']['inicio_real_dt'] }}
                                        @if(!empty($linha['op_atual']['atraso_inicio_min']) && $linha['op_atual']['atraso_inicio_min'] > 0)
                                            @php
                                                $atMin = $linha['op_atual']['atraso_inicio_min'];
                                                $atH   = intdiv($atMin, 60);
                                                $atM   = $atMin % 60;
                                                $atStr = $atH > 0 ? "+{$atH}h {$atM}min" : "+{$atM}min";
                                            @endphp
                                            <span style="color:#b45309;">({{ $atStr }} do planejado)</span>
                                        @endif
                                    </div>
                                @else
                                    @if(($linha['op_atual']['tempo_rodando_min'] ?? 0) > 0)
                                        <div style="color:#9ca3af;">Em produção</div>
                                    @else
                                        <div style="color:#9ca3af;">Aguardando início</div>
                                    @endif
                                @endif
                            </div>
                            <span style="display:none;">{{ $linha['ultimo_sync'] }}</span>

                        @else
                            <span class="text-xs text-gray-400">{{ $linha['ultimo_sync'] }}</span>
                        @endif
                        @if(!empty($linha['sem_sinal_codi']))
                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium">
                                Sem sinal CODI
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Parada em aberto --}}
                @if(!empty($linha['parada_aberta']))
                    @php
                        $pMin = $linha['parada_aberta']['minutos'];
                        $pHoras = floor($pMin / 60);
                        $pResto = $pMin % 60;
                        $pTempo = ($pHoras > 0 ? $pHoras . 'h ' : '') . $pResto . 'min';
                    @endphp
                    <div class="mb-3 flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs font-medium text-red-700">
                        <span>⚠</span>
                        <span>PARADA há {{ $pTempo }}{{ !empty($linha['parada_aberta']['nome']) ? ' — ' . $linha['parada_aberta']['nome'] : '' }}</span>
                    </div>
                @endif

                {{-- OP atual --}}
                @if($linha['op_atual'])
                    @php
                        $op = $linha['op_atual'];
                        $atrasadoMin = $op['atrasado_min'];
                        $corTempo = !empty($op['tempo_atrasado'] ?? false) ? 'text-red-600'
                            : ($op['tempo_rodando_min'] !== null ? 'text-green-600' : 'text-gray-500');
                    @endphp

                    {{-- Linha OP: número + SKU + descrição --}}
                    <div class="mb-2" style="padding: 6px 0; margin-bottom: 6px;">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-mono font-semibold text-gray-700">OP {{ $op['numero_op'] }}</span>
                            <span class="text-xs text-gray-400">{{ $op['sku'] }}</span>
                        </div>
                        <p class="text-sm text-gray-600 truncate mt-0.5" title="{{ $op['descricao'] }}">
                            {{ $op['descricao'] }}
                        </p>
                        @php
                            $opAtual = $linha['op_atual'] ?? [];
                            $inicioP = $opAtual['inicio_previsto_dt'] ?? null;
                            $inicioR = $opAtual['inicio_real_dt'] ?? null;
                            $atrMin  = $opAtual['atraso_inicio_min'] ?? null;
                        @endphp
                        @if($inicioP || $inicioR)
                            <div style="font-size:11px; color:#6b7280; margin-top:2px; display:flex; gap:12px; flex-wrap:wrap;">
                                @if($inicioP)
                                    <span>Previsto: <span style="color:#374151;">{{ $inicioP }}</span></span>
                                @endif
                                @if($inicioR)
                                    <span>
                                        Iniciou: <span style="color:#374151;">{{ $inicioR }}</span>
                                        @if($atrMin !== null && $atrMin > 0)
                                            @php
                                                $h = intdiv($atrMin, 60);
                                                $m = $atrMin % 60;
                                                $str = $h > 0 ? "(+{$h}h {$m}min)" : "(+{$m}min)";
                                            @endphp
                                            <span style="color:#b45309;">{{ $str }}</span>
                                        @elseif($atrMin !== null && $atrMin <= 0)
                                            <span style="color:#27500A;">(no prazo)</span>
                                        @endif
                                    </span>
                                @elseif($inicioP)
                                    <span style="color:#b45309;">Ainda não iniciou</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Quantidades --}}
                    <div class="flex items-center justify-between text-sm mb-2">
                        <span class="font-medium text-gray-700">
                            {{ number_format($op['realizado'], 0, ',', '.') }}
                            @if(($op['programado'] ?? 0) > 0)
                                / {{ number_format($op['programado'], 0, ',', '.') }} cx
                            @else
                                cx
                            @endif
                        </span>
                        @if(($op['programado'] ?? 0) > 0)
                        <span class="text-xs text-gray-500">
                            Faltam: <span class="font-medium text-gray-700">{{ number_format($op['faltam'], 0, ',', '.') }} cx</span>
                        </span>
                        @endif
                    </div>

                    {{-- Barra de progresso --}}
                    <div class="mt-2 h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-2 rounded-full transition-all"
                             style="width: {{ $op['pct'] }}%; background-color: {{ $progressColor }}">
                        </div>
                    </div>
                    <div class="flex items-baseline justify-between mt-1 mb-3">
                        <span class="text-base font-bold text-gray-700">{{ $op['pct'] }}%</span>
                        @php
                            $ritmoDisplay = $op['ritmo_cxh'] > 0 ? $op['ritmo_cxh'] . ' cx/h' : '—';
                            $ritmoColor   = 'text-gray-400';
                            $taxaDisplay  = '';
                            if ($op['ritmo_cxh'] > 0 && !empty($op['taxa_nominal_cxh']) && $op['taxa_nominal_cxh'] > 0) {
                                $pctTaxa     = (int) round($op['ritmo_cxh'] / $op['taxa_nominal_cxh'] * 100);
                                $taxaDisplay = ' / ' . $op['taxa_nominal_cxh'] . ' prev (' . $pctTaxa . '%)';
                                $ritmoColor  = $pctTaxa < 90 ? 'text-amber-600' : 'text-gray-400';
                            }
                        @endphp
                        <span class="text-xs {{ $ritmoColor }}">{{ $ritmoDisplay }}{{ $taxaDisplay }}</span>
                    </div>

                    {{-- Tempos: Previsto | Rodando | ETA conclusão (3 colunas) --}}
                    <div class="grid grid-cols-3 text-xs" style="gap:4px; margin-top:4px; margin-bottom:4px;">
                        <div class="bg-gray-50 rounded-lg p-2" style="font-size:11px;">
                            <p class="text-gray-400 mb-0.5">Previsto</p>
                            <p class="font-medium text-gray-600">
                                {{ floor($op['tempo_previsto_min'] / 60) }}h {{ $op['tempo_previsto_min'] % 60 }}m
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2" style="font-size:11px;">
                            <p class="text-gray-400 mb-0.5">Rodando</p>
                            <p class="font-medium {{ $corTempo }}">
                                @if($op['tempo_rodando_min'] !== null)
                                    {{ floor($op['tempo_rodando_min'] / 60) }}h {{ $op['tempo_rodando_min'] % 60 }}m
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2" style="font-size:11px;">
                            <p class="text-gray-400 mb-0.5">ETA conclusão</p>
                            <p class="font-medium {{ $corTempo }}">{{ $op['eta_formatada'] ?? '—' }}</p>
                            @if(!empty($op['desvio_prazo_em_andamento_horas']))
                                <p class="text-xs mt-0.5 text-red-500">
                                    +{{ $op['desvio_prazo_em_andamento_horas'] }}h de atraso
                                </p>
                            @elseif(!empty($op['desvio_prazo_dias']))
                                <p class="text-xs mt-0.5 {{ $op['desvio_prazo_dias'] > 0 ? 'text-red-500' : 'text-green-600' }}">
                                    {{ $op['desvio_prazo_dias'] > 0 ? '+' . $op['desvio_prazo_dias'] . ' dias' : abs($op['desvio_prazo_dias']) . ' dias adiant.' }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Tempo total parado hoje --}}
                    @if(isset($linha['tempo_parado_hoje_min']) && $linha['tempo_parado_hoje_min'] > 0)
                        @php
                            $pHoje = $linha['tempo_parado_hoje_min'];
                            $pHojeH = floor($pHoje / 60);
                            $pHojeM = $pHoje % 60;
                        @endphp
                        <p class="text-xs text-amber-600 mb-2">
                            Parado hoje: {{ $pHojeH > 0 ? $pHojeH . 'h ' : '' }}{{ $pHojeM }}min
                        </p>
                    @endif

                    {{-- Chips OEE em tempo real (por linha, calculado de codi_eventos.dados_raw) --}}
                    @php
                        $oeeReal = $linha['oee_tempo_real'] ?? [];
                        $dispVal = $oeeReal['disponibilidade'] ?? null;
                        $perfVal = $oeeReal['performance']     ?? null;
                        $oeeVal  = $oeeReal['oee']             ?? null;
                        $oeeBg   = $oeeVal === null ? '#e5e7eb' : ($oeeVal >= 85 ? '#dcfce7' : ($oeeVal >= 65 ? '#fef9c3' : '#fee2e2'));
                        $oeeFg   = $oeeVal === null ? '#6b7280' : ($oeeVal >= 85 ? '#166534' : ($oeeVal >= 65 ? '#854d0e' : '#991b1b'));
                    @endphp
                    @if($oeeVal !== null || $dispVal !== null)
                        <div class="flex flex-wrap gap-1.5 mb-1">
                            <span class="px-2 py-0.5 rounded text-xs font-medium"
                                  style="background:{{ $oeeBg }}; color:{{ $oeeFg }}">
                                OEE {{ $oeeVal !== null ? $oeeVal.'%' : '—' }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                Disp {{ $dispVal !== null ? $dispVal.'%' : '—' }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                Perf {{ $perfVal !== null ? $perfVal.'%' : '—' }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                Qual 100%
                            </span>
                        </div>
                    @endif

                @else
                    {{-- Estado vazio: aguardando início --}}
                    <div class="mt-4 flex items-center gap-2 text-sm text-gray-400">
                        <span>⏳</span>
                        <span>Aguardando início da produção</span>
                    </div>
                @endif

                {{-- Próximas OPs --}}
                @if(!empty($linha['proximas_ops']))
                    <div class="border-t border-gray-100" style="margin-top:8px; padding-top:8px;">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Próximas OPs</p>
                        <div class="overflow-x-auto">
                            <table class="w-full" style="font-size:11px;">
                                <thead>
                                    <tr class="text-gray-400">
                                        <th class="text-left py-1 pr-2">OP</th>
                                        <th class="text-left py-1 pr-2">Produto</th>
                                        <th class="text-right py-1 pr-2">Qtd</th>
                                        <th class="text-right py-1 pr-2">Tempo est.</th>
                                        <th class="text-right py-1">Início prev.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($linha['proximas_ops'] as $prox)
                                        <tr class="border-t border-gray-50 text-gray-600">
                                            <td class="py-1 pr-2 font-mono">{{ $prox['numero_op'] }}</td>
                                            <td class="py-1 pr-2 max-w-[160px]" title="{{ $prox['descricao'] }}">
                                                <span class="text-gray-700">{{ $prox['sku'] }}</span>
                                                @if(!empty($prox['descricao']))
                                                    <br><span class="text-xs text-gray-400 truncate block">{{ $prox['descricao'] }}</span>
                                                @endif
                                            </td>
                                            <td class="py-1 pr-2 text-right">{{ number_format($prox['programado'], 0, ',', '.') }}</td>
                                            <td class="py-1 pr-2 text-right">
                                                @if($prox['tempo_previsto_min'] > 0)
                                                    {{ floor($prox['tempo_previsto_min'] / 60) }}h {{ $prox['tempo_previsto_min'] % 60 }}m
                                                @else
                                                    —
                                                @endif
                                                @if(!empty($prox['setup_min']))
                                                    @php
                                                        $sH = floor($prox['setup_min'] / 60);
                                                        $sM = $prox['setup_min'] % 60;
                                                        $setupStr = ($sH > 0 ? $sH . 'h' : '') . ($sM > 0 ? ($sH > 0 ? ' ' : '') . $sM . 'm' : '');
                                                    @endphp
                                                    <span class="block text-amber-500">+{{ $setupStr }} setup</span>
                                                @endif
                                            </td>
                                            <td class="py-1 text-right"
                                                style="{{ $prox['inicio_previsto_status'] === 'vencido' ? 'color:#ef4444;font-weight:500;' : ($prox['inicio_previsto_status'] === 'hoje' ? 'color:#f59e0b;' : 'color:#9ca3af;') }}">
                                                @if($prox['inicio_previsto_status'] === 'vencido')
                                                    &#9888; {{ $prox['inicio_previsto'] }}
                                                @else
                                                    {{ $prox['inicio_previsto'] ?? '—' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- HISTÓRICO 7 DIAS --}}
                @php
                    $hist = $linha['historico_7d'];
                    $chartId = 'chart-' . strtolower(str_replace(' ', '', $linha['codigo']));
                    $dispMedia = $hist['disponibilidade_media'];
                    $dispColor = $dispMedia === null ? 'text-gray-400' : ($dispMedia >= 85 ? 'text-green-600' : ($dispMedia >= 70 ? 'text-amber-500' : 'text-red-600'));
                    $horasColor = $hist['horas_paradas'] > 8 ? 'text-red-600' : 'text-gray-700';
                    $tendDirecao = $hist['tendencia']['direcao'];
                    $tendIcon = match($tendDirecao) {
                        'up'   => '↑',
                        'down' => '↓',
                        default => '→',
                    };
                    $tendClass = match($tendDirecao) {
                        'up'   => 'text-green-600',
                        'down' => 'text-red-600',
                        default => 'text-gray-500',
                    };
                @endphp
                <div class="border-t border-gray-100" style="margin-top:8px; padding-top:8px;">
                    <div class="flex items-center justify-between" style="margin-bottom:6px;">
                        <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Histórico — últimos 7 dias</span>
                        <span class="text-xs text-gray-400">fonte: CODI eventos</span>
                    </div>

                    {{-- KPIs inline flex --}}
                    <div style="display:flex; gap:24px; align-items:baseline; margin-bottom:8px;">
                        <div>
                            <span style="font-size:15px; font-weight:600; color:#1f2937;">{{ number_format($hist['producao_total']) }}</span>
                            <span style="font-size:11px; color:#9ca3af; margin-left:3px;">cx produzidos</span>
                        </div>
                        <div>
                            <span style="font-size:15px; font-weight:600; {{ $dispMedia !== null ? ($dispMedia >= 85 ? 'color:#16a34a;' : ($dispMedia >= 70 ? 'color:#d97706;' : 'color:#dc2626;')) : 'color:#9ca3af;' }}">
                                {{ $dispMedia !== null ? number_format($dispMedia, 1) . '%' : '—' }}
                            </span>
                            <span style="font-size:11px; color:#9ca3af; margin-left:3px;">disponibilidade</span>
                        </div>
                        <div>
                            <span style="font-size:15px; font-weight:600; {{ $hist['horas_paradas'] > 8 ? 'color:#dc2626;' : 'color:#374151;' }}">
                                {{ number_format($hist['horas_paradas'], 1) }}h
                            </span>
                            <span style="font-size:11px; color:#9ca3af; margin-left:3px;">paradas</span>
                        </div>
                    </div>

                    {{-- Tendência de ritmo --}}
                    @if($hist['tendencia']['ritmo_atual'] !== null && $hist['tendencia']['ritmo_anterior'] !== null)
                    <div class="flex items-center gap-1 text-xs" style="font-size:11px; margin-bottom:4px; margin-top:2px;">
                        <span class="font-semibold {{ $tendClass }} text-sm">{{ $tendIcon }}</span>
                        <span class="{{ $tendClass }} font-medium">
                            Ritmo {{ match($tendDirecao) { 'up' => 'acelerando', 'down' => 'desacelerando', default => 'estável' } }}
                        </span>
                        <span class="text-gray-400 ml-1">
                            semana passada {{ number_format($hist['tendencia']['ritmo_anterior'], 1) }} cx/h
                            · esta semana {{ number_format($hist['tendencia']['ritmo_atual'], 1) }} cx/h
                        </span>
                    </div>
                    @endif

                    {{-- Mini gráfico Chart.js --}}
                    <div class="relative" style="height:50px;" wire:ignore>
                        <canvas id="{{ $chartId }}"
                            data-producao="{{ json_encode(array_column($hist['dados_grafico'], 'producao_qty')) }}"
                            data-paradas="{{ json_encode(array_column($hist['dados_grafico'], 'paradas_min')) }}"
                            data-disponibilidade="{{ json_encode(array_column($hist['dados_grafico'], 'disponibilidade')) }}"
                            data-labels="{{ json_encode(array_map(fn($d) => substr($d['data'], 8, 2) . '/' . substr($d['data'], 5, 2), $hist['dados_grafico'])) }}"
                        ></canvas>
                    </div>

                    {{-- Legenda --}}
                    <div class="flex items-center gap-4 mt-2 justify-center">
                        <div class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-sm" style="background:#639922"></span>
                            <span class="text-xs text-gray-500">Produção (cx)</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-sm" style="background:#E24B4A"></span>
                            <span class="text-xs text-gray-500">Paradas (h)</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="inline-block w-3 h-2 rounded-sm" style="background:#378ADD"></span>
                            <span class="text-xs text-gray-500">Disponibilidade (%)</span>
                        </div>
                    </div>
                </div>

            </div>

        @empty
            {{-- Estado vazio: sem linhas ativas --}}
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400">
                <p class="text-4xl mb-3">🏭</p>
                <p class="text-sm">Nenhuma linha com programação confirmada ativa.</p>
            </div>
        @endforelse

    </div>

    {{-- Inicialização dos mini-gráficos de histórico (Chart.js já carregado no layout) --}}
    <script>
        function initHistoricoCharts() {
            document.querySelectorAll('canvas[id^="chart-"]').forEach(function (canvas) {
                // Destruir instância anterior para evitar sobreposição após wire:poll ou navegação
                var existing = Chart.getChart(canvas);
                if (existing) {
                    existing.destroy();
                }

                var labels        = JSON.parse(canvas.dataset.labels        || '[]');
                var producao      = JSON.parse(canvas.dataset.producao      || '[]');
                var paradasMin    = JSON.parse(canvas.dataset.paradas       || '[]');
                var disponib      = JSON.parse(canvas.dataset.disponibilidade || '[]');

                // Converter paradas de minutos para horas (1 casa decimal)
                var paradasHoras = paradasMin.map(function (m) {
                    return m !== null ? Math.round(m / 60 * 10) / 10 : null;
                });

                new Chart(canvas, {
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Produção (cx)',
                                data: producao,
                                backgroundColor: '#639922',
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                type: 'bar',
                                label: 'Paradas (h)',
                                data: paradasHoras,
                                backgroundColor: '#E24B4A',
                                yAxisID: 'y2',
                                order: 3,
                            },
                            {
                                type: 'line',
                                label: 'Disponibilidade (%)',
                                data: disponib,
                                borderColor: '#378ADD',
                                backgroundColor: 'rgba(55, 138, 221, 0.15)',
                                tension: 0.3,
                                pointRadius: 2,
                                borderWidth: 2,
                                fill: false,
                                yAxisID: 'y3',
                                order: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    font: { size: 10 },
                                    color: '#9CA3AF',
                                },
                            },
                            y:  { display: false },
                            y2: { display: false },
                            y3: { display: false, min: 0, max: 100 },
                        },
                    },
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initHistoricoCharts();
        });

        // Reinicializar após cada atualização do Livewire (wire:poll, dispatch, etc.)
        document.addEventListener('livewire:updated', function () {
            // Pequeno delay para garantir que o DOM do Livewire foi aplicado
            setTimeout(function () {
                initHistoricoCharts();
            }, 50);
        });
    </script>

</div>
