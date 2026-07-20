<div>

    {{-- Mensagem flash --}}
    @if($mensagem)
        <div @class([
            'mb-4 p-3 rounded-xl text-sm border',
            'bg-green-50 border-green-200 text-green-700' => $tipoMensagem === 'sucesso',
            'bg-red-50 border-red-200 text-red-700'       => $tipoMensagem === 'erro',
        ])>
            {{ $mensagem }}
        </div>
    @endif

    {{-- ── Lista de Produtos ─────────────────────────────────────────── --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Produtos Cadastrados</h3>
            <span class="text-xs text-gray-400">🔄 CIGAM: {{ $ultimaSincCigam }}</span>
            <div class="flex items-center gap-2">
                <a href="{{ route('produtos.exportar') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Exportar Excel
                </a>
                <button wire:click="$toggle('mostrarFormNovo')"
                        class="bg-gray-800 hover:bg-gray-900 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                    {{ $mostrarFormNovo ? '− Cancelar' : '+ Novo Produto' }}
                </button>
            </div>
        </div>

        @if($mostrarFormNovo)
            <div class="px-5 py-4 border-b border-dashed border-gray-200 bg-gray-50/50">
                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">SKU *</label>
                        <input type="text" wire:model="skuNovo" placeholder="NOVO1L"
                               class="w-28 text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 uppercase">
                        @error('skuNovo') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Descrição *</label>
                        <input type="text" wire:model="descricaoNova" placeholder="Produto novo 1L"
                               class="w-48 text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500">
                        @error('descricaoNova') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Taxa (cx/h) *</label>
                        <input type="number" wire:model="taxaNova" min="0.01" step="0.5"
                               class="w-28 text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500">
                        @error('taxaNova') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Família setup</label>
                        <input type="text" wire:model="referenciaSetupNova" placeholder="DESINFETANTE"
                               class="w-36 text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 uppercase">
                    </div>
                    <button wire:click="salvarNovoProduto"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Salvar
                    </button>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Linha</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Taxa (cx/h)</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ref. Setup</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Ativo</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $linhaAtual = null;
                        $cores = [
                            'LN01' => 'bg-blue-100 text-blue-700',
                            'LN02' => 'bg-green-100 text-green-700',
                            'LN03' => 'bg-purple-100 text-purple-700',
                            'LN04' => 'bg-orange-100 text-orange-700',
                            'LN05' => 'bg-pink-100 text-pink-700',
                            'LN06' => 'bg-teal-100 text-teal-700',
                            'LN07' => 'bg-yellow-100 text-yellow-700',
                            'LN10' => 'bg-indigo-100 text-indigo-700',
                        ];
                    @endphp

                    @foreach($produtos as $produto)
                        @php $linhaId = $produto->linha_id; @endphp

                        {{-- Separador visual entre grupos de linha --}}
                        @if($linhaId !== $linhaAtual && $linhaAtual !== null)
                            <tr><td colspan="7" class="h-px bg-blue-100 p-0"></td></tr>
                        @endif
                        @php $linhaAtual = $linhaId; @endphp

                        @if($editandoId === $produto->id)
                        {{-- ── Linha de edição inline ── --}}
                        <tr class="bg-blue-50 border-l-4 border-blue-400">
                            <td class="px-3 py-2">
                                <select wire:model="editandoLinhaId"
                                        class="w-full text-xs border border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500">
                                    <option value="">— sem linha —</option>
                                    @foreach($linhasDisponiveis as $linha)
                                        <option value="{{ $linha['id'] }}">{{ $linha['nome'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $produto->sku }}</td>
                            <td class="px-3 py-2 text-xs text-gray-600">{{ $produto->descricao }}</td>
                            <td class="px-3 py-2">
                                <input type="number" wire:model="editandoTaxa"
                                       class="w-24 text-right text-sm border border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500"
                                       placeholder="cx/hora" step="0.01" min="0">
                                @error('editandoTaxa')
                                    <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" wire:model="editandoRefSetup"
                                       class="w-full text-xs border border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 uppercase"
                                       placeholder="família de setup">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input type="checkbox" wire:model="editandoAtivo" class="rounded">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <div class="flex gap-1 justify-center">
                                    <button wire:click="salvarEdicao"
                                            class="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition-colors">
                                        Salvar
                                    </button>
                                    <button wire:click="cancelarEdicao"
                                            class="text-xs text-gray-500 hover:text-gray-800 px-2 py-1">
                                        Cancelar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        @else
                        {{-- ── Linha de visualização ── --}}
                        <tr @class([
                            'hover:bg-gray-50 transition-colors' => true,
                            'opacity-50'    => !$produto->ativo,
                            'bg-amber-50'   => is_null($produto->linha_id) || $produto->taxa_por_hora == 0,
                        ])>
                            <td class="px-4 py-2.5">
                                @if($produto->linha)
                                    @php $cor = $cores[$produto->linha->codigo] ?? 'bg-gray-100 text-gray-600'; @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $cor }}">
                                        {{ $produto->linha->codigo }}
                                    </span>
                                @else
                                    <span class="text-xs text-amber-600 font-medium">⚠ sem linha</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 font-mono font-semibold text-blue-700">{{ $produto->sku }}</td>
                            <td class="px-4 py-2.5 text-gray-700">{{ $produto->descricao }}</td>
                            <td class="px-4 py-2.5 text-right font-medium tabular-nums
                                       {{ $produto->taxa_por_hora == 0 ? 'text-amber-600' : '' }}">
                                {{ $produto->taxa_por_hora == 0 ? '⚠ 0' : number_format($produto->taxa_por_hora, 0) }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $produto->referencia_setup ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <button wire:click="toggleAtivo({{ $produto->id }})"
                                        title="{{ $produto->ativo ? 'Clique para inativar' : 'Clique para ativar' }}">
                                    <span @class([
                                        'inline-block w-2 h-2 rounded-full',
                                        'bg-green-500' => $produto->ativo,
                                        'bg-gray-300'  => !$produto->ativo,
                                    ])></span>
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <button wire:click="editar({{ $produto->id }})"
                                        class="text-xs text-blue-600 hover:underline hover:text-blue-800">
                                    Editar
                                </button>
                            </td>
                        </tr>
                        @endif

                    @endforeach
                </tbody>
            </table>
        </div>

        @if($produtos->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">
                {{ $produtos->links() }}
            </div>
        @endif

        <div class="px-5 py-2 border-t border-gray-100 flex gap-4 text-xs text-gray-400">
            <span class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded bg-amber-50 border border-amber-200 inline-block"></span>
                Produto sem linha ou taxa = requer configuração
            </span>
        </div>
    </div>

</div>
