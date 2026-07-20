<div x-data
     x-on:abrir-modal-eventos.window="abrirModalEventos($event.detail.op, $event.detail.descricao, $event.detail.eventos)">

    {{-- ── Seção 1: Cards de métricas ── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Este mês</p>
            <p class="text-2xl font-bold text-gray-700">{{ $metricas['total_mes'] }}</p>
            <p class="text-xs text-gray-400 mt-1">programações criadas</p>
        </div>

        <div class="bg-white border border-green-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-green-500 uppercase tracking-wide mb-1">Confirmadas</p>
            <p class="text-2xl font-bold text-green-700">{{ $metricas['confirmadas'] }}</p>
            <p class="text-xs text-gray-400 mt-1">total no sistema</p>
        </div>

        <div class="bg-white border border-indigo-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-indigo-500 uppercase tracking-wide mb-1">Produtos (SKU)</p>
            <p class="text-2xl font-bold text-indigo-700">{{ $metricas['produtos_ativos'] }}</p>
            <p class="text-xs text-gray-400 mt-1">com taxa cadastrada</p>
        </div>

    </div>

    {{-- ── Seção 3: Linhas em produção agora ── --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">
                Linhas em Produção Agora
                <span x-data="{
                    segundos: 600,
                    init() {
                        // Calcula segundos restantes com base no último refresh salvo
                        const chave = 'pcp_dashboard_ultimo_refresh';
                        const agora = Math.floor(Date.now() / 1000);
                        const ultimo = parseInt(localStorage.getItem(chave) || '0');
                        const decorrido = agora - ultimo;

                        if (ultimo === 0 || decorrido >= 600) {
                            // Primeira vez ou ciclo já vencido — começa novo ciclo
                            localStorage.setItem(chave, agora);
                            this.segundos = 600;
                        } else {
                            // Continua de onde parou
                            this.segundos = 600 - decorrido;
                        }

                        setInterval(() => {
                            this.segundos--;
                            if (this.segundos <= 0) {
                                const agora = Math.floor(Date.now() / 1000);
                                localStorage.setItem(chave, agora);
                                this.segundos = 600;
                                $wire.call('sincronizarEAtualizar');
                            }
                        }, 1000);
                    },
                    formato() {
                        const m = Math.floor(this.segundos / 60);
                        const s = String(this.segundos % 60).padStart(2, '0');
                        return m + ':' + s;
                    }
                }"
                class="text-xs text-gray-400 ml-2 font-normal normal-case tracking-normal"
                x-text="'↻ ' + formato()">
                </span>
            </h3>
            <div class="flex gap-4 text-xs text-gray-400">
                <span>📡 Performance: {{ $ultimaSincCodiPerformance }}</span>
                <span>📡 Eventos: {{ $ultimaSincCodiEventos }}</span>
            </div>
        </div>

        @if(!empty($oeeTempoReal))
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($oeeTempoReal as $linha)
            <div class="bg-white rounded-xl border shadow-sm flex flex-col
                        {{ $linha['cor'] === 'green'  ? 'border-green-200' :
                           ($linha['cor'] === 'yellow' ? 'border-yellow-300' :
                           ($linha['cor'] === 'red'    ? 'border-red-200' : 'border-gray-100')) }}">

                {{-- Header do card --}}
                <div class="p-3 border-b border-gray-50 flex items-center justify-between">
                    <div>
                        <span class="font-bold text-gray-800 text-sm">{{ $linha['nome'] }}</span>
                        <span class="ml-2 text-xs
                            {{ $linha['cor'] === 'green'  ? 'text-green-600' :
                               ($linha['cor'] === 'yellow' ? 'text-yellow-600' :
                               ($linha['cor'] === 'red'    ? 'text-red-600' : 'text-gray-400')) }}">
                            {{ $linha['estado'] }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">{{ $linha['ops_finalizadas'] }}/{{ $linha['total_ops'] }}</span>
                        <span class="w-2.5 h-2.5 rounded-full
                            {{ $linha['cor'] === 'green'  ? 'bg-green-500' :
                               ($linha['cor'] === 'yellow' ? 'bg-yellow-400 animate-pulse' :
                               ($linha['cor'] === 'red'    ? 'bg-red-400' : 'bg-gray-300')) }}">
                        </span>
                    </div>
                </div>

                {{-- Lista de OPs com scroll --}}
                <div class="overflow-y-auto divide-y divide-gray-50" style="max-height: 280px;">
                    @foreach($linha['ops'] as $op)
                    <div class="px-3 py-2 flex items-start gap-2
                                {{ $op['status'] === 'em_andamento' ? 'bg-yellow-50' : '' }}">

                        {{-- Indicador de status --}}
                        <div class="mt-0.5 flex-shrink-0">
                            @if($op['status'] === 'finalizada')
                                <span class="text-green-500 text-xs" title="Finalizada — quantidade atingida">🟢</span>
                            @elseif($op['status'] === 'concluida')
                                <span class="text-green-400 text-xs" title="Concluída — CODI iniciou próxima OP">✅</span>
                            @elseif($op['status'] === 'em_andamento')
                                <span class="text-yellow-500 text-xs animate-pulse" title="Em andamento">🟡</span>
                            @else
                                <span class="text-gray-300 text-xs" title="Não iniciada">⚪</span>
                            @endif
                        </div>

                        {{-- Dados da OP --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-mono text-gray-500">{{ $op['numero_op'] }}</span>
                                    @if(in_array($op['status'], ['em_andamento', 'concluida', 'finalizada']))
                                    <button
                                        wire:click="abrirEventosOp('{{ $op['numero_op'] }}', '{{ addslashes($op['descricao']) }}')"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50"
                                        wire:target="abrirEventosOp"
                                        class="text-[10px] px-1.5 py-0.5 bg-blue-100 text-blue-600 rounded hover:bg-blue-200 transition font-medium cursor-pointer">
                                        <span wire:loading.remove wire:target="abrirEventosOp">Eventos</span>
                                        <span wire:loading wire:target="abrirEventosOp">...</span>
                                    </button>
                                    @endif
                                </div>
                                <span class="text-xs font-medium
                                    {{ $op['realizado'] > 0 && $op['programado'] > 0
                                        ? ($op['realizado'] >= $op['programado'] ? 'text-green-600' : 'text-red-600')
                                        : 'text-gray-300' }}">
                                    {{ $op['realizado'] > 0
                                        ? number_format($op['realizado'], 0, ',', '.') . '/' . number_format($op['programado'], 0, ',', '.')
                                        : number_format($op['programado'], 0, ',', '.') . ' cx' }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 truncate" title="{{ $op['descricao'] }}">
                                {{ $op['descricao'] }}
                            </div>

                            {{-- Barra de progresso --}}
                            @if($op['realizado'] > 0)
                            <div class="mt-1 h-1 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-1 rounded-full transition-all
                                    {{ in_array($op['status'], ['finalizada', 'concluida']) ? 'bg-green-500' : 'bg-yellow-400' }}"
                                    style="width: {{ $op['pct'] }}%">
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Rodapé --}}
                <div class="px-3 py-2 border-t border-gray-50 text-xs text-gray-400 flex justify-between">
                    <span>{{ $linha['linha'] }}</span>
                    <span>{{ $linha['ultimo_evento'] ? 'Último: ' . $linha['ultimo_evento'] : $linha['sincronizado'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-400 py-4">Nenhuma linha com programação confirmada.</p>
        @endif
    </div>

    {{-- ── Seção 4: Últimas programações + Atalhos ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Tabela últimas programações --}}
        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Últimas Programações</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-400 uppercase bg-gray-50 border-b border-gray-100">
                        <th class="px-5 py-2.5 text-left font-medium">Linha</th>
                        <th class="px-5 py-2.5 text-left font-medium">Início</th>
                        <th class="px-5 py-2.5 text-left font-medium">Ordens</th>
                        <th class="px-5 py-2.5 text-left font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ultimasProgramacoes as $prog)
                    <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 font-medium text-gray-800">{{ $prog['linha'] }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $prog['data_inicio_planejada'] ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $prog['total_ops'] }} OPs · {{ $prog['total_cx'] }} cx</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                Confirmada
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-5 py-6 text-center text-sm text-gray-400">Nenhuma programação encontrada.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Atalhos + Feriado --}}
        <div class="flex flex-col gap-4">

            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Ações Rápidas</h3>
                <div class="flex flex-col gap-2">
                    <a href="{{ route('programacoes') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm text-center transition-colors">
                        + Nova Programação
                    </a>
                    <a href="{{ route('historico') }}"
                       class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium px-4 py-2.5 rounded-lg text-sm text-center transition-colors">
                        Ver Histórico
                    </a>
                    <a href="{{ route('produtos') }}"
                       class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium px-4 py-2.5 rounded-lg text-sm text-center transition-colors">
                        Produtos
                    </a>
                    <a href="{{ route('calendario') }}"
                       class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium px-4 py-2.5 rounded-lg text-sm text-center transition-colors">
                        Calendário
                    </a>
                </div>
            </div>

            @if($proximoFeriado)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs text-amber-600 uppercase tracking-wide font-semibold mb-1">Próximo Feriado</p>
                <p class="text-sm font-medium text-amber-800">{{ $proximoFeriado['descricao'] }}</p>
                <p class="text-sm text-amber-700">{{ $proximoFeriado['data'] }}</p>
                @if($proximoFeriado['dias'] === 0)
                    <p class="text-xs text-amber-600 mt-1">Hoje</p>
                @elseif($proximoFeriado['dias'] === 1)
                    <p class="text-xs text-amber-600 mt-1">Amanhã</p>
                @else
                    <p class="text-xs text-amber-600 mt-1">Em {{ $proximoFeriado['dias'] }} dias</p>
                @endif
            </div>
            @endif

            {{-- Resumo do mês --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Resumo de {{ now()->translatedFormat('F') }}</h3>
                <div class="space-y-1.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Total</span>
                        <span class="font-medium text-gray-700">{{ $programacoesMes['total'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-600">Confirmadas</span>
                        <span class="font-medium text-green-700">{{ $programacoesMes['confirmadas'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-600">Calculadas</span>
                        <span class="font-medium text-blue-700">{{ $programacoesMes['calculadas'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Rascunhos</span>
                        <span class="font-medium text-gray-400">{{ $programacoesMes['rascunhos'] }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>


</div>
