<div wire:poll.30s="refresh">

    <div class="flex items-center justify-between mb-5">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Acompanhar Produção — Sopro</h2>
        <span class="text-xs text-gray-400">
            Último sync: <span class="font-medium text-gray-600">{{ $ultimoSync }}</span>
        </span>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-4 lg:grid-cols-7 gap-3 mb-5">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Máquinas Ativas</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $kpis['maquinas_ativas'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $kpis['maquinas_em_alerta'] ?? 0 }} em alerta</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Situação Geral</p>
            <div class="text-xs mt-2 space-y-0.5">
                <p>🟢 Em dia <span class="font-semibold">{{ $kpis['situacao_geral']['em_dia'] ?? 0 }}</span></p>
                <p>🟡 Atenção <span class="font-semibold">{{ $kpis['situacao_geral']['atencao'] ?? 0 }}</span></p>
                <p>🔴 Atrasadas <span class="font-semibold">{{ $kpis['situacao_geral']['atrasadas'] ?? 0 }}</span></p>
            </div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Produção Hoje</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($kpis['produzido_hoje'] ?? 0, 0, ',', '.') }} un</p>
            <p class="text-xs text-gray-400 mt-1">
                @if($kpis['pct_hoje'] ?? null)
                    de {{ number_format($kpis['previsto_hoje'], 0, ',', '.') }} un · {{ $kpis['pct_hoje'] }}%
                @else
                    em tempo real
                @endif
            </p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Produção Ontem</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($kpis['produzido_ontem'] ?? 0, 0, ',', '.') }} un</p>
            <p class="text-xs text-gray-400 mt-1">realizado</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Disponibilidade</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $kpis['disp_media'] !== null ? $kpis['disp_media'].'%' : '—' }}</p>
            <p class="text-xs text-gray-400 mt-1">média do dia</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Performance</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $kpis['perf_media'] !== null ? $kpis['perf_media'].'%' : '—' }}</p>
            <p class="text-xs text-gray-400 mt-1">média do dia</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">OEE Médio</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $kpis['oee_medio'] !== null ? $kpis['oee_medio'].'%' : '—' }}</p>
            <p class="text-xs text-gray-400 mt-1">média do dia</p>
        </div>
    </div>

    {{-- Top 3 Motivos de Parada --}}
    <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm mb-5" style="max-width: 380px;">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Top 3 motivos de parada</p>
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
            $totalParado = array_sum(array_column((array) $motivos, 'total_min'));
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
                            <span class="text-xs font-medium text-gray-500">
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

        <div class="border-t border-gray-100 pt-1.5 mt-1.5 flex justify-between items-baseline">
            <span class="text-xs text-gray-400">Total parado no período</span>
            @php $hT = intdiv($totalParado, 60); $mT = $totalParado % 60; @endphp
            <span class="text-xl font-bold" style="color:#E24B4A;">
                {{ $hT > 0 ? $hT.'h '.$mT.'m' : $mT.'m' }}
            </span>
        </div>
    </div>

    <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Status das Máquinas</p>
    <div class="text-xs text-gray-400 mb-4 flex flex-wrap gap-4">
        <span>🟢 Em dia — produzindo dentro do esperado</span>
        <span>🔴 Atrasada / Parada / Excesso</span>
        <span>⚪ Sem sinal — sem eventos registrados</span>
    </div>

    {{-- Cards das máquinas --}}
    @if(count($maquinas) > 0)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($maquinas as $m)
                @php
                    $corBorda = ['red' => '#E24B4A', 'green' => '#639922', 'gray' => '#9CA3AF'][$m['cor']] ?? '#9CA3AF';
                    $badgeCor = ['red' => 'bg-red-100 text-red-700', 'green' => 'bg-green-100 text-green-700', 'gray' => 'bg-gray-100 text-gray-500'][$m['cor']] ?? 'bg-gray-100 text-gray-500';
                    $progressColor = ['red' => '#EF4444', 'green' => '#22C55E', 'gray' => '#D1D5DB'][$m['cor']] ?? '#D1D5DB';
                @endphp

                <div class="bg-white rounded-xl shadow-sm"
                     style="border-left: 3px solid {{ $corBorda }}; border-top: 1px solid #E5E7EB; border-right: 1px solid #E5E7EB; border-bottom: 1px solid #E5E7EB; padding: 10px 14px;">

                    {{-- Cabeçalho --}}
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">{{ $m['nome'] }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">Recurso CODI: R{{ $m['codigo_recurso'] }}</p>
                            @if($m['op_atual'])
                                <p class="text-xs text-gray-400">
                                    {{ number_format($m['op_atual']['realizado'], 0, ',', '.') }}
                                    @if($m['op_atual']['programado'])
                                        / {{ number_format($m['op_atual']['programado'], 0, ',', '.') }} un
                                    @else
                                        un
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $badgeCor }}">
                                {{ $m['status'] }}
                            </span>
                            @if($m['cor'] === 'green' && $m['op_atual'])
                                <div style="font-size:11px; color:#374151; text-align:right; line-height:1.6;">
                                    @if(($m['op_atual']['realizado'] ?? 0) > 0)
                                        <div style="color:#9ca3af;">Em produção</div>
                                    @else
                                        <div style="color:#9ca3af;">Aguardando início</div>
                                    @endif
                                </div>
                            @endif
                            @if($m['sem_sinal_codi'] ?? false)
                                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium">Sem sinal CODI</span>
                            @endif
                        </div>
                    </div>

                    {{-- Parada em aberto --}}
                    @if(!empty($m['parada_aberta']))
                        @php
                            $pMin = $m['parada_aberta']['minutos'];
                            $pTempo = (floor($pMin/60) > 0 ? floor($pMin/60).'h ' : '') . ($pMin%60) . 'min';
                        @endphp
                        <div class="mb-3 flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs font-medium text-red-700">
                            <span>⚠</span>
                            <span>PARADA há {{ $pTempo }}{{ !empty($m['parada_aberta']['nome']) ? ' — ' . $m['parada_aberta']['nome'] : '' }}</span>
                        </div>
                    @endif

                    {{-- OP atual --}}
                    @if($m['op_atual'])
                        @php $op = $m['op_atual']; @endphp

                        <div class="mb-2" style="padding: 6px 0; margin-bottom: 6px;">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono font-semibold text-gray-700">OP {{ $op['numero_op'] }}</span>
                                <span class="text-xs text-gray-400">{{ $op['sku'] }}</span>
                                @if($op['divergente'] ?? false)
                                    <span class="text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded font-medium">divergente</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 truncate mt-0.5">{{ $op['descricao'] }}</p>
                        </div>

                        {{-- Quantidades --}}
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700">
                                {{ number_format($op['realizado'], 0, ',', '.') }}
                                @if($op['programado'])
                                    / {{ number_format($op['programado'], 0, ',', '.') }} un
                                @else
                                    un
                                @endif
                            </span>
                            @if($op['programado'])
                                <span class="text-xs text-gray-500">
                                    Faltam: <span class="font-medium text-gray-700">{{ number_format($op['faltam'] ?? 0, 0, ',', '.') }} un</span>
                                </span>
                            @endif
                        </div>

                        {{-- Barra de progresso --}}
                        <div class="mt-2 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-2 rounded-full transition-all"
                                 style="width: {{ min(100, $op['pct'] ?? 0) }}%; background-color: {{ $progressColor }}">
                            </div>
                        </div>
                        <div class="flex items-baseline justify-between mt-1 mb-2">
                            <span class="text-base font-bold text-gray-700">{{ $op['pct'] ?? 0 }}%
                                @if(($op['pct'] ?? 0) > 100)
                                    <span class="text-xs text-red-600 ml-1">— excesso de {{ number_format($op['realizado'] - $op['programado'], 0, ',', '.') }} un</span>
                                @endif
                            </span>
                            @if(($op['ritmo_cxh'] ?? 0) > 0)
                                <span class="text-xs text-gray-400">{{ number_format($op['ritmo_cxh'], 0, ',', '.') }} un/h</span>
                            @endif
                        </div>

                        {{-- Tempos: Ritmo | ETA --}}
                        <div class="grid grid-cols-2 text-xs mb-2" style="gap:6px;">
                            <div class="bg-gray-50 rounded-lg p-2" style="font-size:11px;">
                                <p class="text-gray-400 mb-0.5">Ritmo</p>
                                <p class="font-medium text-gray-600">{{ ($op['ritmo_cxh'] ?? 0) > 0 ? number_format($op['ritmo_cxh'], 0, ',', '.').' un/h' : '—' }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2" style="font-size:11px;">
                                <p class="text-gray-400 mb-0.5">ETA conclusão</p>
                                <p class="font-medium text-gray-600">{{ $op['eta_formatada'] ?? '—' }}</p>
                            </div>
                        </div>

                        {{-- Tempo parado hoje --}}
                        @if(($m['tempo_parado_hoje_min'] ?? 0) > 0)
                            @php $ph = $m['tempo_parado_hoje_min']; @endphp
                            <p class="text-xs text-amber-600 mb-2">
                                Parado hoje: {{ floor($ph/60) > 0 ? floor($ph/60).'h ' : '' }}{{ $ph%60 }}min
                            </p>
                        @endif

                        {{-- Chips OEE --}}
                        @if(($m['oee'] ?? null) !== null || ($m['disponibilidade'] ?? null) !== null)
                            @php
                                $oeeVal  = $m['oee'] ?? null;
                                $dispVal = $m['disponibilidade'] ?? null;
                                $perfVal = $m['performance'] ?? null;
                                $oeeBg   = $oeeVal === null ? '#e5e7eb' : ($oeeVal >= 85 ? '#dcfce7' : ($oeeVal >= 65 ? '#fef9c3' : '#fee2e2'));
                                $oeeFg   = $oeeVal === null ? '#6b7280' : ($oeeVal >= 85 ? '#166534' : ($oeeVal >= 65 ? '#854d0e' : '#991b1b'));
                            @endphp
                            <div class="flex flex-wrap gap-1.5 mb-1">
                                <span class="px-2 py-0.5 rounded text-xs font-medium" style="background:{{ $oeeBg }}; color:{{ $oeeFg }}">
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
                        <div class="mt-2 text-sm text-gray-400">
                            @if($m['status'] === 'Sem programação')
                                <p>Sem programação confirmada.</p>
                            @else
                                <p>Sem produção registrada nas últimas 24h.</p>
                            @endif
                        </div>
                    @endif

                    {{-- Histórico 7 dias --}}
                    <div class="border-t border-gray-100 pt-3 mt-3">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-semibold text-gray-500 uppercase">Histórico — últimos 7 dias</p>
                            <span class="text-xs text-gray-300">fonte: CODI eventos</span>
                        </div>
                        <div class="flex gap-4 text-xs mb-2">
                            <span><span class="font-semibold text-gray-700">{{ number_format($m['historico_7d']['producao_total'], 0, ',', '.') }}</span> un produzidas</span>
                            <span><span class="font-semibold {{ ($m['historico_7d']['disponibilidade_media'] ?? 0) >= 80 ? 'text-green-600' : (($m['historico_7d']['disponibilidade_media'] ?? 0) >= 60 ? 'text-amber-600' : 'text-red-500') }}">{{ $m['historico_7d']['disponibilidade_media'] ?? '—' }}%</span> disponibilidade</span>
                            <span><span class="font-semibold text-gray-700">{{ $m['historico_7d']['horas_paradas'] }}h</span> paradas</span>
                        </div>
                        @if($m['historico_7d']['tendencia']['direcao'] !== 'stable')
                            <p class="text-xs {{ $m['historico_7d']['tendencia']['direcao'] === 'up' ? 'text-green-600' : 'text-red-600' }} mb-2">
                                {{ $m['historico_7d']['tendencia']['direcao'] === 'up' ? '↑ Ritmo acelerando' : '↓ Ritmo desacelerando' }}
                                — semana passada {{ $m['historico_7d']['tendencia']['ritmo_anterior'] ?? '—' }} un/h · esta semana {{ $m['historico_7d']['tendencia']['ritmo_atual'] ?? '—' }} un/h
                            </p>
                        @endif
                        <div class="flex items-end gap-1 h-10">
                            @foreach($m['historico_7d']['dados_grafico'] as $dia)
                                @php $alturaProd = $dia['producao_qty'] > 0 ? min(100, ($dia['producao_qty'] / max(1, max(array_column($m['historico_7d']['dados_grafico'], 'producao_qty')))) * 100) : 0; @endphp
                                <div class="flex-1 flex flex-col items-center gap-0.5">
                                    <div class="w-full rounded-sm" style="height: {{ max(2, $alturaProd * 0.38) }}px; background: {{ $dia['paradas_min'] > 30 ? '#fca5a5' : '#4ade80' }};"></div>
                                    <span class="text-[9px] text-gray-300">{{ \Carbon\Carbon::parse($dia['data'])->format('d/m') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-xl p-10 text-center text-gray-400 text-sm">
            Nenhuma máquina cadastrada com recurso CODI.
        </div>
    @endif

</div>
