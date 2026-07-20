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

    {{-- ── Barra de filtros ────────────────────────────────────────────────── --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
            <input type="text"
                   wire:model.live.debounce.300ms="busca"
                   placeholder="Buscar por Nº OP, SKU ou descrição…"
                   class="flex-1 min-w-48 text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500">

            <select wire:model.live="filtroStatus"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
                <option value="">Todos os status</option>
                <option value="pendente">Pendente</option>
                <option value="programada">Programada</option>
                <option value="em_producao">Em Produção</option>
                <option value="concluida">Concluída</option>
                <option value="cancelada">Cancelada</option>
            </select>

            <select wire:model.live="filtroLinhaId"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
                <option value="">Todas as linhas</option>
                @foreach($linhas as $linha)
                    <option value="{{ $linha->id }}">{{ $linha->codigo }} — {{ $linha->nome }}</option>
                @endforeach
            </select>

            <button wire:click="abrirFormNova"
                    class="bg-gray-800 hover:bg-gray-900 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                + Nova Ordem
            </button>
        </div>

        {{-- ── Modal de criação ───────────────────────────────────────────────── --}}
        @if($mostrarFormNova)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Nova Ordem de Produção</h3>
                        <button wire:click="fecharFormNova"
                                class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                    </div>

                    <form wire:submit="salvarNova" class="px-6 py-4 space-y-3">

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Nº OP</label>
                                <input type="text" wire:model="novoNumeroOp" placeholder="Gerado automaticamente"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500">
                                @error('numero_op') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">SKU *</label>
                                <input type="text" wire:model="novoSku" placeholder="EX-1L"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 uppercase">
                                @error('sku') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Descrição do Produto *</label>
                            <input type="text" wire:model="novaDescricao" placeholder="Produto exemplo 1L"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500">
                            @error('descricao_produto') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Quantidade *</label>
                                <input type="number" wire:model="novaQuantidade" min="0.001" step="0.001" placeholder="0"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500">
                                @error('quantidade') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Linha</label>
                                <select wire:model="novoLinhaId"
                                        class="w-full text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">— sem linha —</option>
                                    @foreach($linhas as $linha)
                                        <option value="{{ $linha->id }}">{{ $linha->codigo }} — {{ $linha->nome }}</option>
                                    @endforeach
                                </select>
                                @error('linha_id') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Data de Entrega</label>
                                <input type="date" wire:model="novaDataEntrega"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500">
                                @error('data_entrega') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Prioridade (1–10)</label>
                                <input type="number" wire:model="novaPrioridade" min="1" max="10"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500">
                                @error('prioridade') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Observações</label>
                            <textarea wire:model="novasObservacoes" rows="2" placeholder="Informações adicionais…"
                                      class="w-full text-sm border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                            @error('observacoes') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" wire:click="fecharFormNova"
                                    class="text-sm text-gray-500 hover:text-gray-800 px-4 py-2 transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
                                    wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed">
                                <span wire:loading.remove wire:target="salvarNova">Salvar</span>
                                <span wire:loading wire:target="salvarNova">Salvando…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- ── Tabela ──────────────────────────────────────────────────────────── --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nº OP</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qtd</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Entrega</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Prioridade</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Linha</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($ordens as $ordem)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $ordem->numero_op ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-mono font-semibold text-blue-700">{{ $ordem->sku }}</td>
                            <td class="px-4 py-2.5 text-gray-700">{{ $ordem->descricao_produto }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-700">
                                {{ number_format((float) $ordem->quantidade, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2.5 text-center text-gray-600">
                                {{ $ordem->data_entrega?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-center text-gray-600">
                                {{ $ordem->prioridade ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">
                                {{ $ordem->linha?->codigo ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @php
                                    $badgeClasses = match($ordem->status) {
                                        'pendente'    => 'bg-gray-100 text-gray-700',
                                        'programada'  => 'bg-blue-100 text-blue-700',
                                        'em_producao' => 'bg-yellow-100 text-yellow-700',
                                        'concluida'   => 'bg-green-100 text-green-700',
                                        'cancelada'   => 'bg-red-100 text-red-700',
                                        default       => 'bg-gray-100 text-gray-700',
                                    };
                                    $badgeLabel = match($ordem->status) {
                                        'pendente'    => 'Pendente',
                                        'programada'  => 'Programada',
                                        'em_producao' => 'Em Produção',
                                        'concluida'   => 'Concluída',
                                        'cancelada'   => 'Cancelada',
                                        default       => $ordem->status,
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $badgeClasses }}">
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @if(!in_array($ordem->status, ['concluida', 'cancelada']))
                                    <button wire:confirm="Confirmar cancelamento desta ordem?"
                                            wire:click="cancelarOrdem({{ $ordem->id }})"
                                            class="text-xs text-red-600 hover:underline hover:text-red-800 transition-colors">
                                        Cancelar
                                    </button>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-400">
                                Nenhuma ordem encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($ordens->hasPages())
            <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
                {{ $ordens->links() }}
            </div>
        @endif

    </div>

</div>
