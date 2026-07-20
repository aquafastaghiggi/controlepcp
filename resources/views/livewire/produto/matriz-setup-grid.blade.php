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

    {{-- Seletor de linha --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6 px-5 py-4 flex items-center gap-4 justify-between">
        <label class="text-sm font-medium text-gray-700">Linha de produção:</label>
        <select wire:change="selecionarLinha($event.target.value)"
                class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 bg-white">
            @foreach($linhas as $linha)
                <option value="{{ $linha->id }}" @selected($linha->id == $linhaIdSelecionada)>
                    {{ $linha->codigo }} — {{ $linha->nome }}
                </option>
            @endforeach
        </select>

        @if($linhaIdSelecionada)
            <button wire:click="exportarTxt"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors border border-gray-300">
                Exportar TXT
            </button>
        @endif

        <div class="flex items-center gap-4 text-xs text-gray-400">
            @if(count($skus) > 0)
                <span>{{ count($skus) }} produtos · {{ count($skus) * (count($skus) - 1) }} pares</span>
            @endif
            <span>🔄 CIGAM: {{ $ultimaSincCigam }}</span>
        </div>
    </div>

    {{-- Grade N×N --}}
    @if(count($skus) > 0)
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Matriz de Setup (minutos)</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Tempo de troca ao passar do produto linha → coluna</p>
                </div>
                <button wire:click="salvar"
                        class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                    Salvar alterações
                </button>
            </div>

            <div class="overflow-x-auto p-4">
                <table class="text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="w-40 px-2 py-1.5 bg-gray-50 text-gray-400 font-medium text-left border border-gray-200 sticky left-0 z-10">
                                ↙ origem \ destino →
                            </th>
                            @foreach($skus as $skuDest)
                                <th class="px-2 py-1.5 bg-gray-50 text-center border border-gray-200 min-w-[60px] max-w-[90px]">
                                    <div class="font-semibold text-gray-600 whitespace-nowrap">{{ $skuDest }}</div>
                                    <div class="font-normal text-gray-400 text-[10px] normal-case truncate" title="{{ $nomesPorSku[$skuDest] ?? '' }}">{{ $nomesPorSku[$skuDest] ?? '' }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($skus as $skuOrig)
                            <tr>
                                <td class="w-40 px-2 py-1.5 bg-gray-50 border border-gray-200 sticky left-0 z-10">
                                    <div class="font-semibold text-gray-600 whitespace-nowrap">{{ $skuOrig }}</div>
                                    <div class="font-normal text-gray-500 text-[10px] truncate" title="{{ $nomesPorSku[$skuOrig] ?? '' }}">{{ $nomesPorSku[$skuOrig] ?? '' }}</div>
                                </td>
                                @foreach($skus as $skuDest)
                                    @php
                                        $diagonal = ($skuOrig === $skuDest);
                                        $chave    = "{$skuOrig}|{$skuDest}";
                                        $valor    = $celulas[$chave] ?? 0;

                                        $cor = match(true) {
                                            $diagonal      => 'bg-gray-100',
                                            $valor === 0   => 'bg-white',
                                            $valor <= 20   => 'bg-green-50',
                                            $valor <= 30   => 'bg-yellow-50',
                                            default        => 'bg-orange-50',
                                        };
                                        $textoCor = match(true) {
                                            $diagonal      => 'text-gray-300',
                                            $valor === 0   => 'text-gray-400',
                                            $valor <= 20   => 'text-green-700',
                                            $valor <= 30   => 'text-yellow-700',
                                            default        => 'text-orange-700',
                                        };
                                    @endphp
                                    <td class="border border-gray-200 p-0 {{ $cor }}">
                                        @if($diagonal)
                                            <div class="px-2 py-2 text-center text-gray-300">—</div>
                                        @else
                                            <input type="number"
                                                   wire:model.lazy="celulas.{{ $chave }}"
                                                   min="0" step="1"
                                                   class="w-full px-2 py-1.5 text-center bg-transparent border-0
                                                          focus:outline-none focus:ring-1 focus:ring-inset focus:ring-blue-400
                                                          font-medium tabular-nums {{ $cor }} {{ $textoCor }}">
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-gray-100 flex gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 bg-white border border-gray-300 rounded inline-block"></span>
                    0 min
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 bg-green-100 border border-green-200 rounded inline-block"></span>
                    1–20 min
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 bg-yellow-100 border border-yellow-200 rounded inline-block"></span>
                    21–30 min
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 bg-orange-100 border border-orange-200 rounded inline-block"></span>
                    > 30 min
                </span>
            </div>
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm px-5 py-10 text-center text-gray-400 text-sm">
            Nenhum produto ativo encontrado para esta linha.
        </div>
    @endif

</div>
