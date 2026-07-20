@extends('layouts.app')
@section('titulo', 'Resultado — ' . $programacao->maquina->codigo)
@section('conteudo')
<div class="max-w-5xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Resultado da Programação</h2>
            <p class="text-sm text-gray-500 mt-1">
                {{ $programacao->maquina->codigo }} — {{ $programacao->maquina->nome }}
                &middot; Programação #{{ $programacao->id }}
                &middot; <span class="capitalize">{{ $programacao->status }}</span>
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('sopro.programacoes') }}"
               class="text-sm px-4 py-2 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">
                ← Voltar
            </a>
            <a href="{{ route('sopro.imprimir', $programacao->id) }}" target="_blank"
               class="text-sm px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                🖨 Imprimir
            </a>
        </div>
    </div>

    {{-- Resumo --}}
    <div class="grid grid-cols-4 gap-3 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase">Início previsto</p>
            <p class="text-sm font-semibold text-gray-800 mt-1">
                {{ $programacao->resultados->min('inicio') ? \Carbon\Carbon::parse($programacao->resultados->min('inicio'))->format('d/m/Y H:i') : '—' }}
            </p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase">Fim previsto</p>
            <p class="text-sm font-semibold text-gray-800 mt-1">
                {{ $programacao->resultados->max('fim') ? \Carbon\Carbon::parse($programacao->resultados->max('fim'))->format('d/m/Y H:i') : '—' }}
            </p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase">Total de OPs</p>
            <p class="text-sm font-semibold text-gray-800 mt-1">{{ $programacao->itens->count() }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase">Total produção</p>
            <p class="text-sm font-semibold text-gray-800 mt-1">
                {{ number_format($programacao->itens->sum('quantidade') * 1000, 0, ',', '.') }} un
            </p>
        </div>
    </div>

    {{-- Sequência --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Sequência de Produção</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">SKU</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Início</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Fim</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Duração</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">OP</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Qtd (un)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($programacao->resultados->sortBy('inicio') as $resultado)
                    @php
                        $item = $programacao->itens->firstWhere('id', $resultado->item_id);
                        $isSetup = $resultado->tipo === 'setup';
                    @endphp
                    <tr class="{{ $isSetup ? 'bg-amber-50' : 'hover:bg-gray-50' }} transition-colors">
                        <td class="px-4 py-2.5">
                            @if($isSetup)
                                <span class="inline-block px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-medium">Setup</span>
                            @else
                                <span class="inline-block px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">Produção</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-600">{{ $resultado->sku ?? '—' }}</td>
                        <td class="px-4 py-2.5 tabular-nums text-gray-600 text-xs">
                            {{ $resultado->inicio ? \Carbon\Carbon::parse($resultado->inicio)->format('d/m H:i') : '—' }}
                        </td>
                        <td class="px-4 py-2.5 tabular-nums text-gray-600 text-xs">
                            {{ $resultado->fim ? \Carbon\Carbon::parse($resultado->fim)->format('d/m H:i') : '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">
                            @php
                                $h = intdiv($resultado->duracao_minutos, 60);
                                $m = $resultado->duracao_minutos % 60;
                            @endphp
                            {{ $h > 0 ? $h.'h ' : '' }}{{ $m }}min
                        </td>
                        <td class="px-4 py-2.5 text-right text-gray-600 font-mono text-xs">
                            {{ $item?->numero_op ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-medium text-gray-800">
                            @if(!$isSetup && $item)
                                {{ number_format($item->quantidade * 1000, 0, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Estilos de impressão --}}
<style>
@media print {
    aside, nav, button, a.btn { display: none !important; }
    body { background: white !important; }
    .shadow-sm { box-shadow: none !important; }
    .rounded-xl { border-radius: 0 !important; }
}
</style>
@endsection
