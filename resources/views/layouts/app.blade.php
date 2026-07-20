<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — @yield('titulo', 'Painel')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <div class="flex h-screen">

        {{-- ── MENU LATERAL ─────────────────────────────────────────────── --}}
        <aside class="w-64 bg-slate-800 text-white flex flex-col shadow-xl shrink-0 h-screen overflow-y-auto">

            <div class="px-5 py-4 border-b border-slate-700">
                <h1 class="text-base font-bold tracking-tight leading-tight">ControlePCP</h1>
                <span class="text-xs text-slate-400">v2.0</span>
            </div>

            <nav class="flex-1 px-3 py-3 space-y-0">

                {{-- Painel --}}
                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150
                          {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                    <span class="text-base leading-none">📊</span>
                    <span>Painel</span>
                </a>

                {{-- Planejamento --}}
                <div x-data="{ aberto: true }">
                    <button @click="aberto = !aberto"
                            class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                                   {{ request()->routeIs('programacoes', 'divergencias') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                        <span class="flex items-center gap-2">
                            <span>🗓️</span>
                            <span>Planejamento</span>
                        </span>
                        <svg class="w-3 h-3 transition-transform" :class="aberto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="aberto" x-transition class="mt-1 ml-4 space-y-0.5">
                        <a href="{{ route('programacoes') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('programacoes') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>🗓️</span><span>Programação</span>
                        </a>
                        @php $totalDivergencias = \Illuminate\Support\Facades\DB::table('divergencias_op')->whereNull('resolvida_em')->count(); @endphp
                        <a href="{{ route('divergencias') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('divergencias') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>
                                @if($totalDivergencias > 0)
                                    <span style="animation:blink 1s infinite;display:inline-block">⚠</span>
                                @else
                                    📋
                                @endif
                            </span>
                            <span class="flex items-center gap-1">
                                Divergências
                                @if($totalDivergencias > 0)
                                    <span class="text-xs bg-red-500 text-white rounded-full px-1.5 py-0.5 leading-none">{{ $totalDivergencias }}</span>
                                @endif
                            </span>
                        </a>
                    </div>
                </div>

                {{-- Envase --}}
                <div x-data="{ aberto: true }">
                    <button @click="aberto = !aberto"
                            class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                                   {{ request()->routeIs('acompanhar', 'desempenho', 'historico', 'calendario') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                        <span class="flex items-center gap-2">
                            <span>🏭</span>
                            <span>Envase</span>
                        </span>
                        <svg class="w-3 h-3 transition-transform" :class="aberto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="aberto" x-transition class="mt-1 ml-4 space-y-0.5">
                        <a href="{{ route('acompanhar') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('acompanhar') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📡</span><span>Acompanhar Produção</span>
                        </a>
                        <a href="{{ route('calendario') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('calendario') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📅</span><span>Calendário</span>
                        </a>
                        <a href="{{ route('desempenho') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('desempenho') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📈</span><span>Desempenho</span>
                        </a>
                        <a href="{{ route('historico') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('historico') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📋</span><span>Histórico</span>
                        </a>
                        <a href="{{ route('tv.static2') }}" target="_blank"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('tv.static2') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📺</span><span>TV Dashboard 2</span>
                        </a>
                    </div>
                </div>

                {{-- Sopro --}}
                <div x-data="{ aberto: true }">
                    <button @click="aberto = !aberto"
                            class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                                   {{ request()->routeIs('sopro.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                        <span class="flex items-center gap-2">
                            <span>🧴</span>
                            <span>Sopro</span>
                        </span>
                        <svg class="w-3 h-3 transition-transform" :class="aberto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="aberto" x-transition class="mt-1 ml-4 space-y-0.5">
                        <a href="{{ route('sopro.acompanhar') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('sopro.acompanhar') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📊</span><span>Acompanhar</span>
                        </a>
                        <a href="{{ route('sopro.calendario') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('sopro.calendario') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📅</span><span>Calendário</span>
                        </a>
                        <a href="{{ route('sopro.frascos') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('sopro.frascos') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>🧴</span><span>Frascos</span>
                        </a>
                        <a href="{{ route('sopro.frascos.fotos') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('sopro.frascos.fotos') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>🖼️</span><span>Fotos</span>
                        </a>
                        <a href="{{ route('sopro.maquinas') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('sopro.maquinas') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>⚙️</span><span>Máquinas</span>
                        </a>
                        <a href="{{ route('sopro.matriz') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('sopro.matriz') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>⚙️</span><span>Matriz de Setup</span>
                        </a>
                        <a href="{{ route('sopro.programacoes') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('sopro.programacoes') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📋</span><span>Histórico</span>
                        </a>
                        <a href="{{ route('tv.sopro.static') }}" target="_blank"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('tv.sopro.static') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📺</span><span>TV Dashboard</span>
                        </a>
                    </div>
                </div>

                {{-- Relatórios — desabilitado temporariamente (em construção) --}}
                <span class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-300"
                      style="opacity:0.5; cursor:not-allowed;">
                    <span>📊</span>
                    <span>Relatório</span>
                    <span style="font-size:10px; background:#555; color:#fff; padding:1px 5px; border-radius:4px; margin-left:4px;">Em construção</span>
                </span>

                {{-- Cadastros --}}
                <div x-data="{ aberto: true }">
                    <button @click="aberto = !aberto"
                            class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                                   {{ request()->routeIs('produtos*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                        <span class="flex items-center gap-2">
                            <span>📦</span>
                            <span>Cadastros</span>
                        </span>
                        <svg class="w-3 h-3 transition-transform" :class="aberto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="aberto" x-transition class="mt-1 ml-4 space-y-0.5">
                        <a href="{{ route('produtos') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('produtos') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📦</span><span>Produtos</span>
                        </a>
                        <a href="{{ route('produtos.matrizes') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('produtos.matrizes') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>🔀</span><span>Matrizes de Setup</span>
                        </a>
                <a href="{{ route('produtos.fotos') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('produtos.fotos') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>🖼️</span><span>Fotos</span>
                        </a>
                    </div>
                </div>

                {{--
                Marketing — oculto do menu lateral a pedido, rota/controller mantidos intactos
                (route('marketing.projecao-tv') continua funcionando por link direto).
                <div x-data="{ aberto: true }">
                    <button @click="aberto = !aberto"
                            class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                                   {{ request()->routeIs('marketing.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                        <span class="flex items-center gap-2">
                            <span>📣</span>
                            <span>Marketing</span>
                        </span>
                        <svg class="w-3 h-3 transition-transform" :class="aberto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="aberto" x-transition class="mt-1 ml-4 space-y-0.5">
                        <a href="{{ route('marketing.projecao-tv') }}" target="_blank" rel="noopener"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('marketing.projecao-tv') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>📺</span><span>Projeção TVs</span>
                        </a>
                    </div>
                </div>
                --}}

                {{-- TV Dashboard (dinâmica) — oculta do menu, mantida a rota. Usar TV Static.
                <a href="{{ route('tv') }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors text-slate-300 hover:bg-slate-700 hover:text-white">
                    <span>📺</span>
                    <span>TV Dashboard</span>
                </a>
                --}}
                <a href="{{ route('tv.static') }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors text-slate-300 hover:bg-slate-700 hover:text-white">
                    <span>📺</span>
                    <span>TV Dashboard</span>
                </a>

                {{-- Configurações --}}
                <div x-data="{ aberto: true }">
                    <button @click="aberto = !aberto"
                            class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                                   {{ request()->routeIs('configuracoes.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                        <span class="flex items-center gap-2">
                            <span>⚙️</span>
                            <span>Configurações</span>
                        </span>
                        <svg class="w-3 h-3 transition-transform" :class="aberto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="aberto" x-transition class="mt-1 ml-4 space-y-0.5">
                        <a href="{{ route('configuracoes.index') }}"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs transition-colors
                                  {{ request()->routeIs('configuracoes.*') ? 'bg-slate-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white' }}">
                            <span>🧮</span><span>Cálculos</span>
                        </a>
                    </div>
                </div>

            </nav>

            <div class="px-4 py-3 border-t border-slate-700">
                <p class="text-xs text-slate-400 truncate">{{ auth()->user()->name ?? '' }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit"
                            class="text-xs text-slate-500 hover:text-white transition-colors">
                        Sair
                    </button>
                </form>
            </div>

        </aside>

        {{-- ── CONTEÚDO PRINCIPAL ───────────────────────────────────────── --}}
        <main class="flex-1 overflow-y-auto" style="padding: 24px 28px;">
            @yield('conteudo')
        </main>

    </div>

    {{-- ── Modal global de eventos CODI ── --}}
    <div id="modal-eventos"
         style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh;
                background:rgba(0,0,0,0.5); z-index:99999;
                align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; width:680px; max-height:78vh;
                    display:flex; flex-direction:column; overflow:hidden;
                    box-shadow:0 20px 60px rgba(0,0,0,0.3);">

            <div style="padding:12px 16px; border-bottom:1px solid #f0f0f0;
                        display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div id="modal-eventos-op" style="font-weight:600; font-size:14px; color:#111;"></div>
                    <div id="modal-eventos-desc" style="font-size:11px; color:#888; margin-top:2px;"></div>
                </div>
                <button onclick="fecharModalEventos()"
                        style="background:none; border:none; cursor:pointer; font-size:22px;
                               color:#aaa; line-height:1; padding:0 0 0 16px;">×</button>
            </div>

            <div id="modal-eventos-corpo" style="overflow-y:auto; flex:1;"></div>

            <div style="padding:8px 16px; border-top:1px solid #f0f0f0;
                        display:flex; justify-content:space-between; align-items:center;
                        background:#f8fafc;">
                <span id="modal-eventos-totais" style="font-size:11px; color:#888;"></span>
                <button onclick="fecharModalEventos()"
                        style="padding:6px 16px; background:#1e293b; color:white;
                               border:none; border-radius:8px; font-size:12px; cursor:pointer;">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <script>
    function abrirModalEventos(op, descricao, eventos) {
        document.getElementById('modal-eventos-op').textContent = 'Eventos — OP ' + op;
        document.getElementById('modal-eventos-desc').textContent = descricao;

        var totalProd = 0, totalPar = 0;
        var minutosProd = 0, minutosPar = 0;

        function fmtMin(m) {
            var h = Math.floor(m / 60);
            var min = m % 60;
            return h + 'h ' + String(min).padStart(2, '0') + 'min';
        }

        var html = '<table style="width:100%;font-size:11px;border-collapse:collapse;">';
        html += '<thead><tr style="background:#1e293b;color:white;position:sticky;top:0;">';
        html += '<th style="padding:6px 12px;text-align:left;font-weight:500;">Tipo</th>';
        html += '<th style="padding:6px 12px;text-align:left;font-weight:500;">Início</th>';
        html += '<th style="padding:6px 12px;text-align:left;font-weight:500;">Fim</th>';
        html += '<th style="padding:6px 12px;text-align:center;font-weight:500;">Duração</th>';
        html += '<th style="padding:6px 12px;text-align:right;font-weight:500;">Qtd</th>';
        html += '</tr></thead><tbody>';

        eventos.forEach(function(e, i) {
            var isProd = e.tipo === 'PRODUCAO';
            isProd ? totalProd++ : totalPar++;
            var durMatch = (e.duracao || '').match(/(\d+)h\s*(\d+)min/);
            if (durMatch) {
                var mins = parseInt(durMatch[1]) * 60 + parseInt(durMatch[2]);
                isProd ? minutosProd += mins : minutosPar += mins;
            }
            var bg = isProd ? (i%2===0 ? '#fff' : '#fafafa') : '#fff5f5';
            var cor = isProd ? '#16a34a' : '#dc2626';
            var labelTipo = isProd
                ? '▶ Produção'
                : ('⏸ ' + (e.nome_parada || 'Parada'));
            var subLabel = '';
            if (!isProd && e.tipo_parada) {
                subLabel = e.tipo_parada;
                if (e.tipo_parada === 'Setup' && e.setup_previsto !== null && e.setup_previsto !== undefined) {
                    var prevH = Math.floor(e.setup_previsto / 60);
                    var prevM = e.setup_previsto % 60;
                    var prevStr = prevH > 0
                        ? prevH + 'h ' + String(prevM).padStart(2, '0') + 'min'
                        : prevM + 'min';
                    subLabel += ' · previsto: ' + prevStr;
                }
            }
            var subTipo = subLabel
                ? '<br><span style="font-size:10px;color:#aaa;font-weight:normal;">' + subLabel + '</span>'
                : '';
            html += '<tr style="background:' + bg + ';border-bottom:1px solid #f5f5f5;">';
            html += '<td style="padding:5px 12px;color:' + cor + ';font-weight:500;line-height:1.3;">' + labelTipo + subTipo + '</td>';
            html += '<td style="padding:5px 12px;color:#555;">' + e.inicio + '</td>';
            html += '<td style="padding:5px 12px;color:#555;">' + e.fim + '</td>';
            html += '<td style="padding:5px 12px;text-align:center;">' + e.duracao + '</td>';
            html += '<td style="padding:5px 12px;text-align:right;color:' +
                    (isProd ? '#16a34a' : '#ccc') + ';font-weight:' +
                    (isProd ? '600' : 'normal') + ';">' + e.quantidade + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        document.getElementById('modal-eventos-corpo').innerHTML = html;
        document.getElementById('modal-eventos-totais').innerHTML =
            '<span style="color:#16a34a;">▶ ' + totalProd + ' prod · ' + fmtMin(minutosProd) + '</span>' +
            '&nbsp;&nbsp;' +
            '<span style="color:#dc2626;">⏸ ' + totalPar + ' parada(s) · ' + fmtMin(minutosPar) + '</span>' +
            '&nbsp;&nbsp;· Total: ' + eventos.length + ' eventos';

        var modal = document.getElementById('modal-eventos');
        modal.style.display = 'flex';
    }

    function fecharModalEventos() {
        document.getElementById('modal-eventos').style.display = 'none';
    }

    document.addEventListener('click', function(e) {
        if (e.target === document.getElementById('modal-eventos')) {
            fecharModalEventos();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fecharModalEventos();
    });
    </script>

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <script type="module">
        // Expõe uma Promise global para que renderizarGantt() possa aguardar
        // a carga do locale sem race condition (evento gantt-atualizado pode
        // disparar antes do import() completar).
        window._localePromise = (async () => {
            try {
                const { ptBR } = await import('https://cdn.jsdelivr.net/npm/date-fns@3/locale/pt-BR.js');
                window.dateFnsPtBR = ptBR;
            } catch (e) {
                window.dateFnsPtBR = null;
            }
        })();
    </script>

</body>
</html>
