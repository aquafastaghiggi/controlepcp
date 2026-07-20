    @php
        $cor    = $linha['cor'] ?? 'gray';
        $barCor = match($cor) {
            'green'  => '#39d353',
            'red'    => '#f85149',
            'orange' => '#f97316',
            'yellow' => '#e3b341',
            default  => '#475569',
        };
        $op  = $linha['op_atual'] ?? null;
        $pct = min(100, $op['pct'] ?? 0);

        // KPIs por LINHA (não por OP)
        // Card 2 "Prev./Dia" e Card 3 "Prev. x Real." só aparecem quando a linha
        // tem programação confirmada + calendário e já produziu algo hoje.
        $projecaoLinha = null;
        $prevDiaStr    = '—';
        $prevXRealStr  = '—';
        $prevXRealCor  = '#8b949e';
        $atrasado      = false;
        $atrasoRitmoMin = 0;

        $codigoRecursoLinha = \Illuminate\Support\Facades\DB::table('linhas')
            ->where('codigo', $linha['codigo'])
            ->value('codigo_recurso');

        // Total produzido pela linha desde 06:00 (todas as OPs)
        $inicioDia6 = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        $prodLinha  = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecursoLinha)
            ->where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioDia6)
            ->sum('quantidade');

        $calendarioId = \Illuminate\Support\Facades\DB::table('calendarios')
            ->where('linha_id', $linha['id'])
            ->value('id');

        $progLinha = \Illuminate\Support\Facades\DB::table('programacoes')
            ->where('linha_id', $linha['id'])
            ->where('status', 'confirmada')
            ->first(['dias_selecionados']);

        if ($prodLinha > 0 && $calendarioId && $progLinha) {
            try {
                // Ritmo atual = total produzido ÷ período total decorrido desde 06:00 (TODOS os
                // eventos — PRODUCAO + PARADA/Intervalo —, não só o tempo produtivo, pra diluir
                // as paradas no ritmo médio e o cálculo fechar redondo com a jornada real).
                $minTrabalhados = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                    ->where('codigo_recurso', $codigoRecursoLinha)
                    ->where('inicio_evento', '>=', $inicioDia6)
                    ->sum('duracao_minutos');

                $horasTrabalhadas = max(0.1, $minTrabalhados / 60);
                $ritmoLinha       = $prodLinha / $horasTrabalhadas;

                $diasSelecionados = json_decode($progLinha->dias_selecionados ?? '[]', true);

                $hoje6  = new \DateTimeImmutable(\Carbon\Carbon::today()->format('Y-m-d') . ' 06:00:00');
                $fimJan = new \DateTimeImmutable(\Carbon\Carbon::today()->addDay()->format('Y-m-d') . ' 03:00:00');

                // O override de dias_selecionados só cobre "hoje" — se algum turno de hoje é
                // overnight (atravessa a meia-noite), amanhã precisa ser explicitamente
                // liberado APENAS para esses turnos overnight (não os diurnos do dia seguinte),
                // senão o CalendarioService pode truncar a parte 00:00→03:00 do turno noturno.
                $diasSelComOvernight = $diasSelecionados;
                $turnosHoje          = $diasSelecionados[$hoje6->format('Y-m-d')]['turnos'] ?? [];

                if (! empty($turnosHoje)) {
                    $turnosOvernightHoje = \Illuminate\Support\Facades\DB::table('intervalos')
                        ->whereIn('id', $turnosHoje)
                        ->where(function ($q) {
                            $q->where('hora_fim', '<=', '06:00:00')
                              ->orWhere('hora_inicio', '>=', '22:00:00');
                        })
                        ->pluck('id')
                        ->toArray();

                    $amanhaStr = $fimJan->format('Y-m-d');

                    if (! empty($turnosOvernightHoje) && ! isset($diasSelComOvernight[$amanhaStr])) {
                        $diasSelComOvernight[$amanhaStr] = [
                            'dia_semana' => (int) $fimJan->format('N'),
                            'turnos'     => $turnosOvernightHoje,
                        ];
                    }
                }

                $calendarioService = app(\App\Services\CalendarioService::class);

                // Capacidade teórica = ritmo atual × horas úteis de turno hoje (06:00→03:00)
                $minJornada        = $calendarioService->minutosUteisEntre($hoje6, $fimJan, $calendarioId, $diasSelComOvernight);
                $horasJornada      = $minJornada / 60;
                $capacidadeTeorica = $ritmoLinha * $horasJornada;

                // Total programado hoje = soma proporcional das OPs confirmadas da linha que
                // cruzam a janela 06:00→03:00 (mesmo rateio usado no TvStaticController)
                $opsLinhaHoje = \Illuminate\Support\Facades\DB::table('itens_programacao as ip')
                    ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                    ->leftJoin('codi_eficiencia as ce', function ($j) {
                        $j->on('ce.numero_op', '=', 'ip.numero_op')
                          ->on('ce.programacao_id', '=', 'p.id');
                    })
                    ->leftJoin('produtos as prod', 'prod.sku', '=', 'ip.sku')
                    ->where('p.linha_id', $linha['id'])
                    ->where('p.status', 'confirmada')
                    ->whereNotNull('ce.inicio_previsto')
                    ->whereNotNull('ce.fim_previsto')
                    ->where('ce.fim_previsto', '>', $hoje6->format('Y-m-d H:i:s'))
                    ->where('ce.inicio_previsto', '<', $fimJan->format('Y-m-d H:i:s'))
                    ->get(['ip.numero_op', 'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto', 'p.eficiencia', 'prod.taxa_por_hora']);

                $somaOpsHoje = 0.0;
                foreach ($opsLinhaHoje as $opRow) {
                    $inicioOp      = new \DateTimeImmutable($opRow->inicio_previsto);
                    $fimOp         = new \DateTimeImmutable($opRow->fim_previsto);
                    $inicioOverlap = $inicioOp < $hoje6 ? $hoje6 : $inicioOp;
                    $fimOverlap    = $fimOp > $fimJan ? $fimJan : $fimOp;

                    if ($fimOverlap <= $inicioOverlap) continue;

                    // NOTA: $diasSelComOvernight (só T4 pra amanhã) é seguro apenas para
                    // $minJornada, cuja janela termina em $fimJan. Aqui $inicioOp/$fimOp
                    // vêm da OP inteira e podem avançar bem além de amanhã (inclusive pelo
                    // turno diurno do dia seguinte) — usar o override restrito suprimiria
                    // T1/T2/T3 de amanhã via override explícito, sem cair no fallback já
                    // corrigido de CalendarioService. Usar o $diasSelecionados original.
                    $minTotal   = $calendarioService->minutosUteisEntre($inicioOp, $fimOp, $calendarioId, $diasSelecionados);
                    $minOverlap = $calendarioService->minutosUteisEntre($inicioOverlap, $fimOverlap, $calendarioId, $diasSelecionados);

                    if ($minTotal <= 0) continue;

                    // Previsto = taxa cadastrada × eficiência da programação × minutos
                    // úteis do overlap, nunca ultrapassando a quantidade da própria OP
                    // (igual ao Histórico/ListaProgramacoes::detalhesLinha()).
                    $taxaPorHora = (float) ($opRow->taxa_por_hora ?? 0);
                    $eficiencia  = (float) ($opRow->eficiencia ?? 100) / 100;
                    if ($taxaPorHora > 0) {
                        $prevCxOp = min((int) $opRow->quantidade, (int) round($taxaPorHora * $eficiencia * $minOverlap / 60));
                    } else {
                        // Fallback para proporção simples se não tiver taxa
                        $prevCxOp = (int) round($opRow->quantidade * ($minOverlap / $minTotal));
                    }
                    $somaOpsHoje += $prevCxOp;
                }

                $numeroOpsConfirmadosLinha = $opsLinhaHoje->pluck('numero_op')->all();

                // Reprogramação durante o dia (ex.: Colemar): a programação antiga foi
                // arquivada e substituída pela confirmada atual — não faz sentido somar
                // o previsto proporcional dela de novo (dobraria o programado do dia).
                // Soma só o que foi REALMENTE produzido no CODI para as OPs dessa
                // programação arquivada que não estão na confirmada atual.
                //
                // Janela desde o último dia útil (pula fim de semana) — não só
                // "hoje" — pra pegar reprogramações feitas na sexta/véspera que
                // ainda afetam o programado de hoje.
                $diaSemana = (int) (new \DateTimeImmutable('now'))->format('N'); // 1=seg, 7=dom
                $inicioUltimoDiaUtil = $diaSemana === 1
                    ? new \DateTimeImmutable(date('Y-m-d', strtotime('-3 days')) . ' 06:00:00')
                    : new \DateTimeImmutable(date('Y-m-d', strtotime('-1 day')) . ' 06:00:00');

                $progsArquivadasHoje = \Illuminate\Support\Facades\DB::table('programacoes')
                    ->where('linha_id', $linha['id'])
                    ->where('status', 'arquivada')
                    ->where('arquivada_em', '>=', $inicioUltimoDiaUtil->format('Y-m-d H:i:s'))
                    ->pluck('id');

                foreach ($progsArquivadasHoje as $progArq) {
                    // Buscar OPs desta programação arquivada
                    $numOpsArq = \Illuminate\Support\Facades\DB::table('itens_programacao')
                        ->where('programacao_id', $progArq)
                        ->whereNotIn('numero_op', $numeroOpsConfirmadosLinha ?? [])
                        ->pluck('numero_op')
                        ->filter()
                        ->values()
                        ->toArray();

                    if (empty($numOpsArq)) continue;

                    // Somar só a produção real dessas OPs no CODI hoje
                    $prodRealArq = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                        ->whereIn('ordem_producao', $numOpsArq)
                        ->where('tipo_evento', 'PRODUCAO')
                        ->where('inicio_evento', '>=', $hoje6->format('Y-m-d H:i:s'))
                        ->sum('quantidade');

                    $somaOpsHoje += $prodRealArq;
                }

                // Total Programado nunca fica menor que o que já foi produzido de
                // verdade hoje — evita mostrar um "programado" abaixo do realizado
                // quando o somaOps proporcional não capturou tudo.
                $somaOpsHoje = $prodLinha + $somaOpsHoje;

                // Sem OP programada na janela de hoje (gap de sincronização ou fila
                // realmente vazia) — não há base confiável para os cards, esconder ambos
                if ($somaOpsHoje <= 0) {
                    $projecaoLinha = null;
                } else {
                    // CARD 2 — Prev./Dia = soma proporcional das OPs programadas para hoje
                    $prevDia = (int) round($somaOpsHoje);

                    // CARD 3 — Prev. x Real. = capacidade teórica (ritmo) - programado hoje
                    // Positivo = ritmo dá pra produzir mais que o programado (sobra/confortável)
                    // Negativo = ritmo não dá conta do programado (risco)
                    $prevXRealVal = (int) round($capacidadeTeorica - $somaOpsHoje);

                    $projecaoLinha = $prevDia;
                    $prevDiaStr    = number_format($prevDia, 0, ',', '.');
                    $prevXRealStr  = ($prevXRealVal > 0 ? '+' : '') . number_format($prevXRealVal, 0, ',', '.');
                    // Negativo = linha produz a menos que o programado (atrasada); positivo = produz a mais (sobra)
                    $prevXRealCor  = $prevXRealVal < 0 ? '#f85149' : ($prevXRealVal > 0 ? '#39d353' : '#8b949e');
                    $atrasado      = $prevXRealVal < 0;

                    // Tempo equivalente de atraso no ritmo: quanto o déficit de caixas representa em minutos no ritmo atual
                    if ($atrasado && $ritmoLinha > 0) {
                        $atrasoRitmoMin = (int) round((abs($prevXRealVal) / $ritmoLinha) * 60);
                        $atrasoRitmoMin = min($atrasoRitmoMin, 999); // máximo 16h39 — valores maiores são artefatos de ritmo baixo
                    }
                }
            } catch (\Throwable $e) {
                $projecaoLinha = null;
            }
        }

        $temParada = str_contains($linha['estado'] ?? '', 'Parada')
                  || !empty($linha['parada_aberta'])
                  || in_array($linha['codigo'], $linhasComParada);

        // Classificação (Parada Programada/Intervalo/Troca de Kit/Troca de
        // Líquido/Desconexão/Atrasada por ritmo) já vem pronta de
        // AcompanharProducao::reclassificarComSinaisTempoReal() — fonte única
        // compartilhada com o dashboard "Acompanhar Produção". Antes essa
        // lógica era re-derivada aqui de novo (consultando codi_eventos
        // direto), o que fazia a TV e o dashboard divergirem pra mesma linha.
        $ehParadaProgramada = $linha['estado'] === 'Parada Programada';
        $ehIntervalo        = $linha['estado'] === 'Intervalo';
        $ehTrocaKit         = $linha['estado'] === 'Troca de Kit';
        $ehTrocaLiquido     = $linha['estado'] === 'Troca de Líquido';
        $ehDesconexao       = $linha['estado'] === 'Desconexão';
        $opAtrasada         = $linha['cor'] === 'red' && $linha['estado'] === 'Atrasada';

        // Cronômetro de troca — só o tempo decorrido da parada atual (exibição
        // visual), não influencia a classificação acima.
        $tempoTrocaMin = 0;
        if ($ehTrocaKit || $ehTrocaLiquido) {
            $inicioUltimaParada = \Illuminate\Support\Facades\DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecursoLinha)
                ->where('tipo_evento', 'PARADA')
                ->orderByDesc('inicio_evento')
                ->value('inicio_evento');

            if ($inicioUltimaParada) {
                $tempoTrocaMin = (int) \Carbon\Carbon::parse($inicioUltimaParada)->diffInMinutes(now());
            }
        }
        $tempoTrocaHhmm = str_pad(intdiv($tempoTrocaMin, 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad($tempoTrocaMin % 60, 2, '0', STR_PAD_LEFT);

        $pulsingClass = '';
        if ($temParada && !$ehParadaProgramada) {
            $pulsingClass = $linha['cor'] === 'orange' ? 'pulsing-orange' : 'pulsing-red';
        }
    @endphp
    @php
        // --- Mapeamento para a nova estrutura visual dos cards ---

        // Número da linha (ex.: "2" extraído do código "LN02") — variável separada
        // do $linha do loop (que é o array inteiro), pra não quebrar a lógica acima
        $linhaNumero = ltrim(preg_replace('/\D+/', '', $linha['codigo'] ?? ''), '0');
        if ($linhaNumero === '') { $linhaNumero = '0'; }

        $statusCorMapa = [
            'green'  => 'verde',
            'red'    => 'vermelho',
            'orange' => 'laranja',
            'yellow' => 'amarelo',
            'blue'   => 'azul',
            'gray'   => 'cinza',
        ];

        // Prioridade: Parada Programada > Intervalo > Troca de Kit > Troca de Líquido > Desconexão > Atrasada (ritmo) > cor da linha
        $statusClasse = $ehParadaProgramada
            ? 'amarelo'
            : ($ehIntervalo
                ? 'azul'
                : ($ehTrocaKit
                    ? 'laranja'
                    : ($ehTrocaLiquido
                        ? 'laranja'
                        : ($ehDesconexao
                            ? 'preto'
                            : ($opAtrasada
                                ? 'vermelho'
                                : ($statusCorMapa[$cor] ?? 'cinza'))))));

        $statusTexto = $ehParadaProgramada
            ? 'Parada'
            : ($ehIntervalo
                ? 'Intervalo'
                : ($ehTrocaKit
                    ? 'Troca de Kit'
                    : ($ehTrocaLiquido
                        ? 'Troca de Líquido'
                        : ($ehDesconexao
                            ? 'Desconexão'
                            : ($opAtrasada
                                ? 'Atrasada'
                                : $linha['estado'])))));

        // Classe do status-pill (componente próprio, independente da cor do card)
        $statusPillMapa = [
            'verde'    => 'em-dia',
            'vermelho' => 'atrasada',
            'laranja'  => 'troca-kit',
            'amarelo'  => 'parada',
            'azul'     => 'intervalo',
            'cinza'    => 'em-dia',
            'preto'    => 'desconexao',
        ];
        $statusPillClasse = $statusPillMapa[$statusClasse] ?? 'em-dia';

        // Possível erro de apontamento: produzido da OP atual acima do
        // programado sugere que o CODI está somando produção de outra
        // OP/lote no apontamento desta. $op['programado']/$op['realizado'] já são
        // os campos usados no card (meta e "número grande") — não existem
        // $op['quantidade'] nem $linha['produzido'].
        $qtdOp       = (int) ($op['programado'] ?? 0);
        $produzidoOp = (int) ($op['realizado'] ?? 0);
        $possivelErroApontamento = $qtdOp > 0 && $produzidoOp > $qtdOp;

        // Condição 1: divergência registrada pelo comando pcp:verificar-divergencias
        // (OP rodando no CODI que não está na programação confirmada da linha)
        $temDivergenciaOp = \Illuminate\Support\Facades\DB::table('divergencias_op')
            ->where('modulo', 'envase')
            ->where('linha_codigo', $linha['codigo'])
            ->whereNull('resolvida_em')
            ->exists();

        // Condição 2: produção da OP atual ultrapassou a quantidade programada
        // (erro de apontamento — mesmo gatilho do triângulo amarelo)
        $temDivergencia = $temDivergenciaOp || $possivelErroApontamento;

        $fotoProduto = \Illuminate\Support\Facades\DB::table('produtos')
            ->where('sku', $op['sku'] ?? '')
            ->value('foto');

        $imagemProduto = $fotoProduto
            ? asset('fotos-produtos/' . $fotoProduto)
            : asset('images/aquafast-logo.svg');

        // Embalagens de 1,5L são visualmente mais altas que as demais — aplica
        // uma classe pra reduzir levemente o tamanho e nivelar com as outras
        $ehEmbalagem15L = (bool) preg_match('/1[.,]5\s*l\b/i', $op['descricao'] ?? '');

        // Galões de 5L, pulverizadores e o amaciante concentrado também são
        // mais altos e estouram o topo do card — reduz um pouco só pra esses
        $ehGarrafao5L    = (bool) preg_match('/(?<![\d.,])5\s*l\b/i', $op['descricao'] ?? '');
        $ehPulverizador  = (bool) preg_match('/pulverizador/i', $op['descricao'] ?? '');
        $ehAmacianteConc = (bool) preg_match('/amac\.?\s*conc\.?|amaciante\s+concentrad/i', $op['descricao'] ?? '');
        $ehProdutoAlto   = !$ehEmbalagem15L && ($ehGarrafao5L || $ehPulverizador || $ehAmacianteConc);

        $oeeReal  = $linha['oee_tempo_real']['oee']            ?? null;
        $dispReal = $linha['oee_tempo_real']['disponibilidade'] ?? null;
        $perfReal = $linha['oee_tempo_real']['performance']     ?? null;
        $oeeClasse  = is_null($oeeReal)  ? 'neutro' : ($oeeReal  >= 75 ? 'verde' : ($oeeReal  >= 60 ? 'amarelo' : 'vermelho'));
        $dispClasse = is_null($dispReal) ? 'neutro' : ($dispReal >= 85 ? 'verde' : ($dispReal >= 70 ? 'amarelo' : 'vermelho'));
        $perfClasse = is_null($perfReal) ? 'neutro' : ($perfReal >= 85 ? 'verde' : ($perfReal >= 70 ? 'amarelo' : 'vermelho'));

        $prevRealClasse = $projecaoLinha === null
            ? 'neutro'
            : ($prevXRealVal > 0 ? 'verde' : ($prevXRealVal < 0 ? 'vermelho' : 'neutro'));

        // Aliases dos valores já calculados acima pros nomes de variável da nova estrutura
        $produzido       = number_format($op['realizado'] ?? 0, 0, ',', '.');
        $meta            = number_format($op['programado'] ?? 0, 0, ',', '.');
        $percentual      = $pct . '%';
        $percentualBarra = $pct;
        $totalDia        = number_format($linha['total_hoje'] ?? 0, 0, ',', '.');
        $prevDiaExibicao = $prevDiaStr;
        $prevReal        = $prevXRealStr;
        $disponibilidade = !is_null($dispReal) ? number_format($dispReal,1,',','.').'%' : '—';
        $performance     = !is_null($perfReal) ? number_format($perfReal,1,',','.').'%' : '—';
        $oee             = !is_null($oeeReal)  ? number_format($oeeReal,1,',','.').'%'  : '—';
    @endphp
    <div class="linha-card {{ $statusClasse }} {{ $pulsingClass }} {{ $ehParadaProgramada ? 'em-pausa' : ($ehIntervalo ? 'em-intervalo' : ($ehTrocaKit ? 'em-troca-kit' : ($ehTrocaLiquido ? 'em-troca-liquido' : ($ehDesconexao ? 'em-desconexao' : '')))) }} {{ $temDivergencia ? 'tem-divergencia' : '' }} {{ $ehEmbalagem15L ? 'produto-15l-card' : '' }}">
        <div class="status-pill {{ $statusPillClasse }}">
            <span class="pill-label">{{ $statusTexto }}</span>
            @if($temDivergencia)
                <span class="divergencia-icon" title="OP rodando sem programação">⚠</span>
            @endif
        </div>
        <div class="linha-nome">LINHA {{ $linhaNumero }}</div>
        @if($ehParadaProgramada)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">⏸</div>
            <div style="font-size:26px;font-weight:800;color:#e3b341;letter-spacing:1px;text-transform:uppercase;">Parada Programada</div>
        </div>
        @elseif($ehIntervalo)
        <div class="status-overlay">
            <img src="{{ asset('images/aquafast-logo.svg') }}" style="max-height:60px;max-width:140px;object-fit:contain;filter:brightness(0) invert(1);opacity:0.9;margin-bottom:14px;" alt="">
            <div style="font-size:26px;font-weight:800;color:#58a6ff;letter-spacing:1px;text-transform:uppercase;">Intervalo</div>
        </div>
        @elseif($ehTrocaKit)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🔧</div>
            <div style="font-size:26px;font-weight:800;color:#ff8c00;letter-spacing:1px;text-transform:uppercase;">Troca de Kit</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px;color:#ffb347;font-size:20px;font-weight:700;">
                <span style="font-size:22px;">⏱</span>
                <span class="cronometro-troca" data-minutos="{{ $tempoTrocaMin }}">{{ $tempoTrocaHhmm }}</span>
            </div>
        </div>
        @elseif($ehTrocaLiquido)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🧴</div>
            <div style="font-size:26px;font-weight:800;color:#ff8c00;letter-spacing:1px;text-transform:uppercase;">Troca de Líquido</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px;color:#ffb347;font-size:20px;font-weight:700;">
                <span style="font-size:22px;">⏱</span>
                <span class="cronometro-troca" data-minutos="{{ $tempoTrocaMin }}">{{ $tempoTrocaHhmm }}</span>
            </div>
        </div>
        @elseif($ehDesconexao)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">⚡</div>
            <div style="font-size:26px;font-weight:800;color:#c0c0c0;letter-spacing:1px;text-transform:uppercase;">Desconexão</div>
        </div>
        @endif
        <div class="card-content">
            <div class="linha-topo">
                <div class="linha-info">
                    @if($op)
                        <div class="op-info">OP {{ $op['numero_op'] }}</div>
                        <div class="produto">{{ $op['descricao'] }}</div>
                        @php
                            // Atraso de início da OP tem prioridade; se não houver, usa o equivalente
                            // em tempo do déficit de ritmo (Card "Prev. x Real." negativo)
                            $aMin = ($op['atraso_inicio_min'] ?? 0) > 0
                                ? (int) $op['atraso_inicio_min']
                                : ($opAtrasada ? $atrasoRitmoMin : 0);
                            $aHhmm = $aMin > 0
                                ? intdiv($aMin,60).':'.str_pad($aMin%60,2,'0',STR_PAD_LEFT)
                                : null;
                        @endphp
                    @else
                        <div class="op-info" style="margin-top:16px;">Aguardando início</div>
                    @endif
                </div>
                <div class="produto-coluna">
                    <div class="produto-img-wrap">
                        <img class="produto-img {{ ($fotoProduto ?? null) ? '' : 'produto-img-placeholder' }} {{ $ehEmbalagem15L ? 'produto-15l' : ($ehProdutoAlto ? 'produto-alto' : '') }}"
                             src="{{ $imagemProduto }}" alt="{{ $op['descricao'] ?? '' }}">
                    </div>
                    @if($op && ($aMin ?? 0) > 0)
                        <div class="atraso">{{ $aHhmm }} de atraso</div>
                    @endif
                </div>
            </div>
            <div class="bloco-inferior">
                <div class="indicadores indicadores-meio">
                    <div class="indicador">
                        <div class="indicador-label">DISPONIBILIDADE</div>
                        <div class="indicador-valor {{ $dispClasse }}">{{ $disponibilidade }}</div>
                    </div>
                    <div class="indicador">
                        <div class="indicador-label">PERFORMANCE</div>
                        <div class="indicador-valor {{ $perfClasse }}">{{ $performance }}</div>
                    </div>
                    <div class="indicador oee-card">
                        <div class="indicador-label">OEE</div>
                        <div class="indicador-valor {{ $oeeClasse }}">{{ $oee }}</div>
                    </div>
                </div>
                @if($op)
                <div class="producao-area">
                    <div class="producao-main">
                        <span class="cx-valor">{{ $produzido }}</span>
                        <span class="cx-meta">
                            @if(($op['programado'] ?? 0) > 0)
                                / {{ $meta }} cx <span>•</span> {{ $percentual }}@if($possivelErroApontamento) <span class="triangulo-apontamento">⚠</span>@endif
                            @else
                                cx
                            @endif
                        </span>
                    </div>
                    <div class="barra-wrap">
                        <div class="barra" style="width: {{ $percentualBarra }}%;background:{{ $barCor }};"></div>
                    </div>
                </div>
                @endif
                <div class="totais-inferiores">
                    <div class="total-box">
                        <div class="total-label">Total produzido</div>
                        <div class="total-valor">{{ $totalDia }}</div>
                        <div class="total-meta">cx produzidas</div>
                    </div>
                    @if($projecaoLinha !== null)
                    <div class="total-box">
                        <div class="total-label">Total Programado</div>
                        <div class="total-valor">{{ $prevDiaExibicao }}</div>
                        <div class="total-meta">cx programadas</div>
                    </div>
                    <div class="total-box">
                        <div class="total-label">Previsto x Realizado</div>
                        <div class="total-valor {{ $prevRealClasse }}">{{ $prevReal }}</div>
                        <div class="total-meta">diferença</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>{{-- card-content --}}
    </div>
