<div>

    {{-- ── Erros globais ──────────────────────────────────────────────── --}}
    @if(count($erros) > 0)
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl">
            <p class="text-sm font-semibold text-red-700 mb-1">Erro no cálculo</p>
            @foreach($erros as $erro)
                <p class="text-sm text-red-600">• {{ $erro }}</p>
            @endforeach
        </div>
    @endif

    @if(session('sucesso'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl">
            <p class="text-sm text-green-700">{{ session('sucesso') }}</p>
        </div>
    @endif


    {{-- ══════════════════════════════════════════════════════════════════
         ETAPA 1 — ENTRADA
    ══════════════════════════════════════════════════════════════════ --}}
    {{-- ── Header: Upload + Arquivo + Tabs — sempre no DOM para manter ImportarExcel montado.
         CSS hidden controla visibilidade; @if desmontaria o componente e perderia $abas. ── --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-4">

        <div @class(['p-6' => true, 'hidden' => $excelCarregado])>
            <p class="text-sm font-semibold text-gray-700 mb-3">📂 Importar Excel do PCP</p>
            <livewire:programacao.importar-excel />
        </div>

        @if($excelCarregado)
            <div class="px-5 pt-3 pb-1 flex items-center justify-between">
                <span class="text-sm text-gray-500 flex items-center gap-1.5">
                    <span>📄</span>
                    <span class="font-medium text-gray-700">{{ $arquivoNome }}</span>
                </span>
                <button onclick="window.location.reload()"
                        class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                    Trocar arquivo
                </button>
            </div>

            {{-- Tabs de linha com badge calculada --}}
            <div class="px-5 py-2.5 flex items-center gap-1 flex-wrap border-b border-gray-100">
                @foreach($abasDisponiveis as $aba)
                    @if($aba['linha_existe'])
                        @php
                            $isAtiva     = $aba['nome'] === $abaSelecionada;
                            $isCalculada = in_array($aba['nome'], $abasCalculadas);
                        @endphp
                        @if($isCalculada)
                            <span class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium cursor-not-allowed"
                                  style="background:#d1fae5; color:#6b7280; opacity:0.7;">
                                {{ $aba['linha_codigo'] }}
                                <span class="text-xs" style="color:#9ca3af;">{{ $aba['total_ordens'] }}</span>
                                <span style="color:#16a34a;">✓</span>
                            </span>
                        @else
                            <button wire:click="trocarAba('{{ $aba['nome'] }}')"
                                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                                    style="{{ $isAtiva ? 'background:#1f2937; color:#ffffff;' : 'background:#e5e7eb; color:#374151;' }}">
                                {{ $aba['linha_codigo'] }}
                                <span class="text-xs" style="{{ $isAtiva ? 'color:#9ca3af;' : 'color:#6b7280;' }}">
                                    {{ $aba['total_ordens'] }}
                                </span>
                                @if($aba['skus_faltando'] > 0)
                                    <span style="color:#f59e0b;">⚠</span>
                                @endif
                            </button>
                        @endif
                    @else
                        <span class="px-3 py-1.5 text-sm text-gray-300 line-through cursor-not-allowed"
                              title="{{ $aba['nome'] }} — linha não cadastrada">
                            {{ $aba['nome'] }}
                        </span>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         ETAPA 1 — ENTRADA
    ══════════════════════════════════════════════════════════════════ --}}
    @if($etapaAtual === 'entrada')

        {{-- ── BLOCO 2 + 3 + Calcular — Alpine gerencia grade sem round-trip por clique ── --}}
        @if($excelCarregado)
            {{-- wire:key força re-init do Alpine ao trocar de linha --}}
            <div x-data="gradeDias(@js($configuracaoDias), @js($turnosDisponiveis), @js($proximosDias))"
                 wire:key="prog-grade-{{ $linhaId }}-{{ count($turnosDisponiveis) }}">

                {{-- ── Config ── --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-4 px-5 py-4">
                    <p class="text-xs font-semibold text-gray-500 mb-3">⚙️ Configuração</p>
                    <div class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Início da 1ª OP *</label>
                            <input type="datetime-local" wire:model="dataInicio"
                                   class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            @error('dataInicio') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Eficiência (%)</label>
                            <input type="number" wire:model="eficiencia" min="1" max="150" step="0.5"
                                   class="w-24 text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            @error('eficiencia') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Grade de dias — Alpine, zero delay por clique --}}
                    <template x-if="proximosDias.length > 0">
                        <div class="mt-4">
                            <label class="block text-xs font-medium text-gray-600 mb-2">
                                Dias e turnos de trabalho
                                <span class="text-[10px] text-gray-400 font-normal ml-1">(próximos 10 dias)</span>
                            </label>
                            <div class="flex gap-2 overflow-x-auto pb-2">
                                <template x-for="diaInfo in proximosDias" :key="diaInfo.data">
                                    <div class="flex-shrink-0 w-20 rounded-lg overflow-hidden border"
                                         :class="dias[diaInfo.data]?.ativo ? 'border-slate-700' : 'border-gray-200'">

                                        {{-- Header do dia --}}
                                        <button type="button"
                                                @click="toggleDia(diaInfo.data)"
                                                class="w-full py-2 text-center transition"
                                                :class="dias[diaInfo.data]?.ativo
                                                    ? 'bg-slate-800 text-white hover:bg-slate-700'
                                                    : 'bg-gray-100 text-gray-400 hover:bg-gray-200'">
                                            <div class="text-xs font-bold"     x-text="diaInfo.label_dia"></div>
                                            <div class="text-sm font-semibold" x-text="diaInfo.label_data"></div>
                                        </button>

                                        {{-- Turnos --}}
                                        <div class="p-1 space-y-1 bg-white">
                                            <template x-for="turno in turnosDisponiveis" :key="turno.id">
                                                <button type="button"
                                                        @click="toggleTurno(diaInfo.data, turno.id)"
                                                        :disabled="!dias[diaInfo.data]?.ativo"
                                                        :title="`${turno.nome}: ${turno.hora_inicio_fmt}–${turno.hora_fim_fmt}`"
                                                        class="w-full rounded text-center transition leading-tight py-1 text-[10px]"
                                                        :class="{
                                                            'bg-slate-600 text-white font-semibold': turnoAtivo(diaInfo.data, turno.id),
                                                            'bg-gray-100 text-gray-400':             !turnoAtivo(diaInfo.data, turno.id),
                                                            'opacity-40 cursor-not-allowed':         !dias[diaInfo.data]?.ativo,
                                                            'hover:opacity-80 cursor-pointer':        dias[diaInfo.data]?.ativo,
                                                        }">
                                                    <span x-text="turno.nome"></span>
                                                    <span class="block opacity-80 text-[9px]" x-text="turno.hora_inicio_fmt"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1.5">Clique no dia para ativar/desativar • Clique no turno para incluir/excluir</p>
                        </div>
                    </template>
                </div>

                {{-- ── Tabela de ordens ── --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-4">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">
                                Ordens — {{ $linhaNome ?: $abaSelecionada }}
                            </h3>
                            <p class="text-xs text-gray-400 mt-0.5">{{ count($itens) }} OPs importadas</p>
                        </div>
                    </div>

                    @if(count($itens) === 0)
                        <div class="px-5 py-8 text-center text-gray-400 text-sm">
                            Nenhuma OP com SKU cadastrado nesta linha.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 w-12">Seq.</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 w-24">OP</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">SKU</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Descrição</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Qtd (cx)</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Prazo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($itens as $item)
                                        @php $semSeq = ($item['sequencia'] > count($itens) * 2); @endphp
                                        <tr @class(['hover:bg-gray-50' => true, 'opacity-50' => $semSeq])>
                                            <td class="px-4 py-2.5 tabular-nums text-xs text-gray-400">{{ $item['sequencia'] }}</td>
                                            <td class="px-4 py-2.5 font-mono text-xs text-blue-600">{{ $item['numero_op'] ?? '—' }}</td>
                                            <td class="px-4 py-2.5 font-mono font-semibold text-gray-800">{{ $item['sku'] }}</td>
                                            <td class="px-4 py-2.5 text-gray-600 max-w-xs truncate">{{ $item['descricao'] }}</td>
                                            <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ number_format($item['quantidade'], 0, ',', '.') }}</td>
                                            <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $item['prazo'] ? \Carbon\Carbon::parse($item['prazo'])->format('d/m/Y') : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ── Calcular ── --}}
                <button type="button"
                        @click="calcular()"
                        :disabled="calculando"
                        class="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-4 rounded-xl text-lg transition-colors flex items-center justify-center gap-2"
                        :class="calculando ? 'opacity-50 cursor-not-allowed' : ''">
                    <span x-show="!calculando">⚡ Calcular Programação</span>
                    <span x-show="calculando" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Calculando...
                    </span>
                </button>

            </div>
        @endif {{-- fim: excelCarregado --}}

    @endif {{-- fim etapa entrada --}}




    {{-- ══════════════════════════════════════════════════════════════════
         ETAPA 2 — RESULTADO
    ══════════════════════════════════════════════════════════════════ --}}
    @if($etapaAtual === 'resultado' && count($resultados) > 0)

        {{-- ── Cards de resumo ───────────────────────────────────── --}}
        @php
            $totalSetup    = $resumo['total_setup_min'] ?? 0;
            $totalProducao = $resumo['total_producao_min'] ?? 0;
            $fimPrevisto   = $resumo['fim_previsto'] ?? null;
            $itensCalc     = collect($resultados)->where('tipo', 'producao')->count();
            $hSetup        = intdiv($totalSetup, 60);
            $mSetup        = $totalSetup % 60;
            $hProd         = intdiv($totalProducao, 60);
            $mProd         = $totalProducao % 60;
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs text-gray-500 mb-1">Total de Setup</p>
                <p class="text-xl font-bold text-orange-600">{{ $hSetup > 0 ? "{$hSetup}h " : '' }}{{ $mSetup }}min</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs text-gray-500 mb-1">Total de Produção</p>
                <p class="text-xl font-bold text-blue-700">{{ $hProd > 0 ? "{$hProd}h " : '' }}{{ $mProd }}min</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs text-gray-500 mb-1">Fim Previsto</p>
                <p class="text-xl font-bold text-gray-800">
                    {{ $fimPrevisto ? \Carbon\Carbon::parse($fimPrevisto)->format('d/m H:i') : '—' }}
                </p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs text-gray-500 mb-1">Itens Calculados</p>
                <p class="text-xl font-bold text-green-600">{{ $itensCalc }}</p>
            </div>
        </div>

        {{-- ── Gantt ──────────────────────────────────────────────── --}}
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Gantt de Produção</h3>
            {{-- P8: 28px por linha + ~80px de overhead (eixo topo + legenda + padding).
                 Garante slot mínimo de 28px por categoria mesmo com muitos blocos. --}}
            <div style="height: {{ max(240, count($resultados) * 28 + 80) }}px">
                <canvas id="ganttChart"></canvas>
            </div>
        </div>

        {{-- ── Tabela de resultado ─────────────────────────────────── --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-5"
             x-data="{ expandido: null }">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Resultado Detalhado</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Tipo</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">SKU</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Início</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Fim</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Duração</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Previsto</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Realizado (CODI)</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Detalhe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($resultados as $idx => $r)
                            @php
                                $isSetup = ($r['tipo'] === 'setup');
                                $inicio  = \Carbon\Carbon::parse($r['inicio']);
                                $fim     = \Carbon\Carbon::parse($r['fim']);
                                $dur     = $r['duracao_minutos'];
                                $durStr  = ($dur >= 60 ? intdiv($dur, 60).'h' : '') . ($dur % 60 ? ($dur % 60).'m' : '');
                            @endphp
                            <tr @class([
                                'bg-orange-50/60'  => $isSetup,
                                'bg-blue-50/40'    => !$isSetup,
                                'hover:bg-gray-50' => true,
                            ])>
                                <td class="px-4 py-2.5">
                                    <span @class([
                                        'inline-block px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-orange-100 text-orange-700' => $isSetup,
                                        'bg-blue-100 text-blue-700'     => !$isSetup,
                                    ])>{{ ucfirst($r['tipo']) }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="font-mono font-semibold text-gray-800">{{ $r['sku'] ?? '—' }}</span>
                                    @if(!empty($r['descricao']))
                                        <br><span class="text-xs text-gray-400 font-normal">{{ $r['descricao'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-gray-600 tabular-nums text-xs">{{ $inicio->format('d/m H:i') }}</td>
                                <td class="px-4 py-2.5 text-gray-600 tabular-nums text-xs">{{ $fim->format('d/m H:i') }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-700 tabular-nums text-xs font-medium">{{ $durStr }}</td>
                                {{-- Previsto --}}
                                <td class="px-4 py-2.5 text-right tabular-nums text-xs text-gray-700">
                                    @if(!$isSetup && isset($r['programado']) && $r['programado'] !== null)
                                        {{ number_format((float)$r['programado'], 0, ',', '.') }} cx
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                {{-- Realizado CODI --}}
                                <td class="px-4 py-2.5 text-right tabular-nums text-xs">
                                    @if(!$isSetup)
                                        @if(isset($r['realizado_codi']) && $r['realizado_codi'] !== null)
                                            @php $pct = $r['pct_realizado'] ?? 0; @endphp
                                            <span @class([
                                                'font-medium',
                                                'text-green-600' => $pct >= 100,
                                                'text-yellow-600'=> $pct >= 70 && $pct < 100,
                                                'text-red-500'   => $pct < 70,
                                            ])>{{ number_format((int)$r['realizado_codi'], 0, ',', '.') }} cx</span>
                                            <span class="text-gray-400 block">{{ $pct }}%</span>
                                        @else
                                            <span class="text-gray-300">Sem dados</span>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    @if(!empty($r['memoria_calculo']))
                                        <button @click="expandido === {{ $idx }} ? expandido = null : expandido = {{ $idx }}"
                                                class="text-xs text-blue-600 hover:underline">
                                            <span x-show="expandido !== {{ $idx }}">Ver</span>
                                            <span x-show="expandido === {{ $idx }}" x-cloak>Fechar</span>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if(!empty($r['memoria_calculo']))
                                <tr x-show="expandido === {{ $idx }}" x-cloak
                                    @class(['bg-orange-50' => $isSetup, 'bg-blue-50' => !$isSetup])>
                                    <td colspan="8" class="px-4 py-3 text-xs text-gray-500 font-mono">
                                        {{ $r['memoria_calculo'] }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-xs text-gray-500 font-medium">Totais</td>
                            <td colspan="3" class="px-4 py-2 text-right text-xs font-semibold text-gray-700">
                                Setup: {{ $hSetup }}h {{ $mSetup }}m &nbsp;|&nbsp;
                                Produção: {{ $hProd }}h {{ $mProd }}m &nbsp;|&nbsp;
                                Fim: {{ $fimPrevisto ? \Carbon\Carbon::parse($fimPrevisto)->format('d/m/Y H:i') : '—' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- ── Ações ───────────────────────────────────────────────── --}}
        @php
            $proximaAba      = null;
            $encontrouAtual  = false;
            foreach ($abasDisponiveis as $aba) {
                if (! $aba['linha_existe']) continue;
                if ($encontrouAtual && ! in_array($aba['nome'], $abasCalculadas)) {
                    $proximaAba = $aba;
                    break;
                }
                if ($aba['nome'] === $abaSelecionada) $encontrouAtual = true;
            }
        @endphp

        <div class="flex flex-wrap gap-3 items-center mb-2">
            @if($proximaAba)
                <button wire:click="trocarAba('{{ $proximaAba['nome'] }}')"
                        class="flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                    Próxima linha: {{ $proximaAba['linha_codigo'] }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            @elseif(count($abasCalculadas) > 0)
                <span class="text-xs text-green-600 font-medium">✓ Todas as linhas calculadas</span>
            @endif
        </div>

        <div class="flex gap-3">
            <button wire:click="recalcular"
                    @if($this->temProducaoIniciada) disabled title="Produção já iniciada no CODI — recalcular está bloqueado" @endif
                    class="border font-medium px-5 py-2.5 rounded-lg text-sm transition-colors
                           {{ $this->temProducaoIniciada
                                ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed'
                                : 'border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer' }}">
                🔄 Recalcular
            </button>
            @if($programacaoSalvaId)
            <a href="{{ route('programacoes.imprimir', $programacaoSalvaId) }}"
               target="_blank"
               class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium px-5 py-2.5 rounded-lg text-sm transition-colors inline-flex items-center gap-1.5">
                🖨️ Imprimir
            </a>
            @endif
        </div>

    @endif

</div>

@script
<script>
    // ── Registro do componente Alpine gradeDias ────────────────────────────────
    // Alpine ja esta inicializado pelo Livewire neste ponto,
    // portanto Alpine.data() pode ser chamado diretamente.
    Alpine.data('gradeDias', (configInicial, turnos, proxDias) => ({
        dias: {},
        turnosDisponiveis: [],
        proximosDias: [],
        calculando: false,

        init() {
            this.turnosDisponiveis = (turnos || []).map((t, idx) => ({
                ...t,
                hora_inicio_fmt: (t.hora_inicio || '').substring(0, 5),
                hora_fim_fmt:    (t.hora_fim    || '').substring(0, 5),
                label: 'T' + (idx + 1),
            }));

            this.proximosDias = (proxDias || []).map(d => ({
                ...d,
                labelDia:  d.label_dia  || '',
                labelData: d.label_data || '',
            }));

            this.dias = JSON.parse(JSON.stringify(configInicial || {}));
        },

        toggleDia(data) {
            if (!this.dias[data]) return;
            const novoEstado = !this.dias[data].ativo;
            this.dias[data].ativo = novoEstado;
            if (novoEstado) {
                this.dias[data].turnos = this.dias[data].turnos.map(t => ({ ...t, ativo: true }));
            }
        },

        toggleTurno(data, turnoId) {
            if (!this.dias[data]?.ativo) return;
            const idx = this.dias[data].turnos.findIndex(t => t.id == turnoId);
            if (idx === -1) return;
            this.dias[data].turnos[idx].ativo = !this.dias[data].turnos[idx].ativo;
            if (!this.dias[data].turnos.some(t => t.ativo)) {
                this.dias[data].ativo = false;
            }
        },

        turnoAtivo(data, turnoId) {
            if (!this.dias[data]?.ativo) return false;
            return (this.dias[data].turnos || []).find(t => t.id == turnoId)?.ativo ?? false;
        },

        async calcular() {
            if (this.calculando) return;
            this.calculando = true;
            try {
                await this.$wire.set('configuracaoDias', this.dias);
                await this.$wire.calcular();
            } catch (e) {
                console.error('Erro ao calcular:', e);
            } finally {
                this.calculando = false;
            }
        },
    }));

    // ── Gantt ─────────────────────────────────────────────────────────────────
    $wire.on('gantt-atualizado', ({ resultados, turnos }) => renderizarGantt(resultados, turnos ?? {}));

    async function renderizarGantt(resultados, turnos = {}) {
        // Aguarda o locale pt-BR terminar de carregar (elimina race condition:
        // o evento gantt-atualizado pode disparar antes do import() ES module completar).
        if (window._localePromise) await window._localePromise;

        const canvas = document.getElementById('ganttChart');
        if (!canvas || !resultados?.length) return;

        if (canvas._chartInstance) {
            canvas._chartInstance.destroy();
            canvas._chartInstance = null;
        }

        // P10: Formatação de milhar consistente com a tabela PHP (number_format pt-BR).
        const fmt = n => n == null ? '?' : Number(n).toLocaleString('pt-BR');
        const fmtCx = n => n == null ? '—' : Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        const formatDt = s => {
            if (!s) return '—';
            const dt = new Date(s.replace(' ', 'T'));
            return `${String(dt.getDate()).padStart(2,'0')}/${String(dt.getMonth()+1).padStart(2,'0')} ${String(dt.getHours()).padStart(2,'0')}:${String(dt.getMinutes()).padStart(2,'0')}`;
        };

        // ── P5: yKey único por bloco ──────────────────────────────────────────────
        // Antes: yKey = r.sku → todos os blocos com mesmo SKU ocupavam a mesma linha
        // e se sobrepunham visualmente. Agora cada ocorrência recebe linha própria.
        //
        // SKU com ocorrência única  → label "SKU"
        // SKU com múltiplas OPs     → label "SKU #1", "SKU #2", ...
        // Setup com destino único   → label "Setup → SKU"
        // Setup com destino repetido → label "Setup → SKU #1", ...
        const skuTotal   = {}, setupTotal   = {};
        resultados.forEach(r => {
            if (r.tipo === 'producao') skuTotal[r.sku]   = (skuTotal[r.sku]   || 0) + 1;
            else                       setupTotal[r.sku] = (setupTotal[r.sku] || 0) + 1;
        });
        const skuSeq = {}, setupSeq = {};
        const yKeyPorIdx = resultados.map(r => {
            if (r.tipo === 'producao') {
                skuSeq[r.sku] = (skuSeq[r.sku] || 0) + 1;
                return skuTotal[r.sku] > 1 ? `${r.sku} #${skuSeq[r.sku]}` : r.sku;
            }
            setupSeq[r.sku] = (setupSeq[r.sku] || 0) + 1;
            return setupTotal[r.sku] > 1
                ? `Setup → ${r.sku} #${setupSeq[r.sku]}`
                : `Setup → ${r.sku || '?'}`;
        });
        const yLabels = [...new Set(yKeyPorIdx)];

        // Metadata para rótulos customizados do eixo Y (duas linhas: OP + SKU)
        const yKeyMeta = {};
        resultados.forEach((r, idx) => {
            const yKey = yKeyPorIdx[idx];
            if (yKeyMeta[yKey]) return;
            if (r.tipo === 'producao') {
                yKeyMeta[yKey] = { op: r.numero_op ?? null, sku: r.sku };
            } else {
                yKeyMeta[yKey] = { op: null, sku: r.sku, isSetup: true };
            }
        });

        // ── P7 (reformulado): eixo fixado em 'hour'; maxTicksLimit controla densidade ──
        // O layout de dois níveis (dia + hora) exige unit:'hour' sempre. Para programações
        // longas o Chart.js agrupa automaticamente (ex.: tick a cada 6h/12h).

        // ── Datasets ─────────────────────────────────────────────────────────────
        const dsProducao = {
            label: 'Previsto',
            data: [],
            backgroundColor: 'rgba(59,130,246,0.25)',
            borderColor: 'rgba(59,130,246,0.4)',
            borderWidth: 1, borderSkipped: false, borderRadius: 2,
            barThickness: 10, minBarLength: 4,
        };
        const dsSetup = {
            label: 'Setup',
            data: [],
            backgroundColor: '#EF9F27', borderColor: '#D97706',
            borderWidth: 1, borderSkipped: false, borderRadius: 2,
            barThickness: 7, minBarLength: 4,
        };
        const dsRealizado = {
            label: 'Realizado (CODI)',
            data: [],
            // P9: desenhado por cima do trilho previsto; barThickness igual = mesmo porte visual
            backgroundColor: '#22c55e',
            borderColor: '#16a34a',
            borderWidth: 1, borderSkipped: false, borderRadius: 2,
            barThickness: 10, minBarLength: 4,
        };

        resultados.forEach((r, idx) => {
            const yKey = yKeyPorIdx[idx];
            const ini  = new Date(r.inicio.replace(' ', 'T')).getTime();
            const fim  = new Date(r.fim.replace(' ', 'T')).getTime();
            const pt   = {
                x: [ini, fim], y: yKey,
                _op: r.numero_op ?? null,
                _sku: r.sku,
                _descricao: r.descricao ?? null,
                _tipo: r.tipo,
                _inicio: r.inicio, _fim: r.fim,
                _durMin: r.duracao_minutos,
                _programado: r.programado,
                _pct: r.pct_realizado ?? null,
                _realizado: r.realizado_codi ?? null,
            };
            (r.tipo === 'setup' ? dsSetup : dsProducao).data.push(pt);

            // P6: Só renderiza barra de realizado quando há produção efetiva no CODI.
            // Antes: barra de largura zero (cinza) era criada para toda OP sem dados,
            // poluindo o dataset e disparando tooltips vazios.
            if (r.tipo === 'producao' && r.pct_realizado !== null && r.pct_realizado > 0) {
                const iniR = new Date(r.inicio_realizado.replace(' ', 'T')).getTime();
                // fimR proporcional ao pct_realizado: quando < 100% o verde termina antes
                // do previsto, expondo a parte azul restante. Evita que fim_realizado == fim
                // (valor padrão do CODI para OPs em andamento) cubra todo o previsto.
                const fimR = iniR + Math.round((fim - ini) * Math.min(r.pct_realizado, 100) / 100);
                dsRealizado.data.push({
                    x: [iniR, fimR], y: yKey,
                    _sku: r.sku, _tipo: 'realizado',
                    _status: r.status_gantt, _pct: r.pct_realizado,
                    _realizado: r.realizado_codi, _programado: r.programado,
                    _inicio: r.inicio_realizado,
                    // _fimCodi é o timestamp bruto do CODI; o visual usa fimR (proporcional ao %)
                    _fimCodi: r.fim_realizado,
                });
            }
        });

        // ── Plugin: faixas de horário não-produtivo ───────────────────────────
        // Sombreia os períodos fora dos turnos configurados (antes do primeiro turno,
        // entre turnos e após o último). Usa beforeDraw para desenhar abaixo das barras.
        const pluginFaixasNaoProdutivas = {
            id: 'faixasNaoProdutivas',
            beforeDraw(chart, _args, opts) {
                const t = opts.turnos;
                if (!t || !Object.keys(t).length) return;

                const { ctx, chartArea, scales } = chart;
                if (!scales.x || !chartArea) return;

                // 'HH:MM' → milissegundos desde meia-noite
                const toMs = hhmm => {
                    const [h, m] = hhmm.split(':').map(Number);
                    return (h * 60 + m) * 60_000;
                };

                const xMin = scales.x.min;
                const xMax = scales.x.max;

                // Preenche um intervalo de tempo no canvas, respeitando os limites da área
                const fillBand = (ts1, ts2) => {
                    if (ts2 <= xMin || ts1 >= xMax) return;
                    const x1 = scales.x.getPixelForValue(Math.max(ts1, xMin));
                    const x2 = scales.x.getPixelForValue(Math.min(ts2, xMax));
                    if (x2 > x1) ctx.fillRect(x1, chartArea.top, x2 - x1, chartArea.bottom - chartArea.top);
                };

                ctx.save();
                // fillStyle é definido por iteração abaixo (dias sem turnos recebem overlay escuro)
                // Clip para não vazar além da área do gráfico
                ctx.beginPath();
                ctx.rect(chartArea.left, chartArea.top, chartArea.right - chartArea.left, chartArea.bottom - chartArea.top);
                ctx.clip();

                // Itera por cada dia visível no eixo X
                const startDay = new Date(xMin);
                startDay.setHours(0, 0, 0, 0);
                for (let d = new Date(startDay); d.getTime() < xMax; d.setDate(d.getDate() + 1)) {
                    const yyyy  = d.getFullYear();
                    const mm    = String(d.getMonth() + 1).padStart(2, '0');
                    const dd    = String(d.getDate()).padStart(2, '0');
                    const dateStr = `${yyyy}-${mm}-${dd}`;
                    const base    = new Date(`${dateStr}T00:00:00`).getTime();
                    const end24   = base + 86_400_000;
                    const slots   = t[dateStr];

                    if (!slots?.length) {
                        // Qualquer dia sem turnos (domingo, sábado, feriado) → overlay escuro
                        ctx.fillStyle = 'rgba(0,0,0,0.18)';
                        fillBand(base, end24);
                        ctx.fillStyle = opts.cor ?? 'rgba(0,0,0,0.08)'; // reset para gaps intra-dia
                        continue;
                    }

                    // Ordena turnos por hora_inicio e preenche as lacunas
                    ctx.fillStyle = opts.cor ?? 'rgba(0,0,0,0.08)';
                    const sorted = [...slots].sort((a, b) => toMs(a.inicio) - toMs(b.inicio));
                    let cursor = base;
                    for (const s of sorted) {
                        const tsIni      = base + toMs(s.inicio);
                        const isOvernight = toMs(s.fim) < toMs(s.inicio);
                        // Turno overnight: termina no fim do dia (a madrugada do dia seguinte
                        // é coberta quando aquele dia for processado)
                        const tsFim = isOvernight ? end24 : base + toMs(s.fim);
                        if (tsIni > cursor) fillBand(cursor, tsIni); // lacuna antes deste turno
                        cursor = tsFim;
                    }
                    if (cursor < end24) fillBand(cursor, end24); // lacuna após último turno
                }

                ctx.restore();
            },
        };

        // ── Plugin: fundo do cabeçalho para dias não-produtivos ──────────────────
        // Pinta fundo suave na faixa do eixo X (acima das barras) para qualquer dia
        // sem turnos cadastrados (dom, sáb, feriados).
        const pluginCabecalhoDias = {
            id: 'cabecalhoDias',
            beforeDraw(chart, _args, opts) {
                const { ctx, chartArea, scales } = chart;
                const xScale = scales.x;
                if (!xScale || !chartArea) return;

                const t = opts.turnos ?? {};
                const xMin = xScale.min;
                const xMax = xScale.max;

                ctx.save();
                // Clip à faixa horizontal da área do gráfico, altura do cabeçalho (eixo)
                ctx.beginPath();
                ctx.rect(chartArea.left, 0, chartArea.right - chartArea.left, chartArea.top);
                ctx.clip();

                const startDay = new Date(xMin);
                startDay.setHours(0, 0, 0, 0);
                for (let d = new Date(startDay); d.getTime() < xMax; d.setDate(d.getDate() + 1)) {
                    const nextDay = new Date(d);
                    nextDay.setDate(nextDay.getDate() + 1);
                    const x1 = Math.max(chartArea.left, xScale.getPixelForValue(d.getTime()));
                    const x2 = Math.min(chartArea.right, xScale.getPixelForValue(nextDay.getTime()));
                    if (x2 <= x1) continue;

                    const yyyy = d.getFullYear();
                    const mm   = String(d.getMonth() + 1).padStart(2, '0');
                    const dd   = String(d.getDate()).padStart(2, '0');
                    const dateStr = `${yyyy}-${mm}-${dd}`;
                    const hasSlots = (t[dateStr]?.length ?? 0) > 0;

                    if (!hasSlots) {
                        // Dia não-produtivo (dom, sáb, feriado): fundo suave no cabeçalho
                        ctx.fillStyle = 'rgba(0,0,0,0.06)';
                        ctx.fillRect(x1, 0, x2 - x1, chartArea.top);
                    }
                }
                ctx.restore();
            },
        };

        // ── Plugin: hachura sobre barras previstas sem dado CODI ─────────────────
        // Após dsProducao ser desenhado (index 0), verifica quais barras não têm
        // entrada em dsRealizado (mesma chave Y) e aplica hachura sutil sobre elas.
        const pluginHachuraSemCodi = {
            id: 'hachuraSemCodi',
            afterDatasetDraw(chart, args) {
                if (args.index !== 0) return; // só após dsProducao
                const { ctx, chartArea } = chart;
                if (!chartArea) return;

                const dsR = chart.data.datasets.find(ds => ds.label === 'Realizado (CODI)');
                const comRealizado = new Set((dsR?.data ?? []).map(d => d.y));

                // Padrão: faixas verticais de 2px a cada 8px
                const pCanvas = document.createElement('canvas');
                pCanvas.width = 8; pCanvas.height = 1;
                const pCtx = pCanvas.getContext('2d');
                pCtx.fillStyle = 'rgba(0,0,0,0.08)';
                pCtx.fillRect(0, 0, 2, 1);
                const hatch = ctx.createPattern(pCanvas, 'repeat');
                if (!hatch) return;

                ctx.save();
                ctx.beginPath();
                ctx.rect(chartArea.left, chartArea.top,
                    chartArea.right - chartArea.left, chartArea.bottom - chartArea.top);
                ctx.clip();
                ctx.fillStyle = hatch;

                const meta = chart.getDatasetMeta(0);
                meta.data.forEach((bar, i) => {
                    const pt = chart.data.datasets[0].data[i];
                    if (comRealizado.has(pt?.y)) return; // já tem CODI — não hachura
                    const x1 = Math.min(bar.x, bar.base);
                    const x2 = Math.max(bar.x, bar.base);
                    const halfH = Math.abs(bar.height ?? 10) / 2;
                    ctx.fillRect(x1, bar.y - halfH, x2 - x1, halfH * 2);
                });
                ctx.restore();
            },
        };

        const pluginLabelsY = {
            id: 'labelsY',
            afterDraw(chart) {
                const { ctx, scales } = chart;
                const yScale = scales.y;
                if (!yScale) return;

                ctx.save();
                const x = yScale.right - 4;

                yScale.ticks.forEach((tick, index) => {
                    const yKey = tick.label;
                    const meta = yKeyMeta[yKey];
                    if (!meta) return;
                    const yPx = yScale.getPixelForValue(index);

                    if (meta.isSetup || !meta.op) {
                        // Setup ou sem OP: uma linha, cinza
                        ctx.font = '11px -apple-system, BlinkMacSystemFont, sans-serif';
                        ctx.fillStyle = '#6B7280';
                        ctx.textAlign = 'right';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(yKey, x, yPx);
                    } else {
                        // Produção: OP em cima (bold, escuro) + SKU embaixo (menor, cinza)
                        ctx.font = '600 11px -apple-system, BlinkMacSystemFont, sans-serif';
                        ctx.fillStyle = '#111827';
                        ctx.textAlign = 'right';
                        ctx.textBaseline = 'bottom';
                        ctx.fillText(meta.op, x, yPx + 1);

                        ctx.font = '10px -apple-system, BlinkMacSystemFont, sans-serif';
                        ctx.fillStyle = '#9CA3AF';
                        ctx.textBaseline = 'top';
                        ctx.fillText(meta.sku, x, yPx + 2);
                    }
                });

                ctx.restore();
            },
        };

        try {
            canvas._chartInstance = new Chart(canvas, {
                type: 'bar',
                plugins: [pluginFaixasNaoProdutivas, pluginCabecalhoDias, pluginHachuraSemCodi, pluginLabelsY],
                // P9: dsProducao desenhado primeiro (fundo/trilho azul), dsRealizado
                // por cima (totalmente visível sobre o trilho). dsSetup por último
                // (linhas próprias — sem sobreposição com produção).
                data: { datasets: [dsProducao, dsRealizado, dsSetup] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    grouped: false,
                    scales: {
                        x: {
                            type: 'time',
                            position: 'top',
                            adapters: { date: { locale: window.dateFnsPtBR } },
                            time: {
                                unit: 'hour',
                                tooltipFormat: 'dd/MM/yyyy HH:mm',
                                displayFormats: {
                                    hour:    'H[h]',
                                    day:     'dd/MM',
                                    week:    'dd/MM',
                                    month:   'MM/yyyy',
                                    quarter: 'MM/yyyy',
                                    year:    'yyyy',
                                },
                            },
                            // Eixo de dois níveis: o primeiro tick visível de cada dia recebe
                            // tick.major=true e retorna ['Seg 16/06','7h'] (duas linhas);
                            // os demais retornam apenas '10h', '13h', etc.
                            afterBuildTicks(scale) {
                                const DIAS = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
                                const seen = new Set();
                                for (const tick of scale.ticks) {
                                    const d   = new Date(tick.value);
                                    const key = `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
                                    if (!seen.has(key)) {
                                        tick.major = true;
                                        tick._ganttDia = `${DIAS[d.getDay()]} ${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}`;
                                        seen.add(key);
                                    }
                                }
                            },
                            title: { display: false },
                            grid: {
                                color:     ctx => ctx.tick?.major ? 'rgba(0,0,0,0.08)' : 'rgba(0,0,0,0)',
                                lineWidth: 1,
                            },
                            ticks: {
                                major: { enabled: true },
                                font:  ctx => ctx.tick?.major ? { weight: '600', size: 11 } : { size: 10 },
                                color: ctx => {
                                    if (!ctx.tick?.major) return '#9CA3AF';
                                    const d2  = new Date(ctx.tick.value);
                                    const key = `${d2.getFullYear()}-${String(d2.getMonth()+1).padStart(2,'0')}-${String(d2.getDate()).padStart(2,'0')}`;
                                    return (turnos[key]?.length ?? 0) > 0 ? '#111827' : '#B0BAC5';
                                },
                                maxRotation: 0,
                                maxTicksLimit: 24,
                                callback(value, index, ticks) {
                                    const tick   = ticks[index];
                                    const hLabel = `${new Date(value).getHours()}h`;
                                    return tick?._ganttDia ? [tick._ganttDia, hLabel] : hLabel;
                                },
                            },
                        },
                        y: {
                            type: 'category', labels: yLabels,
                            grid: { display: false },
                            ticks: {
                                font: { size: 11 },
                                // Esconde rótulos nativos (mantém width do eixo) — plugin labelsY desenha rótulos customizados
                                color: 'transparent',
                            },
                        },
                    },
                    plugins: {
                        // Opções dos plugins inline — acessadas via opts.* nos hooks beforeDraw/afterDatasetDraw
                        faixasNaoProdutivas: {
                            turnos: turnos,
                            cor: 'rgba(0,0,0,0.08)',
                        },
                        cabecalhoDias: {
                            turnos: turnos,
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12, font: { size: 11 }, padding: 16,
                                generateLabels(chart) {
                                    const base = Chart.defaults.plugins.legend.labels.generateLabels(chart)
                                        .filter(l => ['Previsto', 'Realizado (CODI)', 'Setup'].includes(l.text));
                                    // Entradas manuais para elementos desenhados por plugins
                                    base.push({
                                        text: 'Sem produção real',
                                        fillStyle: 'rgba(0,0,0,0.06)',
                                        strokeStyle: 'rgba(0,0,0,0.25)',
                                        lineWidth: 1, lineDash: [2, 4],
                                        hidden: false,
                                    });
                                    base.push({
                                        text: 'Dom não produtivo',
                                        fillStyle: 'rgba(0,0,0,0.18)',
                                        strokeStyle: 'rgba(0,0,0,0.3)',
                                        lineWidth: 1,
                                        hidden: false,
                                    });
                                    return base;
                                },
                            },
                        },
                        tooltip: {
                            // filter remove duplicidade: barra 'realizado' fica oculta no tooltip
                            // (seus dados são mesclados na entrada 'producao' via label callback)
                            filter: item => item.raw?._tipo !== 'realizado',
                            callbacks: {
                                title(items) {
                                    const d = items[0]?.raw;
                                    if (!d) return '';
                                    if (d._tipo === 'setup') return `Setup → ${d._sku || ''}`;
                                    return d._op ? `OP ${d._op}` : (d._sku || '');
                                },
                                afterTitle(items) {
                                    const d = items[0]?.raw;
                                    return (d?._tipo === 'producao' && d._descricao) ? d._descricao : undefined;
                                },
                                beforeBody: () => ' ',
                                label(ctx) {
                                    const d = ctx.raw;
                                    if (!d) return null;

                                    if (d._tipo === 'setup') {
                                        const dur = d._durMin ?? 0;
                                        return [
                                            ` Destino: ${d._sku || '—'}`,
                                            ` Duração: ${Math.floor(dur / 60) > 0 ? Math.floor(dur / 60) + 'h ' : ''}${dur % 60}min`,
                                            ` Início:  ${formatDt(d._inicio)}`,
                                            ` Fim:     ${formatDt(d._fim)}`,
                                        ];
                                    }

                                    // producao — busca o realizado correspondente no dataset
                                    const dsR = ctx.chart.data.datasets.find(ds => ds.label === 'Realizado (CODI)');
                                    const real = dsR?.data.find(pt => pt.y === d.y) ?? null;

                                    const linhas = [
                                        ` SKU:       ${d._sku || '—'}`,
                                        ` Previsto:  ${fmtCx(d._programado)} cx`,
                                    ];
                                    if (real !== null) {
                                        const icon = (real._pct ?? 0) >= 100 ? '✅' : '⚠️';
                                        linhas.push(` Realizado: ${fmtCx(real._realizado)} cx  (${real._pct ?? 0}% ${icon})`);
                                    } else {
                                        linhas.push(` Realizado: —`);
                                    }
                                    linhas.push(
                                        ` Início:    ${formatDt(d._inicio)}`,
                                        ` Fim:       ${formatDt(d._fim)}`,
                                    );
                                    return linhas;
                                },
                            },
                            bodyFont: { family: 'monospace', size: 11 },
                        },
                    },
                },
            });
        } catch (e) {
            console.error('[Gantt] Erro:', e);
        }
    }
</script>
@endscript
