<div>

    {{-- ── Seletor de Programação ── --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm mb-6">
        <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">
                Programação
            </label>
            <span class="text-xs text-gray-400">📡 {{ $ultimaSincCodi }}</span>
        </div>
        <div class="flex gap-2 mb-3">
            <button
                wire:click="setFiltroStatus('confirmada')"
                class="text-xs px-3 py-1 rounded-full border transition-colors
                       {{ $filtroStatus === 'confirmada'
                          ? 'bg-green-100 border-green-400 text-green-700 font-medium'
                          : 'bg-white border-gray-300 text-gray-500 hover:border-gray-400' }}">
                Confirmadas
            </button>
            <button
                wire:click="setFiltroStatus('arquivada')"
                class="text-xs px-3 py-1 rounded-full border transition-colors
                       {{ $filtroStatus === 'arquivada'
                          ? 'bg-amber-100 border-amber-400 text-amber-700 font-medium'
                          : 'bg-white border-gray-300 text-gray-500 hover:border-gray-400' }}">
                Arquivadas
            </button>
            <button
                wire:click="setFiltroStatus('todas')"
                class="text-xs px-3 py-1 rounded-full border transition-colors
                       {{ $filtroStatus === 'todas'
                          ? 'bg-blue-100 border-blue-400 text-blue-700 font-medium'
                          : 'bg-white border-gray-300 text-gray-500 hover:border-gray-400' }}">
                Todas
            </button>
        </div>
        <select wire:model.live="programacaoId"
                class="w-full max-w-md border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">— Selecione uma programação —</option>
            @foreach($programacoes as $prog)
                @php
                    $statusLabel = match($prog['status']) {
                        'confirmada' => 'Confirmada',
                        'arquivada'  => 'Arquivada',
                        'calculada'  => 'Calculada',
                        default      => ucfirst($prog['status']),
                    };
                @endphp
                <option value="{{ $prog['id'] }}">
                    {{ $prog['label'] }} — {{ $prog['linha_nome'] }} ({{ $statusLabel }}, {{ $prog['criada_em'] }})
                </option>
            @endforeach
        </select>
    </div>

    @if(!$programacaoId)
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-10 text-center text-gray-400">
            <p class="text-4xl mb-3">📈</p>
            <p class="text-sm">Selecione uma programação para visualizar os KPIs de desempenho.</p>
        </div>

    @elseif(empty($ops))
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center text-amber-700">
            <p class="text-3xl mb-3">⏳</p>
            <p class="text-sm font-medium">Nenhum dado de eficiência calculado para esta programação.</p>
            <p class="text-xs mt-1 text-amber-600">
                Execute <code class="bg-amber-100 px-1 rounded">php artisan codi:sincronizar --tipo=todos</code>
                e depois chame o <code class="bg-amber-100 px-1 rounded">EficienciaCalculator</code>.
            </p>
        </div>

    @else

    {{-- ── Cards de Resumo ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">OEE Médio</p>
            @if($resumo['oee_medio'] !== null)
                <p class="text-3xl font-bold {{ $resumo['oee_medio'] >= 75 ? 'text-green-600' : ($resumo['oee_medio'] >= 50 ? 'text-amber-500' : 'text-red-600') }}">
                    {{ $resumo['oee_medio'] }}%
                </p>
            @else
                <p class="text-2xl font-bold text-gray-300">—</p>
            @endif
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Eficiência Média</p>
            @if($resumo['eficiencia_media'] !== null)
                <p class="text-3xl font-bold {{ $resumo['eficiencia_media'] >= 85 ? 'text-green-600' : ($resumo['eficiencia_media'] >= 70 ? 'text-amber-500' : 'text-red-600') }}">
                    {{ $resumo['eficiencia_media'] }}%
                </p>
            @else
                <p class="text-2xl font-bold text-gray-300">—</p>
            @endif
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">OPs no Prazo</p>
            <p class="text-3xl font-bold text-green-600">{{ $resumo['ops_ok'] }}</p>
            <p class="text-xs text-gray-400 mt-1">de {{ $resumo['ops_total'] }} OPs</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Situação</p>
            <div class="flex gap-2 mt-1 flex-wrap">
                @if($resumo['ops_critico'] > 0)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                        {{ $resumo['ops_critico'] }} crítico
                    </span>
                @endif
                @if($resumo['ops_aviso'] > 0)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">
                        {{ $resumo['ops_aviso'] }} aviso
                    </span>
                @endif
                @if($resumo['ops_pendente'] > 0)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                        {{ $resumo['ops_pendente'] }} pendente
                    </span>
                @endif
                @if($resumo['ops_critico'] === 0 && $resumo['ops_aviso'] === 0 && $resumo['ops_pendente'] === 0)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                        ✅ Tudo ok
                    </span>
                @endif
            </div>
        </div>

    </div>

    <div class="flex items-center gap-5 mb-3">
        <span class="flex items-center gap-1.5 text-xs text-gray-500">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-400"></span>
            Crítico — OEE &lt; 50% ou Efic. &lt; 70% ou atraso &gt; 5 dias
        </span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-400"></span>
            Aviso — OEE 50–74% ou Efic. 70–84% ou atraso 2–5 dias
        </span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-400"></span>
            OK — OEE ≥ 75% e Efic. ≥ 85% e atraso ≤ 2 dias
        </span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-gray-300"></span>
            Pendente — sem dados CODI ainda
        </span>
    </div>

    {{-- ── Tabela de OPs ── --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Ordens de Produção — Previsto × Realizado</h3>
            <span class="text-xs text-gray-400">{{ count($ops) }} OPs</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-400 uppercase bg-gray-50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left font-medium">OP</th>
                        <th class="px-4 py-3 text-left font-medium">SKU</th>
                        <th class="px-4 py-3 text-right font-medium">Qtd Prev.</th>
                        <th class="px-4 py-3 text-right font-medium">Qtd Real</th>
                        <th class="px-4 py-3 text-right font-medium">Desvio</th>
                        <th class="px-4 py-3 text-center font-medium">OEE</th>
                        <th class="px-4 py-3 text-center font-medium">Efic.</th>
                        <th class="px-4 py-3 text-center font-medium">Disp.</th>
                        <th class="px-4 py-3 text-center font-medium">Prazo</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Início real</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Prazo</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Término real</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-400 uppercase tracking-wide">T. produzido</th>
                        <th class="px-4 py-3 text-center font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ops as $op)
                    <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-700">{{ $op['numero_op'] }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $op['linha_nome'] ?? '—' }}</div>
                            @if($op['descricao_produto'])
                                <div class="text-xs text-gray-500 mt-0.5">{{ $op['descricao_produto'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $op['sku'] }}</td>

                        <td class="px-4 py-3 text-right text-gray-600">
                            {{ $op['quantidade_programada'] !== null ? number_format($op['quantidade_programada'], 0, ',', '.') : '—' }}
                        </td>

                        <td class="px-4 py-3 text-right font-medium {{ $op['quantidade_realizada'] !== null ? 'text-gray-700' : 'text-gray-300' }}">
                            {{ $op['quantidade_realizada'] !== null ? number_format($op['quantidade_realizada'], 0, ',', '.') : '—' }}
                        </td>

                        <td class="px-4 py-3 text-right text-xs font-medium
                            {{ $op['desvio_quantidade_pct'] === null ? 'text-gray-300' :
                               ($op['desvio_quantidade_pct'] >= 0 ? 'text-green-600' : 'text-red-600') }}">
                            @if($op['desvio_quantidade_pct'] !== null)
                                {{ $op['desvio_quantidade_pct'] >= 0 ? '+' : '' }}{{ $op['desvio_quantidade_pct'] }}%
                            @else
                                —
                            @endif
                        </td>

                        {{-- OEE --}}
                        <td class="px-4 py-3 text-center">
                            @if($op['oee'] !== null)
                                <span class="font-bold text-sm
                                    {{ $op['oee'] >= 75 ? 'text-green-600' : ($op['oee'] >= 50 ? 'text-amber-500' : 'text-red-600') }}">
                                    {{ $op['oee'] }}%
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Eficiência --}}
                        <td class="px-4 py-3 text-center text-xs text-gray-500">
                            {{ $op['eficiencia_quantidade'] !== null ? $op['eficiencia_quantidade'] . '%' : '—' }}
                        </td>

                        {{-- Disponibilidade --}}
                        <td class="px-4 py-3 text-center text-xs text-gray-500">
                            {{ $op['disponibilidade'] !== null ? $op['disponibilidade'] . '%' : '—' }}
                        </td>

                        {{-- Prazo --}}
                        <td class="px-4 py-3 text-center text-xs">
                            @if($op['desvio_prazo_dias'] === null)
                                <span class="text-gray-300">—</span>
                            @elseif($op['desvio_prazo_dias'] <= 0)
                                <span class="text-green-600 font-medium">No prazo</span>
                            @else
                                <span class="text-red-600 font-medium">+{{ $op['desvio_prazo_dias'] }}d</span>
                            @endif
                        </td>

                        {{-- Início real --}}
                        <td class="py-3 px-3 text-sm text-gray-600">
                            {{ $op['inicio_real'] ?? '—' }}
                        </td>

                        {{-- Prazo (fim previsto) --}}
                        <td class="py-3 px-3 text-sm text-gray-600">
                            {{ $op['fim_previsto'] ?? '—' }}
                        </td>

                        {{-- Término real --}}
                        <td class="py-3 px-3 text-sm text-gray-600">
                            {{ $op['fim_real'] ?? '—' }}
                        </td>

                        {{-- Tempo produzido (HH:MM) --}}
                        <td class="py-3 px-3 text-sm text-gray-600">
                            @if($op['tempo_prod_min'] !== null)
                                @php
                                    $h = intdiv($op['tempo_prod_min'], 60);
                                    $m = $op['tempo_prod_min'] % 60;
                                @endphp
                                {{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}
                            @else
                                —
                            @endif
                        </td>

                        {{-- Status badge --}}
                        <td class="px-4 py-3 text-center">
                            @if($op['status'] === 'ok')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Ok</span>
                            @elseif($op['status'] === 'aviso')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Aviso</span>
                            @elseif($op['status'] === 'critico')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">Crítico</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-400">Pendente</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    @endif

</div>
