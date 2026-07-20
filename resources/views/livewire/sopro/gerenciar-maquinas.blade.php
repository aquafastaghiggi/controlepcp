<div class="p-6">

    {{-- Cabeçalho --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Máquinas de Sopro</h1>
            <p class="text-sm text-gray-500 mt-1">{{ count($maquinas) }} máquina(s) cadastrada(s)</p>
        </div>
        @if(!$formAberto)
            <button wire:click="abrirForm"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                + Nova Máquina
            </button>
        @endif
    </div>

    {{-- Mensagem --}}
    @if($mensagem)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm font-medium
            {{ $tipoMensagem === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
            {{ $mensagem }}
        </div>
    @endif

    {{-- Formulário nova máquina --}}
    @if($formAberto)
        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-blue-800 mb-4">Nova Máquina</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Código</label>
                    <input wire:model="novoCodigo" type="text" placeholder="ex: MAQ11"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                    <input wire:model="novoNome" type="text" placeholder="ex: Máquina 11"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button wire:click="salvar"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Salvar
                </button>
                <button wire:click="cancelarForm"
                        class="px-4 py-2 bg-white hover:bg-gray-50 text-gray-600 text-sm border border-gray-300 rounded-lg transition-colors">
                    Cancelar
                </button>
            </div>
        </div>
    @endif

    {{-- Tabela --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">Código</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Status</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($maquinas as $m)
                    @if($editandoId === $m['id'])
                        {{-- Linha em edição --}}
                        <tr class="bg-amber-50">
                            <td class="px-4 py-3">
                                <input wire:model="editCodigo" type="text"
                                       class="w-full text-sm border border-amber-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400 uppercase font-mono">
                            </td>
                            <td class="px-4 py-3">
                                <input wire:model="editNome" type="text"
                                       class="w-full text-sm border border-amber-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400">
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs text-gray-400">—</span>
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
                        <tr class="{{ $m['ativo'] ? 'hover:bg-gray-50' : 'bg-gray-50 opacity-60' }} transition-colors">
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-gray-800">{{ $m['codigo'] }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $m['nome'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleAtivo({{ $m['id'] }})"
                                        class="text-xs px-2.5 py-1 rounded-full font-medium transition-colors
                                               {{ $m['ativo'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                    {{ $m['ativo'] ? 'Ativa' : 'Inativa' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="editar({{ $m['id'] }})"
                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors">
                                    Editar
                                </button>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

</div>
