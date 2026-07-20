<div>
    {{-- Filtros --}}
    <div class="bg-white border border-gray-200 rounded-xl p-4 mb-5 shadow-sm flex flex-wrap gap-3 items-end">

        <div>
            <label class="block text-xs text-gray-500 mb-1">Linha</label>
            <select wire:model.live="filtroLinhaId"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value="0">Todas</option>
                @foreach(\App\Models\Linha::where('ativo', true)->get() as $l)
                    <option value="{{ $l->id }}">{{ $l->nome }}</option>
                @endforeach
            </select>
        </div>

    </div>

    {{-- Tabela --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        @if($programacoes->isEmpty())
            <div class="py-16 text-center text-gray-400 text-sm">
                Nenhuma programação encontrada com os filtros aplicados.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="w-8 px-2 py-3"></th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Linha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Início</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">Itens</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Calculada em</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">Origem</th>
                            @foreach($turnosFixos as $turnoFixo)
                                <th class="px-2 py-3 text-center text-xs font-medium {{ $turnoFixo['noturno'] ? 'text-amber-700' : 'text-gray-500' }}">
                                    {{ $turnoFixo['noturno'] ? '🌙 ' : '' }}{{ $turnoFixo['label'] }}
                                    <span class="block font-normal normal-case text-[11px] {{ $turnoFixo['noturno'] ? 'text-amber-600' : 'text-gray-400' }}">
                                        {{ $turnoFixo['inicio'] }}–{{ $turnoFixo['fim'] }}
                                    </span>
                                </th>
                            @endforeach
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($programacoes as $prog)
                            @php
                                $turnoNoturno = $turnosNoturnos[$prog->id] ?? null;
                                $estaExpandida = in_array($prog->id, $linhasExpandidas);
                            @endphp
                            <tr wire:key="prog-{{ $prog->id }}" class="transition-colors {{ $turnoNoturno ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-gray-50' }}">
                                <td class="px-2 py-3 text-center">
                                    <button wire:click="toggleExpandir({{ $prog->id }})"
                                            type="button"
                                            class="text-gray-400 hover:text-gray-700 inline-flex items-center justify-center w-6 h-6 rounded transition-colors">
                                        {{-- style inline (não classe utilitária) para não depender do build do Tailwind já ter a classe compilada --}}
                                        <svg class="w-4 h-4 transition-transform" style="{{ $estaExpandida ? 'transform: rotate(90deg);' : '' }}"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-800">
                                    {{ $prog->linha->nome ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs tabular-nums">
                                    {{ $prog->data_inicio_planejada?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{-- P15: count de blocos 'producao' no resultado, não total de itens importados --}}
                                    {{ $prog->itens_sequenciados_count }}
                                </td>
                                <td class="px-4 py-3">
                                    @php $ts = $prog->calculado_em ?? $prog->created_at; @endphp
                                    <span class="text-sm text-gray-700">
                                        {{ \Carbon\Carbon::parse($ts)->locale('pt_BR')->isoFormat('ddd DD/MM/YYYY') }}
                                    </span>
                                    <span class="text-xs text-gray-400 block">
                                        {{ \Carbon\Carbon::parse($ts)->format('H:i') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $badgeStatus = match($prog->status) {
                                            'rascunho'   => 'bg-gray-100 text-gray-600',
                                            'calculada'  => 'bg-blue-100 text-blue-700',
                                            'confirmada' => 'bg-green-100 text-green-700',
                                            'cancelada'  => 'bg-red-100 text-red-600',
                                            'arquivada'  => 'bg-amber-100 text-amber-700',
                                            default      => 'bg-gray-100 text-gray-500',
                                        };
                                    @endphp
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeStatus }}">
                                        {{ ucfirst($prog->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $badgeOrigem = match($prog->origem) {
                                            'manual'  => 'bg-gray-100 text-gray-500',
                                            'excel'   => 'bg-green-100 text-green-600',
                                            'api_erp' => 'bg-purple-100 text-purple-700',
                                            default   => 'bg-gray-100 text-gray-400',
                                        };
                                    @endphp
                                    <div class="flex items-center justify-center gap-1 flex-wrap">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeOrigem }}">
                                            {{ match($prog->origem) {
                                                'manual'  => 'Manual',
                                                'excel'   => 'Excel',
                                                'api_erp' => 'ERP',
                                                default   => $prog->origem,
                                            } }}
                                        </span>
                                        @if($prog->otimizado)
                                            <span class="inline-block px-1.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700"
                                                  title="Sequência otimizada pelo algoritmo">
                                                ⚡ Otimizado
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                @php $grade = $gradeTurnos[$prog->id] ?? []; @endphp
                                @foreach($turnosFixos as $indice => $turnoFixo)
                                    @php $turnoAtivo = $grade[$indice] ?? false; @endphp
                                    <td class="px-2 py-3 text-center">
                                        @if($turnoAtivo)
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold {{ $turnoFixo['noturno'] ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                                                ✓
                                            </span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        {{-- P11: resultados_count vem de withCount(), sem carregar a coleção --}}
                                        @if($prog->resultados_count > 0)
                                            <a href="{{ route('programacoes') }}?id={{ $prog->id }}"
                                               class="text-xs text-blue-600 hover:underline">
                                                Ver Resultado
                                            </a>
                                            <a href="{{ route('programacoes.imprimir', $prog->id) }}"
                                               target="_blank"
                                               class="text-xs text-gray-500 hover:text-gray-700 hover:underline">
                                                🖨️ Imprimir
                                            </a>
                                        @endif
                                        @if($prog->estaEditavel())
                                            {{-- P13: incluir ?id= para carregar a programação no formulário --}}
                                            <a href="{{ route('programacoes') }}?id={{ $prog->id }}"
                                               class="text-xs text-gray-500 hover:text-gray-800 hover:underline">
                                                Recalcular
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @if($estaExpandida)
                                @php $detalhe = $detalhesExpandidos[$prog->id] ?? null; @endphp
                                <tr wire:key="prog-expand-{{ $prog->id }}">
                                    <td colspan="{{ 8 + count($turnosFixos) }}" class="p-0 border-b border-gray-100">
                                        <div class="bg-gray-50 px-6 py-4">
                                            @if($detalhe && ! empty($detalhe['dias_disponiveis']))
                                                <div class="mb-3 flex items-center gap-2">
                                                    <label class="text-xs font-medium text-gray-500">Data</label>
                                                    {{-- wire:change explícito em vez de wire:model.live: mais confiável que o
                                                         binding automático em array com chave dinâmica (programacao_id). --}}
                                                    <select wire:change="selecionarData({{ $prog->id }}, $event.target.value)"
                                                            class="text-xs border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-blue-500">
                                                        @foreach($detalhe['dias_disponiveis'] as $dia)
                                                            <option value="{{ $dia }}" @selected($dia === $detalhe['data_selecionada'])>
                                                                {{ \Carbon\Carbon::parse($dia)->format('d/m') }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-xs">
                                                        <thead>
                                                            <tr class="text-gray-400 border-b border-gray-200">
                                                                <th class="text-left pb-2 pr-3 font-medium">OP</th>
                                                                <th class="text-left pb-2 pr-3 font-medium">SKU</th>
                                                                <th class="text-left pb-2 pr-3 font-medium">Produto</th>
                                                                <th class="text-right pb-2 pr-3 font-medium" style="padding: 6px 16px 6px 12px;">Qtd</th>
                                                                <th class="text-left pb-2 pr-3 font-medium" style="padding: 6px 16px 6px 12px;">Início prev.</th>
                                                                <th class="text-left pb-2 pr-3 font-medium">Fim prev.</th>
                                                                <th class="text-center pb-2 pr-3 font-medium">Turnos</th>
                                                                <th class="text-right pb-2 font-medium">Prev. cx</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @forelse($detalhe['ops'] as $op)
                                                                <tr class="border-b border-gray-100">
                                                                    <td class="py-1.5 pr-3 text-gray-600">{{ $op['numero_op'] ?? '—' }}</td>
                                                                    <td class="py-1.5 pr-3 text-gray-500">{{ $op['sku'] }}</td>
                                                                    <td class="py-1.5 pr-3 text-gray-700">{{ $op['descricao_produto'] }}</td>
                                                                    <td class="py-1.5 pr-3 text-right text-gray-700" style="padding: 6px 16px 6px 12px;">
                                                                        {{ number_format($op['quantidade'], 0, ',', '.') }}
                                                                    </td>
                                                                    <td class="py-1.5 pr-3 text-gray-600 whitespace-nowrap" style="padding: 6px 16px 6px 12px;">
                                                                        {{ \Carbon\Carbon::parse($op['inicio_previsto'])->format('d/m H:i') }}
                                                                    </td>
                                                                    <td class="py-1.5 pr-3 text-gray-600 whitespace-nowrap">
                                                                        {{ \Carbon\Carbon::parse($op['fim_previsto'])->format('d/m H:i') }}
                                                                    </td>
                                                                    <td class="py-1.5 pr-3">
                                                                        <div class="flex items-center justify-center gap-1">
                                                                            @forelse($op['turnos'] as $t)
                                                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $t['noturno'] ? 'bg-amber-100 text-amber-700' : 'bg-gray-200 text-gray-700' }}">
                                                                                    {{ $t['label'] }}
                                                                                </span>
                                                                            @empty
                                                                                <span class="text-gray-300">—</span>
                                                                            @endforelse
                                                                        </div>
                                                                    </td>
                                                                    <td class="py-1.5 text-right text-gray-800 font-medium">
                                                                        {{ number_format($op['prev_cx'], 0, ',', '.') }}
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="8" class="py-3 text-center text-gray-400">
                                                                        Nenhuma OP prevista para esta data.
                                                                    </td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                        <tfoot>
                                                            <tr>
                                                                <td colspan="7" class="pt-2 text-right font-semibold text-gray-600">
                                                                    Total do dia
                                                                </td>
                                                                <td class="pt-2 text-right font-bold text-gray-900">
                                                                    {{ number_format($detalhe['total_prev_cx'], 0, ',', '.') }}
                                                                </td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-400 py-1">
                                                    Nenhum dia com OPs previstas encontrado para esta programação.
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-300">
                            <td colspan="{{ 7 + count($turnosFixos) }}" class="px-4 py-3 text-right font-semibold text-gray-700">
                                Produção estimada do dia (Colemar)
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900 text-base">
                                {{ number_format($totalEstimadoHoje, 0, ',', '.') }}
                            </td>
                        </tr>
                        @if($totalGeralPrevCx > 0)
                            <tr class="bg-gray-50 border-t border-gray-200">
                                <td colspan="{{ 7 + count($turnosFixos) }}" class="px-4 py-3 text-right font-semibold text-gray-600">
                                    Total geral previsto (linhas expandidas)
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900">
                                    {{ number_format($totalGeralPrevCx, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $programacoes->links() }}
            </div>
        @endif
    </div>

    {{-- ── Histórico de Programações Arquivadas ── --}}
    @if(count($historico) > 0)
    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
            Histórico de Programações
        </h2>

        @foreach($historico as $linhaId => $versoes)
        <div class="mb-4">
            <p class="text-xs font-semibold text-gray-400 uppercase mb-2">
                {{ $versoes[0]['linha']['nome'] ?? 'Linha '.$linhaId }}
            </p>

            @foreach($versoes as $prog)
            <div class="bg-white border border-gray-200 rounded-lg mb-2 overflow-hidden">

                {{-- Linha clicável --}}
                <div class="flex items-center px-4 py-3 hover:bg-gray-50 transition-colors">
                    <button
                        wire:click="toggleHistorico({{ $prog['id'] }})"
                        class="flex-1 flex items-center justify-between text-left">

                        <div class="flex items-center gap-4">
                            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded font-medium">
                                arquivada
                            </span>
                            <span class="text-sm text-gray-700 font-medium">
                                {{ \Carbon\Carbon::parse($prog['arquivada_em'])->format('d/m/Y H:i') }}
                            </span>
                            <span class="text-xs text-gray-400">
                                {{ count($prog['itens']) }} OPs
                                &middot;
                                {{ number_format(collect($prog['itens'])->sum('quantidade'), 0, ',', '.') }} cx
                            </span>
                        </div>

                        <svg class="w-4 h-4 text-gray-400 transition-transform {{ in_array($prog['id'], $historicoExpandido) ? 'rotate-180' : '' }}"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <a href="{{ route('programacoes.imprimir', $prog['id']) }}"
                       target="_blank"
                       class="ml-4 text-xs text-gray-500 hover:text-gray-700 hover:underline whitespace-nowrap">
                        🖨️ Imprimir
                    </a>
                </div>

                {{-- OPs expandidas --}}
                @if(in_array($prog['id'], $historicoExpandido))
                <div class="border-t border-gray-100 px-4 py-3">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-gray-400 border-b border-gray-100">
                                <th class="text-left pb-2 font-medium">OP</th>
                                <th class="text-left pb-2 font-medium">SKU</th>
                                <th class="text-left pb-2 font-medium">Produto</th>
                                <th class="text-right pb-2 font-medium">Qtd</th>
                                <th class="text-right pb-2 font-medium">Seq.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($prog['itens'] as $item)
                            <tr class="border-b border-gray-50 hover:bg-gray-50">
                                <td class="py-1.5 text-gray-600">{{ $item['numero_op'] ?? '—' }}</td>
                                <td class="py-1.5 text-gray-500">{{ $item['sku'] }}</td>
                                <td class="py-1.5 text-gray-700">{{ $item['descricao_produto'] }}</td>
                                <td class="py-1.5 text-right text-gray-700 font-medium">
                                    {{ number_format($item['quantidade'], 0, ',', '.') }}
                                </td>
                                <td class="py-1.5 text-right text-gray-400">
                                    {{ $item['sequencia'] }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

            </div>
            @endforeach
        </div>
        @endforeach
    </div>
    @endif

</div>
