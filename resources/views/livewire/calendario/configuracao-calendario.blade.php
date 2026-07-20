<div>

    {{-- Seleção de linha --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm mb-5">
        <label class="block text-sm font-medium text-gray-700 mb-2">Linha de Produção</label>
        <select wire:model.live="linhaSelecionada"
                class="w-64 text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <option value="">Selecione uma linha...</option>
            @foreach($linhas as $l)
                <option value="{{ $l['id'] }}">{{ $l['codigo'] }} — {{ $l['nome'] }}</option>
            @endforeach
        </select>
    </div>

    {{-- Feedback global --}}
    @if($mensagem)
        <div @class([
            'mb-4 px-4 py-3 rounded-xl border text-sm font-medium',
            'bg-green-50 border-green-300 text-green-800' => $tipoMensagem === 'sucesso',
            'bg-red-50 border-red-300 text-red-800'       => $tipoMensagem === 'erro',
        ])>
            {{ $mensagem }}
        </div>
    @endif

    @if($calendario)

        {{-- ── Layout duas colunas ─────────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {{-- ══ COLUNA ESQUERDA — Turnos ══════════════════════════════ --}}
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Turnos de Trabalho</h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $calendario['nome'] }}</p>
                </div>

                {{-- Botão adicionar turno --}}
                <div class="px-5 py-3 border-b border-gray-100 flex justify-end">
                    @if(! $adicionandoTurno)
                        <button wire:click="iniciarNovoTurno"
                                class="text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded-lg transition-colors">
                            + Novo Turno
                        </button>
                    @endif
                </div>

                {{-- Formulário de novo turno --}}
                @if($adicionandoTurno)
                    <div class="px-5 py-4 bg-green-50/60 border-l-4 border-green-500 border-b border-gray-100">
                        <p class="text-xs font-semibold text-green-700 mb-3">Novo Turno</p>
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-600 mb-1">Nome</label>
                                <input type="text" wire:model="novoTurnoForm.nome" placeholder="ex: Turno 1"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-green-500">
                                @error('novoTurnoForm.nome') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Início</label>
                                <input type="time" wire:model="novoTurnoForm.hora_inicio"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-green-500">
                                @error('novoTurnoForm.hora_inicio') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Fim</label>
                                <input type="time" wire:model="novoTurnoForm.hora_fim"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-green-500">
                                @error('novoTurnoForm.hora_fim') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs text-gray-600 mb-1.5">Dias da semana</label>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach([1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'] as $numDia => $nomeDia)
                                    <label class="inline-flex items-center gap-1 cursor-pointer select-none">
                                        <input type="checkbox" wire:model="novoTurnoForm.dias" value="{{ $numDia }}"
                                               class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                        <span class="text-xs font-medium text-gray-700">{{ $nomeDia }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('novoTurnoForm.dias') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div class="mb-4 flex items-center gap-2">
                            <input type="checkbox" wire:model="novoTurnoForm.ativo" id="novo-ativo"
                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <label for="novo-ativo" class="text-xs font-medium text-gray-700 cursor-pointer">Turno ativo</label>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="salvarNovoTurno"
                                    class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition-colors">
                                Salvar
                            </button>
                            <button wire:click="cancelarNovoTurno"
                                    class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-xs font-medium px-4 py-1.5 rounded-lg transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </div>
                @endif

                <div class="divide-y divide-gray-100">
                    @forelse($intervalos as $intervalo)
                        @if($turnoEditando === $intervalo['id'])
                            {{-- ── Formulário de edição inline ── --}}
                            <div class="px-5 py-4 bg-blue-50/60 border-l-4 border-blue-500">
                                <p class="text-xs font-semibold text-blue-700 mb-3">Editando {{ $intervalo['nome'] }}</p>

                                <div class="grid grid-cols-2 gap-3 mb-3">
                                    <div class="col-span-2">
                                        <label class="block text-xs text-gray-600 mb-1">Nome</label>
                                        <input type="text" wire:model="turnoForm.nome"
                                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
                                        @error('turnoForm.nome') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Início</label>
                                        <input type="time" wire:model="turnoForm.hora_inicio"
                                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
                                        @error('turnoForm.hora_inicio') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Fim</label>
                                        <input type="time" wire:model="turnoForm.hora_fim"
                                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
                                        @error('turnoForm.hora_fim') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                {{-- Dias da semana --}}
                                <div class="mb-3">
                                    <label class="block text-xs text-gray-600 mb-1.5">Dias da semana</label>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach([1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'] as $numDia => $nomeDia)
                                            <label class="inline-flex items-center gap-1 cursor-pointer select-none">
                                                <input type="checkbox" wire:model="turnoForm.dias"
                                                       value="{{ $numDia }}"
                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span class="text-xs font-medium text-gray-700">{{ $nomeDia }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('turnoForm.dias') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                                </div>

                                {{-- Toggle ativo --}}
                                <div class="mb-4 flex items-center gap-2">
                                    <input type="checkbox" wire:model="turnoForm.ativo" id="ativo-{{ $intervalo['id'] }}"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label for="ativo-{{ $intervalo['id'] }}" class="text-xs font-medium text-gray-700 cursor-pointer">
                                        Turno ativo
                                    </label>
                                </div>

                                <div class="flex gap-2">
                                    <button wire:click="salvarTurno"
                                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition-colors">
                                        Salvar
                                    </button>
                                    <button wire:click="cancelarEdicao"
                                            class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-xs font-medium px-4 py-1.5 rounded-lg transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- ── Card de exibição do turno ── --}}
                            <div class="px-5 py-3.5 flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-semibold text-gray-800">{{ $intervalo['nome'] }}</span>
                                        @if(! $intervalo['ativo'])
                                            <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">inativo</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <span class="font-mono text-xs text-gray-600">
                                            {{ substr($intervalo['hora_inicio'], 0, 5) }}–{{ substr($intervalo['hora_fim'], 0, 5) }}
                                        </span>
                                        <div class="flex gap-1 flex-wrap">
                                            @php $nomesDias = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom']; @endphp
                                            @foreach($intervalo['dias'] as $dia)
                                                <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-medium">
                                                    {{ $nomesDias[$dia] ?? $dia }}
                                                </span>
                                            @endforeach
                                            @if(empty($intervalo['dias']))
                                                <span class="text-[10px] text-gray-400">nenhum dia</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <button wire:click="editarTurno({{ $intervalo['id'] }})"
                                        class="shrink-0 text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors">
                                    Editar
                                </button>
                            </div>
                        @endif
                    @empty
                        <div class="px-5 py-8 text-center text-gray-400 text-sm">
                            Nenhum turno cadastrado para este calendário.
                        </div>
                    @endforelse
                </div>
                {{-- Botão aplicar a todas as linhas --}}
                <div class="px-5 py-4 border-t border-gray-100">
                    <button wire:click="aplicarTodasLinhas"
                            wire:confirm="Isso vai sobrescrever os turnos de TODAS as outras linhas com os turnos desta linha. Os feriados de cada linha são mantidos. Confirma?"
                            class="w-full py-2 px-4 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition-colors">
                        📋 Aplicar turnos a todas as linhas
                    </button>
                </div>
            </div>

            {{-- ══ COLUNA DIREITA — Feriados ═══════════════════════════════ --}}
            <div class="space-y-4">

                {{-- Lista de feriados --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Feriados Cadastrados</h3>
                    </div>
                    @if(empty($feriados))
                        <p class="px-5 py-8 text-sm text-gray-400 text-center">Nenhum feriado cadastrado.</p>
                    @else
                        <ul class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                            @foreach($feriados as $feriado)
                                <li class="flex items-center justify-between px-5 py-2.5">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="font-mono text-sm text-gray-700 shrink-0">
                                            {{ \Carbon\Carbon::parse($feriado['data'])->format('d/m/Y') }}
                                        </span>
                                        <span class="text-sm text-gray-500 truncate">{{ $feriado['descricao'] }}</span>
                                    </div>
                                    <button wire:click="removerFeriado({{ $feriado['id'] }})"
                                            wire:confirm="Remover feriado {{ \Carbon\Carbon::parse($feriado['data'])->format('d/m/Y') }}?"
                                            class="shrink-0 ml-2 text-xs text-red-400 hover:text-red-600 transition-colors">
                                        Remover
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Formulário de adição --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Adicionar Feriado</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Data *</label>
                            <input type="date" wire:model="feriadoData"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            @error('feriadoData') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Descrição *</label>
                            <input type="text" wire:model="feriadoDescricao"
                                   placeholder="Ex: Natal"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   wire:keydown.enter="adicionarFeriado">
                            @error('feriadoDescricao') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <button wire:click="adicionarFeriado"
                                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                            Adicionar
                        </button>
                    </div>
                </div>

            </div>

        </div>

    @else
        <div class="bg-white border border-gray-200 rounded-xl p-10 text-center text-gray-400 text-sm shadow-sm">
            Selecione uma linha para visualizar e editar o calendário.
        </div>
    @endif

</div>
