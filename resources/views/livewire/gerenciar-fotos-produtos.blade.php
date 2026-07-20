<div class="p-6">

    {{-- Cabeçalho --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Fotos de Produtos</h1>
            <p class="text-sm text-gray-500 mt-0.5">Gerencie a associação de fotos aos produtos</p>
        </div>
        <div class="flex gap-2 text-sm">
            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full font-medium">{{ $totalComFoto }} com foto</span>
            <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-full font-medium">{{ $totalSemFoto }} sem foto</span>
        </div>
    </div>

    {{-- Mensagem --}}
    @if($mensagem)
        <div class="mb-4 px-4 py-2 rounded text-sm {{ $tipoMensagem === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
            {{ $mensagem }}
        </div>
    @endif

    {{-- Filtros --}}
    <div class="flex items-center gap-3 mb-5">
        <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
            <button wire:click="$set('filtro', 'pendentes')"
                    class="text-sm px-4 py-1.5 rounded-md transition-colors {{ $filtro === 'pendentes' ? 'bg-white text-gray-800 shadow-sm font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                Sem foto ({{ $totalSemFoto }})
            </button>
            <button wire:click="$set('filtro', 'com_foto')"
                    class="text-sm px-4 py-1.5 rounded-md transition-colors {{ $filtro === 'com_foto' ? 'bg-white text-gray-800 shadow-sm font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                Com foto ({{ $totalComFoto }})
            </button>
            <button wire:click="$set('filtro', 'todos')"
                    class="text-sm px-4 py-1.5 rounded-md transition-colors {{ $filtro === 'todos' ? 'bg-white text-gray-800 shadow-sm font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                Todos
            </button>
        </div>
        <input wire:model.live.debounce.300ms="busca"
               type="text"
               placeholder="Buscar por nome ou SKU..."
               class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-100">
    </div>

    {{-- Grid --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 items-start">
        @foreach($produtos as $p)
        @php $temFoto = !empty($p->foto); @endphp
        <div class="bg-white border {{ $temFoto ? 'border-green-200' : 'border-gray-200' }} rounded-xl overflow-hidden shadow-sm flex flex-col">

            {{-- Imagem --}}
            <div class="bg-gray-50 flex items-center justify-center" style="height: 160px;">
                @if($temFoto)
                    <img src="{{ asset('fotos-produtos/' . $p->foto) }}"
                         class="max-w-full object-contain"
                         style="max-height: 148px;"
                         alt="{{ $p->descricao }}">
                @elseif($p->sugestao ?? null)
                    <img src="{{ asset('fotos-produtos/' . $p->sugestao) }}"
                         class="max-w-full object-contain opacity-30"
                         style="max-height: 148px;"
                         alt="Sugestão">
                @else
                    <span class="text-4xl text-gray-200">📦</span>
                @endif
            </div>

            {{-- Info --}}
            <div class="p-3">
                <div class="text-xs text-gray-400 mb-0.5">{{ $p->sku }}</div>
                <div class="text-xs font-medium text-gray-700 leading-tight mb-3">{{ $p->descricao }}</div>

                @if($temFoto)
                    <div class="text-xs text-green-600 font-medium mb-1">✓ {{ $p->foto }}</div>
                    <button wire:click="rejeitar({{ $p->id }})"
                            class="text-xs text-gray-400 hover:text-red-500 underline">
                        Remover foto
                    </button>
                @else
                    {{-- Sugestão automática --}}
                    @if($p->sugestao)
                        <div class="mb-2 p-2 bg-blue-50 border border-blue-100 rounded-lg">
                            <div class="text-xs text-gray-500 mb-2 text-center break-words">{{ $p->sugestao }}</div>
                            <button wire:click="salvarFoto({{ $p->id }}, '{{ addslashes($p->sugestao) }}')"
                                    class="w-full text-xs bg-blue-600 hover:bg-blue-700 text-white py-1 px-2 rounded transition-colors">
                                ✓ Aprovar sugestão
                            </button>
                        </div>
                    @endif

                    {{-- Select manual --}}
                    <div x-data="{ arquivo: '' }">
                        <select x-model="arquivo"
                                class="w-full text-xs border border-gray-200 rounded px-1 py-1 text-gray-500 mb-1">
                            <option value="">{{ $p->sugestao ? 'Trocar foto...' : 'Selecionar foto...' }}</option>
                            @foreach($arquivos as $arq)
                                <option value="{{ $arq }}">{{ $arq }}</option>
                            @endforeach
                        </select>
                        <button x-show="arquivo"
                                x-on:click="$wire.salvarFoto({{ $p->id }}, arquivo); arquivo = ''"
                                class="w-full text-xs bg-green-600 hover:bg-green-700 text-white py-1 px-2 rounded transition-colors">
                            ✓ Salvar
                        </button>
                    </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    @if($produtos->isEmpty())
        <div class="text-center py-12 text-gray-400">
            <div class="text-4xl mb-2">🔍</div>
            <p class="text-sm">Nenhum produto encontrado</p>
        </div>
    @endif

</div>
