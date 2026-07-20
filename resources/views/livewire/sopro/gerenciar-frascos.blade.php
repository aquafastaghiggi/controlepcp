<div class="p-6">

    {{-- Cabeçalho --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Frascos</h1>
            <p class="text-sm text-gray-500 mt-1">{{ count($frascos) }} frasco(s) exibido(s)</p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Busca --}}
            <input wire:model.live.debounce.300ms="busca" type="text" placeholder="Buscar SKU ou descrição…"
                   class="text-sm border border-gray-300 rounded-lg px-3 py-2 w-56 focus:outline-none focus:ring-2 focus:ring-blue-500">
            {{-- Filtro material --}}
            <select wire:model.live="filtroMaterial"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Todos os materiais</option>
                <option value="PEAD">PEAD</option>
                <option value="PET">PET</option>
            </select>
        </div>
    </div>

    {{-- Mensagem --}}
    @if($mensagem)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm font-medium
            {{ $tipoMensagem === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
            {{ $mensagem }}
        </div>
    @endif

    {{-- Tabela --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">SKU</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Descrição</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-20">Mat.</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Taxa/h</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">Máquina</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($frascos as $f)
                    @if($editandoId === $f['id'])
                        {{-- Linha em edição --}}
                        <tr class="bg-amber-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $f['sku'] }}</td>
                            <td class="px-4 py-3 text-gray-700 text-xs leading-tight">{{ $f['descricao'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs px-2 py-0.5 rounded font-medium
                                    {{ $f['material'] === 'PEAD' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $f['material'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <input wire:model="editTaxa" type="number" step="0.01" min="0" placeholder="0"
                                       class="w-full text-sm text-right border border-amber-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
                            </td>
                            <td class="px-4 py-3">
                                <select wire:model="editMaquinaId"
                                        class="w-full text-sm border border-amber-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
                                    <option value="">— nenhuma —</option>
                                    @foreach($maquinas as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['codigo'] }} · {{ $m['nome'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="salvarEdicao"
                                            class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors">
                                        Salvar
                                    </button>
                                    <button wire:click="cancelarEdicao"
                                            class="px-3 py-1.5 bg-white hover:bg-gray-50 text-gray-600 text-xs border border-gray-300 rounded-lg transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @else
                        <tr class="{{ $f['ativo'] ? 'hover:bg-gray-50' : 'bg-gray-50 opacity-50' }} transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $f['sku'] }}</td>
                            <td class="px-4 py-3 text-gray-700 text-xs leading-tight">{{ $f['descricao'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs px-2 py-0.5 rounded font-medium
                                    {{ $f['material'] === 'PEAD' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $f['material'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-sm
                                {{ $f['taxa_por_hora'] ? 'text-gray-800 font-medium' : 'text-gray-300' }}">
                                {{ $f['taxa_por_hora'] ? number_format($f['taxa_por_hora'], 0, ',', '.') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm
                                {{ $f['maquina_codigo'] ? 'text-gray-700' : 'text-gray-300' }}">
                                @if($f['maquina_codigo'])
                                    <span class="font-mono font-semibold text-xs">{{ $f['maquina_codigo'] }}</span>
                                    <span class="text-gray-400 text-xs"> · {{ $f['maquina_nome'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="editar({{ $f['id'] }})"
                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors">
                                    Editar
                                </button>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">
                            Nenhum frasco encontrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Legenda --}}
    <div class="mt-4 flex items-center gap-6 text-xs text-gray-400">
        <span>{{ collect($frascos)->where('taxa_por_hora', null)->count() }} sem taxa</span>
        <span>{{ collect($frascos)->where('maquina_id', null)->count() }} sem máquina</span>
        <span class="ml-auto">Taxa/h = frascos por hora</span>
    </div>

</div>
