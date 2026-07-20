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

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6 px-5 py-4 flex items-center gap-4 justify-between">
        <label class="text-sm font-medium text-gray-700">Máquina:</label>
        <select wire:model.live="maquinaSelecionada"
                class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 bg-white">
            @foreach($maquinas as $m)
                <option value="{{ $m['id'] }}">{{ $m['codigo'] }} — {{ $m['nome'] }}</option>
            @endforeach
        </select>

        @if($calendario)
            <button wire:click="aplicarTodasMaquinas"
                    wire:confirm="Isso vai sobrescrever os turnos de TODAS as outras máquinas com os turnos desta. Confirma?"
                    class="text-xs font-medium px-3 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                Aplicar turnos a todas as máquinas
            </button>
        @endif
    </div>

    @if($calendario)
        {{-- Turnos --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Turnos — {{ $calendario['nome'] }}</h3>
                @if(!$adicionandoTurno)
                    <button wire:click="iniciarNovoTurno"
                            class="text-xs font-medium px-3 py-1.5 bg-green-50 text-green-700 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                        + Novo turno
                    </button>
                @endif
            </div>

            <div class="divide-y divide-gray-100">
                @foreach($intervalos as $turno)
                    @if($turnoEditando === $turno['id'])
                        <div class="px-5 py-4 bg-blue-50">
                            <div class="grid grid-cols-4 gap-3 mb-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Nome</label>
                                    <input type="text" wire:model="turnoForm.nome" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Início</label>
                                    <input type="time" wire:model="turnoForm.hora_inicio" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Fim</label>
                                    <input type="time" wire:model="turnoForm.hora_fim" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                                </div>
                                <div class="flex items-end">
                                    <label class="flex items-center gap-2 text-sm text-gray-600">
                                        <input type="checkbox" wire:model="turnoForm.ativo">
                                        Ativo
                                    </label>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="salvarTurno" class="text-xs font-medium px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700">Salvar</button>
                                <button wire:click="cancelarEdicao" class="text-xs font-medium px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">Cancelar</button>
                            </div>
                        </div>
                    @else
                        <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                            <div class="flex items-center gap-4">
                                <span class="font-medium text-sm text-gray-800">{{ $turno['nome'] }}</span>
                                <span class="text-sm text-gray-500 tabular-nums">
                                    {{ substr($turno['hora_inicio'], 0, 5) }} – {{ substr($turno['hora_fim'], 0, 5) }}
                                </span>
                                @if(!$turno['ativo'])
                                    <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-400 rounded-full">Inativo</span>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="editarTurno({{ $turno['id'] }})" class="text-xs text-blue-600 hover:underline">Editar</button>
                                <button wire:click="removerTurno({{ $turno['id'] }})" wire:confirm="Remover este turno?" class="text-xs text-red-500 hover:underline">Remover</button>
                            </div>
                        </div>
                    @endif
                @endforeach

                @if($adicionandoTurno)
                    <div class="px-5 py-4 bg-green-50">
                        <div class="grid grid-cols-4 gap-3 mb-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Nome</label>
                                <input type="text" wire:model="novoTurnoForm.nome" placeholder="T1" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Início</label>
                                <input type="time" wire:model="novoTurnoForm.hora_inicio" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Fim</label>
                                <input type="time" wire:model="novoTurnoForm.hora_fim" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" wire:model="novoTurnoForm.ativo">
                                    Ativo
                                </label>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="salvarNovoTurno" class="text-xs font-medium px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700">Adicionar</button>
                            <button wire:click="cancelarNovoTurno" class="text-xs font-medium px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">Cancelar</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Feriados --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Feriados / Paradas Programadas</h3>
            </div>
            <div class="px-5 py-4">
                <div class="flex gap-3 mb-4">
                    <input type="date" wire:model="feriadoData" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <input type="text" wire:model="feriadoDescricao" placeholder="Descrição" class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <button wire:click="adicionarFeriado" class="text-xs font-medium px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Adicionar</button>
                </div>
                @if(count($feriados) > 0)
                    <div class="space-y-1">
                        @foreach($feriados as $f)
                            <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg text-sm">
                                <span>{{ \Carbon\Carbon::parse($f['data'])->format('d/m/Y') }} — {{ $f['descricao'] }}</span>
                                <button wire:click="removerFeriado({{ $f['id'] }})" class="text-xs text-red-500 hover:underline">Remover</button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400">Nenhum feriado cadastrado.</p>
                @endif
            </div>
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm px-5 py-10 text-center text-gray-400 text-sm">
            Esta máquina não possui calendário configurado.
        </div>
    @endif
</div>
