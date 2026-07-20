<div>

    @if($mensagem)
        <div @class([
            'mb-4 p-3 rounded-xl text-sm border',
            'bg-green-50 border-green-200 text-green-700' => $tipoMensagem === 'sucesso',
            'bg-red-50 border-red-200 text-red-700'       => $tipoMensagem === 'erro',
        ])>
            {{ $mensagem }}
        </div>
    @endif

    {{-- Seletor de máquina --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6 px-5 py-4 flex items-center gap-4 justify-between">
        <label class="text-sm font-medium text-gray-700">Máquina:</label>
        <select wire:model.live="maquinaIdSelecionada"
                class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 bg-white">
            @foreach($maquinas as $maquina)
                <option value="{{ $maquina->id }}">{{ $maquina->codigo }} — {{ $maquina->nome }}</option>
            @endforeach
        </select>

        <div class="text-xs text-gray-400">
            @if(count($skus) > 0)
                <span>{{ count($skus) }} frascos · {{ count($skus) * (count($skus) - 1) }} pares</span>
            @endif
        </div>
    </div>

    {{-- Grade N×N --}}
    @if(count($skus) > 0)
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Matriz de Setup — Sopro</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Tempo (min) e tipo de troca ao passar do frasco linha → coluna</p>
                </div>
                <button wire:click="salvar"
                        class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium px-4 py-1.5 rounded-lg transition-colors">
                    Salvar alterações
                </button>
            </div>

            <div class="overflow-x-auto p-4">
                <table class="text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="w-32 px-2 py-1.5 bg-gray-50 text-gray-400 font-medium text-left border border-gray-200 sticky left-0 z-10">
                                ↙ origem \ destino →
                            </th>
                            @foreach($skus as $skuDest)
                                <th class="px-2 py-1.5 bg-gray-50 text-gray-600 font-semibold text-center border border-gray-200 min-w-[90px] whitespace-normal align-bottom">
                                    <div>{{ $skuDest }}</div>
                                    <div class="text-[10px] font-normal text-gray-400 leading-tight mt-0.5 normal-case">
                                        {{ \Illuminate\Support\Facades\DB::table('frascos')->where('sku', $skuDest)->value('descricao') }}
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($skus as $skuOrig)
                            <tr>
                                <td class="px-2 py-1.5 bg-gray-50 text-gray-700 font-medium border border-gray-200 sticky left-0 z-10">
                                    <div class="whitespace-nowrap">{{ $skuOrig }}</div>
                                    <div class="text-[10px] font-normal text-gray-400 leading-tight whitespace-normal">
                                        {{ \Illuminate\Support\Facades\DB::table('frascos')->where('sku', $skuOrig)->value('descricao') }}
                                    </div>
                                </td>
                                @foreach($skus as $skuDest)
                                    @php
                                        $diagonal  = ($skuOrig === $skuDest);
                                        $chave     = "{$skuOrig}|{$skuDest}";
                                        $valor     = $celulas[$chave] ?? 0;
                                        $tipo      = $tiposSetup[$chave] ?? null;

                                        $cor = match(true) {
                                            $diagonal    => 'bg-gray-100',
                                            $valor === 0 => 'bg-white',
                                            $tipo === 'troca_cor'   => 'bg-blue-50',
                                            $tipo === 'troca_molde' => 'bg-orange-50',
                                            default      => 'bg-yellow-50',
                                        };
                                        $textoCor = match(true) {
                                            $diagonal    => 'text-gray-300',
                                            $valor === 0 => 'text-gray-400',
                                            $tipo === 'troca_cor'   => 'text-blue-700',
                                            $tipo === 'troca_molde' => 'text-orange-700',
                                            default      => 'text-yellow-700',
                                        };
                                    @endphp
                                    <td class="border border-gray-200 p-0">
                                        @if($diagonal)
                                            <div class="px-2 py-2 text-center text-gray-200">—</div>
                                        @else
                                            <div class="flex flex-col">
                                                <input type="number"
                                                       wire:model.lazy="celulas.{{ $chave }}"
                                                       min="0" step="1"
                                                       class="w-full px-2 py-1 text-center bg-transparent border-0
                                                              focus:outline-none focus:ring-1 focus:ring-blue-400
                                                              font-medium tabular-nums {{ $cor }} {{ $textoCor }}">
                                                <select wire:model.lazy="tiposSetup.{{ $chave }}"
                                                        class="w-full text-center border-0 border-t border-gray-100
                                                               bg-transparent text-xs py-0.5 focus:outline-none {{ $textoCor }}">
                                                    <option value="">—</option>
                                                    <option value="troca_cor">Cor</option>
                                                    <option value="troca_molde">Molde</option>
                                                </select>
                                            </div>
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
                    <span class="w-3 h-3 bg-blue-100 border border-blue-200 rounded inline-block"></span>
                    Troca de Cor
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 bg-orange-100 border border-orange-200 rounded inline-block"></span>
                    Troca de Molde
                </span>
            </div>
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm px-5 py-10 text-center text-gray-400 text-sm">
            Nenhum frasco ativo encontrado para esta máquina.
        </div>
    @endif

</div>
