<div wire:poll.30s="carregarDados">

    <style>
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
    </style>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">
                @if($totalAtivas > 0)
                    <span style="animation:blink 1s infinite;display:inline-block">⚠</span>
                @endif
                Divergências de OP
            </h1>
            <p class="text-sm text-gray-500 mt-1">OPs rodando no CODI diferentes do planejado no PCP</p>
        </div>
        <div class="text-xs text-gray-400">
            Atualiza a cada 30s
        </div>
    </div>

    {{-- Ativas --}}
    @php $divergenciasEnvase = collect($divergencias)->filter(fn($d) => ($d['modulo'] ?? 'envase') === 'envase'); @endphp
    @if($divergenciasEnvase->count() > 0)
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-sm font-medium text-red-600 uppercase tracking-wide">● {{ $divergenciasEnvase->count() }} Ativas agora (Envase)</span>
            </div>
            <div class="bg-white border border-red-200 rounded-xl overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-red-50 border-b border-red-100">
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Linha</th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-green-600 uppercase">OP Esperada (PCP)</th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-green-600 uppercase">Produto Esperado</th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-red-600 uppercase">OP Rodando (CODI)</th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-red-600 uppercase">Produto Rodando</th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Detectado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($divergenciasEnvase as $d)
                        <tr class="border-b border-gray-100 hover:bg-red-50 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-800">{{ $d['linha_nome'] }}</td>
                            <td class="py-3 px-4 text-green-700 font-medium">{{ $d['op_esperada'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-green-600 text-xs">{{ $d['prod_esperada'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-red-700 font-medium">{{ $d['op_rodando'] }}</td>
                            <td class="py-3 px-4 text-red-600 text-xs">{{ $d['prod_rodando'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-400 text-xs">{{ \Carbon\Carbon::parse($d['detectado_em'])->format('d/m H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Divergências de Excesso de Produção — Sopro --}}
    @php $divergenciasSopro = collect($divergencias)->where('modulo', 'sopro')->where('tipo', 'divergencia_codi_cigam'); @endphp
    @if($divergenciasSopro->count() > 0)
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-sm font-medium text-amber-600 uppercase tracking-wide">⚠ {{ $divergenciasSopro->count() }} Divergência(s) CODI vs CIGAM — Sopro</span>
            </div>
            <div class="bg-white border border-amber-200 rounded-xl overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-amber-50 border-b border-amber-100">
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Máquina</th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">OP</th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Frasco</th>
                            <th class="text-center py-3 px-4 text-xs font-medium text-gray-500 uppercase">Turno</th>
                            <th class="text-right py-3 px-4 text-xs font-medium text-green-600 uppercase">Previsto</th>
                            <th class="text-right py-3 px-4 text-xs font-medium text-blue-600 uppercase">Realizado CODI</th>
                            <th class="text-right py-3 px-4 text-xs font-medium text-purple-600 uppercase">Realizado CIGAM</th>
                            <th class="text-right py-3 px-4 text-xs font-medium text-red-600 uppercase">Diferença</th>
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 uppercase">Detectado em</th>
                            <th class="text-center py-3 px-4 text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($divergenciasSopro as $d)
                        <tr class="border-b border-gray-100 hover:bg-amber-50 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-800">{{ $d['linha_nome'] }}</td>
                            <td class="py-3 px-4 text-gray-600 font-mono text-xs">{{ $d['op_rodando'] }}</td>
                            <td class="py-3 px-4 text-gray-600 text-xs">{{ $d['prod_esperada'] }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-medium">
                                    {{ $d['turno_predominante'] ?? '—' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-green-700 font-medium tabular-nums">
                                {{ number_format($d['quantidade_prevista'] ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right text-blue-700 font-medium tabular-nums">
                                {{ number_format($d['quantidade_realizada'] ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right text-purple-700 font-medium tabular-nums">
                                {{ $d['quantidade_realizada_cigam'] ? number_format($d['quantidade_realizada_cigam'], 0, ',', '.') : '—' }}
                            </td>
                            @php
                                $dif = $d['quantidade_excesso'] ?? 0;
                                $difColor = $dif > 0 ? 'text-red-600' : ($dif < 0 ? 'text-blue-600' : 'text-gray-400');
                                $difPrefix = $dif > 0 ? '+' : '';
                            @endphp
                            <td class="py-3 px-4 text-right font-medium tabular-nums {{ $difColor }}">
                                {{ $difPrefix }}{{ number_format($dif, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-gray-400 text-xs whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($d['detectado_em'])->format('d/m H:i') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button wire:click="marcarCorrigido({{ $d['id'] }})"
                                        wire:confirm="Confirma que já verificou este apontamento no CODI?"
                                        class="text-xs font-semibold px-4 py-2 bg-green-600 text-white rounded-lg shadow-sm hover:bg-green-700 active:scale-95 transition-all">
                                    ✓ Corrigido
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($divergenciasEnvase->count() === 0 && $divergenciasSopro->count() === 0)
        <div class="mb-8 bg-green-50 border border-green-200 rounded-xl p-6 text-center">
            <div class="text-green-600 text-2xl mb-2">✓</div>
            <div class="text-green-700 font-medium">Nenhuma divergência ativa</div>
            <div class="text-green-500 text-sm mt-1">Todas as linhas estão rodando as OPs programadas</div>
        </div>
    @endif

    {{-- Histórico --}}
    @if(count($divergenciasHist) > 0)
        <div>
            <div class="text-sm font-medium text-gray-400 uppercase tracking-wide mb-3">Histórico recente (últimas 20)</div>
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left py-2 px-4 text-xs font-medium text-gray-500 uppercase">Linha</th>
                            <th class="text-left py-2 px-4 text-xs font-medium text-gray-500 uppercase">OP Esperada</th>
                            <th class="text-left py-2 px-4 text-xs font-medium text-gray-500 uppercase">OP Rodou</th>
                            <th class="text-left py-2 px-4 text-xs font-medium text-gray-500 uppercase">Detectado</th>
                            <th class="text-left py-2 px-4 text-xs font-medium text-gray-500 uppercase">Resolvido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($divergenciasHist as $d)
                        <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                            <td class="py-2 px-4 text-gray-600">{{ $d['linha_nome'] }}</td>
                            <td class="py-2 px-4 text-gray-500 text-xs">{{ $d['op_esperada'] ?? '—' }}</td>
                            <td class="py-2 px-4 text-gray-500 text-xs">{{ $d['op_rodando'] }}</td>
                            <td class="py-2 px-4 text-gray-400 text-xs">{{ \Carbon\Carbon::parse($d['detectado_em'])->format('d/m H:i') }}</td>
                            <td class="py-2 px-4 text-green-500 text-xs">{{ \Carbon\Carbon::parse($d['resolvida_em'])->format('d/m H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
