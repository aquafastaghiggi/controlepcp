@extends('layouts.app')

@section('titulo', 'Configurações')

@section('conteudo')

<div class="mb-6">
    <h1 class="text-xl font-bold text-gray-800">Configurações</h1>
    <p class="text-sm text-gray-500 mt-1">Parâmetros do sistema — cálculo de previsto e turnos de trabalho</p>
</div>

<div class="space-y-6">

    {{-- Seção 1: Cálculo Previsto do Dia --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-700">Cálculo Previsto do Dia ("de")</h2>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                Somente leitura — editável em breve
            </span>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 mb-4">
            <code class="text-sm text-gray-800">{{ $formulaDe }}</code>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="font-medium text-gray-600">taxa_por_hora</dt>
                <dd class="text-gray-500 mt-0.5">Capacidade produtiva do produto, cadastrada em cada item de <span class="font-medium">produtos</span> (caixas por hora).</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600">eficiência</dt>
                <dd class="text-gray-500 mt-0.5">Percentual de eficiência definido na programação (<span class="font-medium">programacoes.eficiencia</span>), simula o desempenho real da linha.</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600">minutos úteis na janela</dt>
                <dd class="text-gray-500 mt-0.5">Minutos de turno realmente disponíveis entre 06:00 de hoje e 03:00 do dia seguinte, calculados pelo calendário/turnos da linha.</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600">limitado à quantidade da OP</dt>
                <dd class="text-gray-500 mt-0.5">O previsto nunca ultrapassa a quantidade total programada da ordem de produção, mesmo que a capacidade calculada seja maior.</dd>
            </div>
        </dl>
    </div>

    {{-- Seção 2: Turnos de Trabalho --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-700">Turnos de Trabalho</h2>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                Somente leitura — editável em breve
            </span>
        </div>

        @if(empty($turnos))
            <div class="py-8 text-center text-gray-400 text-sm">
                Nenhum turno configurado encontrado.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Nome</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Início</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Fim</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Tipo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($turnos as $turno)
                            <tr class="{{ $turno['noturno'] ? 'bg-amber-50' : '' }}">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $turno['nome'] }}</td>
                                <td class="px-4 py-3 text-gray-600 tabular-nums">{{ $turno['hora_inicio'] }}</td>
                                <td class="px-4 py-3 text-gray-600 tabular-nums">{{ $turno['hora_fim'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($turno['noturno'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                            🌙 Noturno
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            Diurno
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

@endsection
