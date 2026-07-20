    @php
        $cor    = $maquina['cor'] ?? 'gray';
        $barCor = match($cor) {
            'green'  => '#39d353',
            'red'    => '#f85149',
            'orange' => '#f97316',
            'yellow' => '#e3b341',
            default  => '#475569',
        };
        $op  = $maquina['op_atual'] ?? null;
        $pct = min(100, $op['pct'] ?? 0);

        // KPIs por MÁQUINA (não por OP)
        // Card 2 "Prev./Dia" e Card 3 "Prev. x Real." só aparecem quando a máquina
        // tem programação confirmada + calendário e já produziu algo hoje.
        $projecaoLinha = null;
        $prevDiaStr    = '—';
        $prevXRealStr  = '—';
        $prevXRealCor  = '#8b949e';
        $atrasado      = false;
        $atrasoRitmoMin = 0;

        $codigoRecursoLinha = $maquina['codigo_recurso'] ?? null;

        // Total produzido pela máquina desde 06:30 (todas as OPs) — janela Sopro
        // rolante de 24h (06:30→06:30 do dia seguinte).
        $inicioDia6h30 = \Carbon\Carbon::today()->setHour(6)->setMinute(30)->setSecond(0);
        if (\Carbon\Carbon::now()->lt($inicioDia6h30)) {
            $inicioDia6h30 = $inicioDia6h30->copy()->subDay();
        }
        $prodLinha  = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecursoLinha)
            ->where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioDia6h30)
            ->sum('quantidade');

        $calendarioId = \Illuminate\Support\Facades\DB::table('calendarios_sopro')
            ->where('maquina_id', $maquina['id'])
            ->value('id');

        $progLinha = \Illuminate\Support\Facades\DB::table('programacoes_sopro')
            ->where('maquina_id', $maquina['id'])
            ->where('status', 'confirmada')
            ->first(['dias_selecionados']);

        if ($prodLinha > 0 && $calendarioId && $progLinha) {
            try {
                // Ritmo atual = total produzido ÷ período total decorrido desde 06:30
                // (TODOS os eventos — PRODUCAO + PARADA/Intervalo —, não só o tempo
                // produtivo, pra diluir as paradas no ritmo médio).
                $minTrabalhados = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                    ->where('codigo_recurso', $codigoRecursoLinha)
                    ->where('inicio_evento', '>=', $inicioDia6h30)
                    ->sum('duracao_minutos');

                $horasTrabalhadas = max(0.1, $minTrabalhados / 60);
                $ritmoLinha       = $prodLinha / $horasTrabalhadas;

                $diasSelecionados = json_decode($progLinha->dias_selecionados ?? '[]', true);

                $hoje630 = new \DateTimeImmutable($inicioDia6h30->format('Y-m-d H:i:s'));
                $fimJan  = new \DateTimeImmutable($inicioDia6h30->copy()->addDay()->format('Y-m-d H:i:s'));

                // O override de dias_selecionados só cobre "hoje" — se algum turno de
                // hoje é overnight (ex.: T3 21:30→06:30, cruza a meia-noite), amanhã
                // precisa ser explicitamente liberado APENAS para esses turnos
                // overnight, senão o CalendarioSoproService pode truncar a parte
                // 00:00→06:30 do turno noturno. Turnos ADM nunca entram aqui.
                $diasSelComOvernight = $diasSelecionados;
                $turnosHoje          = $diasSelecionados[$hoje630->format('Y-m-d')]['turnos'] ?? [];

                if (! empty($turnosHoje)) {
                    $turnosOvernightHoje = \Illuminate\Support\Facades\DB::table('intervalos_sopro')
                        ->whereIn('id', $turnosHoje)
                        ->where('nome', 'not like', '%ADM%')
                        ->whereColumn('hora_fim', '<=', 'hora_inicio')
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

                $calendarioService = app(\App\Services\CalendarioSoproService::class);

                // Capacidade teórica = ritmo atual × horas úteis de turno hoje (06:30→06:30)
                $minJornada        = $calendarioService->minutosUteisEntre($hoje630, $fimJan, $calendarioId, $diasSelComOvernight);
                $horasJornada      = $minJornada / 60;
                $capacidadeTeorica = $ritmoLinha * $horasJornada;

                // Total programado hoje = soma proporcional das OPs confirmadas da
                // máquina que cruzam a janela 06:30→06:30 (mesmo rateio usado no
                // TvStaticSoproController)
                $opsLinhaHoje = \Illuminate\Support\Facades\DB::table('itens_programacao_sopro as ip')
                    ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
                    ->leftJoin('codi_eficiencia_sopro as ce', function ($j) {
                        $j->on('ce.numero_op', '=', 'ip.numero_op')
                          ->on('ce.programacao_sopro_id', '=', 'p.id');
                    })
                    ->leftJoin('frascos as frs', \Illuminate\Support\Facades\DB::raw('frs.sku COLLATE utf8mb4_unicode_ci'), '=', 'ip.sku')
                    ->where('p.maquina_id', $maquina['id'])
                    ->where('p.status', 'confirmada')
                    ->whereNotNull('ce.inicio_previsto')
                    ->whereNotNull('ce.fim_previsto')
                    ->where('ce.fim_previsto', '>', $hoje630->format('Y-m-d H:i:s'))
                    ->where('ce.inicio_previsto', '<', $fimJan->format('Y-m-d H:i:s'))
                    ->get(['ip.numero_op', 'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto', 'p.eficiencia', 'frs.taxa_por_hora']);

                $somaOpsHoje = 0.0;
                foreach ($opsLinhaHoje as $opRow) {
                    $inicioOp      = new \DateTimeImmutable($opRow->inicio_previsto);
                    $fimOp         = new \DateTimeImmutable($opRow->fim_previsto);
                    $inicioOverlap = $inicioOp < $hoje630 ? $hoje630 : $inicioOp;
                    $fimOverlap    = $fimOp > $fimJan ? $fimJan : $fimOp;

                    if ($fimOverlap <= $inicioOverlap) continue;

                    // NOTA: $diasSelComOvernight (só T3 pra amanhã) é seguro apenas
                    // para $minJornada, cuja janela termina em $fimJan. Aqui
                    // $inicioOp/$fimOp vêm da OP inteira e podem avançar bem além de
                    // amanhã — usar o $diasSelecionados original evita suprimir
                    // turnos diurnos do dia seguinte.
                    $minTotal   = $calendarioService->minutosUteisEntre($inicioOp, $fimOp, $calendarioId, $diasSelecionados);
                    $minOverlap = $calendarioService->minutosUteisEntre($inicioOverlap, $fimOverlap, $calendarioId, $diasSelecionados);

                    if ($minTotal <= 0) continue;

                    // Previsto = taxa cadastrada × eficiência da programação × minutos
                    // úteis do overlap, nunca ultrapassando a quantidade da própria OP.
                    // itens_programacao_sopro.quantidade vem em milheiros — ×1000.
                    $taxaPorHora = (float) ($opRow->taxa_por_hora ?? 0);
                    $eficiencia  = (float) ($opRow->eficiencia ?? 100) / 100;
                    $quantidadeRealOp = (float) $opRow->quantidade * 1000;
                    if ($taxaPorHora > 0) {
                        $prevCxOp = min((int) $quantidadeRealOp, (int) round($taxaPorHora * $eficiencia * $minOverlap / 60));
                    } else {
                        $prevCxOp = (int) round($quantidadeRealOp * ($minOverlap / $minTotal));
                    }
                    $somaOpsHoje += $prevCxOp;
                }

                $numeroOpsConfirmadosLinha = $opsLinhaHoje->pluck('numero_op')->all();

                // Reprogramação durante o dia: soma proporcional das OPs de
                // programações arquivadas HOJE que ainda cruzam a janela
                // 06:30→06:30 (mesmo rateio da confirmada). Não conta quem já está
                // na confirmada — evita duplicar.
                $numeroOpsSomadosArquivadaProporcional = [];

                $progsArquivadasHoje = \Illuminate\Support\Facades\DB::table('programacoes_sopro')
                    ->where('maquina_id', $maquina['id'])
                    ->where('status', 'arquivada')
                    ->where('arquivada_em', '>=', $inicioDia6h30)
                    ->pluck('id');

                foreach ($progsArquivadasHoje as $progArqId) {
                    $progArq = \Illuminate\Support\Facades\DB::table('programacoes_sopro')->where('id', $progArqId)->first(['dias_selecionados', 'eficiencia']);
                    $diasSelArq = json_decode($progArq->dias_selecionados ?? '[]', true);
                    $eficienciaArq = max(0.0, (float) $progArq->eficiencia) / 100;

                    $opsArq = \Illuminate\Support\Facades\DB::table('itens_programacao_sopro as ip')
                        ->join('codi_eficiencia_sopro as ce', function ($j) use ($progArqId) {
                            $j->on('ce.numero_op', '=', 'ip.numero_op')
                              ->where('ce.programacao_sopro_id', '=', $progArqId);
                        })
                        ->leftJoin('frascos as frs', \Illuminate\Support\Facades\DB::raw('frs.sku COLLATE utf8mb4_unicode_ci'), '=', 'ip.sku')
                        ->where('ip.programacao_sopro_id', $progArqId)
                        ->where('ce.fim_previsto', '>', $hoje630->format('Y-m-d H:i:s'))
                        ->where('ce.inicio_previsto', '<', $fimJan->format('Y-m-d H:i:s'))
                        ->select('ip.numero_op', 'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto', 'frs.taxa_por_hora')
                        ->get();

                    foreach ($opsArq as $opArq) {
                        if (in_array($opArq->numero_op, $numeroOpsConfirmadosLinha, true)) continue;

                        $iniArq     = new \DateTimeImmutable($opArq->inicio_previsto);
                        $fimArq     = new \DateTimeImmutable($opArq->fim_previsto);
                        $iniCalcArq = $iniArq < $hoje630 ? $hoje630 : $iniArq;
                        $fimCalcArq = $fimArq > $fimJan ? $fimJan : $fimArq;
                        if ($fimCalcArq <= $iniCalcArq) continue;

                        $minTotalArq   = $calendarioService->minutosUteisEntre($iniArq, $fimArq, $calendarioId, $diasSelArq);
                        $minOverlapArq = $calendarioService->minutosUteisEntre($iniCalcArq, $fimCalcArq, $calendarioId, $diasSelArq);
                        if ($minTotalArq <= 0) continue;

                        // itens_programacao_sopro.quantidade vem em milheiros — ×1000.
                        $taxaPorHoraArq = (float) ($opArq->taxa_por_hora ?? 0);
                        $quantidadeRealArq = (float) $opArq->quantidade * 1000;
                        if ($taxaPorHoraArq > 0) {
                            $prevCxOpArq = min((int) $quantidadeRealArq, (int) round($taxaPorHoraArq * $eficienciaArq * $minOverlapArq / 60));
                        } else {
                            $prevCxOpArq = (int) round($quantidadeRealArq * ($minOverlapArq / $minTotalArq));
                        }
                        $somaOpsHoje += $prevCxOpArq;
                        $numeroOpsSomadosArquivadaProporcional[] = $opArq->numero_op;
                    }
                }

                // Máquina reprogramada hoje: a OP rodou e terminou sob a programação
                // antiga, que foi arquivada — soma o que foi REALMENTE produzido
                // dessas OPs desde 06:30, sem duplicar.
                $itensArquivadosLinha = \Illuminate\Support\Facades\DB::table('itens_programacao_sopro as ip')
                    ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
                    ->where('p.maquina_id', $maquina['id'])
                    ->where('p.status', 'arquivada')
                    ->where('p.arquivada_em', '>=', $inicioDia6h30)
                    ->pluck('ip.numero_op')
                    ->unique();

                foreach ($itensArquivadosLinha as $numeroOpArquivada) {
                    if (in_array($numeroOpArquivada, $numeroOpsConfirmadosLinha, true)) continue;
                    if (in_array($numeroOpArquivada, $numeroOpsSomadosArquivadaProporcional, true)) continue;

                    $somaOpsHoje += (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                        ->where('codigo_recurso', $codigoRecursoLinha)
                        ->where('ordem_producao', $numeroOpArquivada)
                        ->where('tipo_evento', 'PRODUCAO')
                        ->where('inicio_evento', '>=', $inicioDia6h30)
                        ->sum('quantidade');
                }

                // Sem OP programada na janela de hoje — não há base confiável para
                // os cards, esconder ambos
                if ($somaOpsHoje <= 0) {
                    $projecaoLinha = null;
                } else {
                    // CARD 2 — Prev./Dia = soma proporcional das OPs programadas para hoje
                    $prevDia = (int) round($somaOpsHoje);

                    // CARD 3 — Prev. x Real. = capacidade teórica (ritmo) - programado hoje
                    $prevXRealVal = (int) round($capacidadeTeorica - $somaOpsHoje);

                    $projecaoLinha = $prevDia;
                    $prevDiaStr    = number_format($prevDia, 0, ',', '.');
                    $prevXRealStr  = ($prevXRealVal > 0 ? '+' : '') . number_format($prevXRealVal, 0, ',', '.');
                    $prevXRealCor  = $prevXRealVal < 0 ? '#f85149' : ($prevXRealVal > 0 ? '#39d353' : '#8b949e');
                    $atrasado      = $prevXRealVal < 0;

                    if ($atrasado && $ritmoLinha > 0) {
                        $atrasoRitmoMin = (int) round((abs($prevXRealVal) / $ritmoLinha) * 60);
                        $atrasoRitmoMin = min($atrasoRitmoMin, 999);
                    }
                }
            } catch (\Throwable $e) {
                $projecaoLinha = null;
            }
        }

        $temParada = str_contains($maquina['status'] ?? '', 'Parada')
                  || !empty($maquina['parada_aberta'])
                  || in_array($maquina['codigo'], $maquinasComParada);

        // Se a OP atual começou dentro da própria janela prevista (inicio_previsto →
        // fim_previsto), a máquina não é considerada atrasada — mesmo que o ritmo do
        // dia (Prev. x Real.) esteja negativo no momento.
        $opAtualNumero    = $op['numero_op'] ?? null;
        $inicioRealOp     = null;
        $inicioPrevistoOp = null;
        $fimPrevistoOp    = null;

        if ($opAtualNumero) {
            $eventoReal = \Illuminate\Support\Facades\DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecursoLinha)
                ->where('ordem_producao', $opAtualNumero)
                ->where('tipo_evento', 'PRODUCAO')
                ->min('inicio_evento');

            $previstoOp = \Illuminate\Support\Facades\DB::table('codi_eficiencia_sopro')
                ->where('numero_op', $opAtualNumero)
                ->select('inicio_previsto', 'fim_previsto')
                ->first();

            $inicioRealOp     = $eventoReal ? new \Carbon\Carbon($eventoReal) : null;
            $inicioPrevistoOp = $previstoOp ? new \Carbon\Carbon($previstoOp->inicio_previsto) : null;
            $fimPrevistoOp    = $previstoOp ? new \Carbon\Carbon($previstoOp->fim_previsto) : null;
        }

        $inicioDentroDoPlano = $inicioRealOp && $inicioPrevistoOp && $fimPrevistoOp
            && $inicioRealOp->between($inicioPrevistoOp, $fimPrevistoOp);

        $opAtrasada = $inicioDentroDoPlano ? false : (isset($atrasado) && $atrasado);

        $nomeParada = $maquina['parada_aberta']['nome'] ?? '';

        // Último evento da máquina (qualquer tipo) para saber o estado atual real
        $ultimoEvento = \Illuminate\Support\Facades\DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecursoLinha)
            ->orderByDesc('inicio_evento')
            ->first(['tipo_evento', 'dados_raw', 'fim_evento', 'inicio_evento']);

        // Desconexão automática: sem evento nos últimos 15 minutos (o sync CODI
        // roda a cada 10 min — 5 min gerava falso positivo a cada ciclo lento).
        $agoraTs = now();
        $ultimoEventoTs = $ultimoEvento
            ? \Carbon\Carbon::parse($ultimoEvento->fim_evento ?? $ultimoEvento->inicio_evento)
            : null;
        $semSinalHa15min = $ultimoEventoTs === null
            || $ultimoEventoTs->diffInMinutes($agoraTs) >= 15;

        // Se último evento foi PRODUCAO com fim_evento null (ainda rodando), não é desconexão
        $ultimoEventoAberto = $ultimoEvento
            && $ultimoEvento->tipo_evento === 'PRODUCAO'
            && $ultimoEvento->fim_evento === null;

        $desconexaoAutomatica = $semSinalHa15min && !$ultimoEventoAberto;

        // Só considera parada se o último evento for realmente uma PARADA
        $ultimaParada = ($ultimoEvento && $ultimoEvento->tipo_evento === 'PARADA') ? $ultimoEvento : null;

        $ehParadaProgramada = false;
        $ehIntervalo = false;
        if ($ultimaParada) {
            $raw = is_array($ultimaParada->dados_raw) ? $ultimaParada->dados_raw : json_decode($ultimaParada->dados_raw, true);
            $nomeUltimaParada = $raw['parada']['nomeParada'] ?? '';
            $tipoUltimaParada = $raw['parada']['tipoParada']['nomeTipoParada'] ?? '';
            $ehParadaProgramada = stripos($nomeUltimaParada, 'PARADA PROGRAMADA') !== false;
            $ehIntervalo = stripos($tipoUltimaParada, 'Intervalo') !== false;
            if ($ehParadaProgramada) $nomeParada = $nomeUltimaParada;
        }

        // Estados especiais do Sopro: Troca de Cor (era Troca de Kit no Envase) e
        // Troca de Molde (era Troca de Líquido) — reaproveitam o mesmo bucket
        // visual/CSS ("laranja"/"troca-kit"), só mudam texto e ícone.
        $ehTrocaCor = false;
        $ehTrocaMolde = false;
        if ($ultimaParada) {
            $ehTrocaCor   = stripos($nomeUltimaParada, 'TROCA DE COR') !== false;
            $ehTrocaMolde = stripos($nomeUltimaParada, 'TROCA DE MOLDE') !== false;
        }
        // Desconexão: automática por timeout de 15 min OU evento explícito de
        // PARADA com "DESCONEX" no nome.
        $ehDesconexao = $desconexaoAutomatica
            || ($ultimaParada && stripos($nomeUltimaParada, 'DESCONEX') !== false);

        // Paradas longas (> 10 min, Micro Parada > 15 min) — overlay especial
        $ehManutencao = false;
        $ehFaltaSilos = false;
        $ehMicroParada = false;
        $ehParadaLonga = false;

        if ($ultimaParada && !$ehParadaProgramada && !$ehIntervalo && !$ehTrocaCor && !$ehTrocaMolde && !$ehDesconexao) {
            $nomeParadaUpper = strtoupper($nomeUltimaParada ?? '');
            $inicioParadaLonga = \Carbon\Carbon::parse($ultimaParada->inicio_evento);
            $duracaoMin = $inicioParadaLonga->diffInMinutes(now());

            if ($duracaoMin >= 10) {
                $ehManutencao = str_contains($nomeParadaUpper, 'MANUTENCAO') || str_contains($nomeParadaUpper, 'MANUTENÇÃO');
                $ehFaltaSilos = str_contains($nomeParadaUpper, 'FALTA DE SILOS') || str_contains($nomeParadaUpper, 'SILO');
            }

            // MICRO_PARADA (nome literal gravado pelo CODI) só vira overlay depois
            // de 15 min — abaixo disso é uma micro-parada normal, não amostra.
            if ($duracaoMin >= 15) {
                $ehMicroParada = str_contains($nomeParadaUpper, 'MICRO_PARADA') || str_contains($nomeParadaUpper, 'MICRO PARADA');
            }

            $ehParadaLonga = $ehManutencao || $ehFaltaSilos || $ehMicroParada;
        }

        $tempoTrocaMin = 0;
        if (($ehTrocaCor || $ehTrocaMolde || $ehManutencao || $ehFaltaSilos || $ehMicroParada) && $ultimaParada) {
            $inicioParada = new \Carbon\Carbon($ultimaParada->inicio_evento);
            $tempoTrocaMin = (int) $inicioParada->diffInMinutes(now());
        }
        $tempoTrocaHhmm = str_pad(intdiv($tempoTrocaMin, 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad($tempoTrocaMin % 60, 2, '0', STR_PAD_LEFT);

        $pulsingClass = '';
        if ($temParada && !$ehParadaProgramada) {
            $pulsingClass = $maquina['cor'] === 'orange' ? 'pulsing-orange' : 'pulsing-red';
        }
    @endphp
    @php
        // --- Mapeamento para a estrutura visual dos cards ---

        // Número da máquina (ex.: "1" extraído do código "MAQ01")
        $maquinaNumero = ltrim(preg_replace('/\D+/', '', $maquina['codigo'] ?? ''), '0');
        if ($maquinaNumero === '') { $maquinaNumero = '0'; }

        $statusCorMapa = [
            'green'  => 'verde',
            'red'    => 'vermelho',
            'orange' => 'laranja',
            'yellow' => 'amarelo',
            'blue'   => 'azul',
            'gray'   => 'cinza',
        ];

        // Prioridade: Parada Programada > Intervalo > Troca de Cor > Troca de Molde > Desconexão > Manutenção > Falta de Silos > Micro Parada > Atrasada (ritmo) > cor da máquina
        $statusClasse = $ehParadaProgramada
            ? 'amarelo'
            : ($ehIntervalo
                ? 'azul'
                : ($ehTrocaCor
                    ? 'laranja'
                    : ($ehTrocaMolde
                        ? 'laranja'
                        : ($ehDesconexao
                            ? 'preto'
                            : ($ehManutencao
                                ? 'laranja-escuro'
                                : ($ehFaltaSilos
                                    ? 'amarelo-escuro'
                                    : ($ehMicroParada
                                        ? 'laranja'
                                        : ($opAtrasada
                                            ? 'vermelho'
                                            : ($statusCorMapa[$cor] ?? 'cinza')))))))));

        $statusTexto = $ehParadaProgramada
            ? 'Parada'
            : ($ehIntervalo
                ? 'Intervalo'
                : ($ehTrocaCor
                    ? 'Troca de Cor'
                    : ($ehTrocaMolde
                        ? 'Troca de Molde'
                        : ($ehDesconexao
                            ? 'Desconexão'
                            : ($ehManutencao
                                ? 'Manutenção'
                                : ($ehFaltaSilos
                                    ? 'Falta de Silos'
                                    : ($ehMicroParada
                                        ? 'Micro Parada'
                                        : ($opAtrasada
                                            ? 'Atrasada'
                                            : $maquina['status']))))))));

        // Classe do status-pill (componente próprio, independente da cor do card)
        $statusPillMapa = [
            'verde'          => 'em-dia',
            'vermelho'       => 'atrasada',
            'laranja'        => 'troca-kit',
            'amarelo'        => 'parada',
            'azul'           => 'intervalo',
            'cinza'          => 'em-dia',
            'preto'          => 'desconexao',
            'laranja-escuro' => 'manutencao-pill',
            'amarelo-escuro' => 'falta-silos-pill',
        ];
        $statusPillClasse = $statusPillMapa[$statusClasse] ?? 'em-dia';

        // Divergência = OP rodando no CODI que não está na programação confirmada
        // da máquina (já detectada em AcompanharProducaoSopro::carregarMaquinas())
        $temDivergencia = (bool) ($op['divergente'] ?? false);

        // Possível erro de apontamento: produzido da OP atual acima do programado
        $qtdOp       = (int) ($op['programado'] ?? 0);
        $produzidoOp = (int) ($op['realizado'] ?? 0);
        $possivelErroApontamento = $qtdOp > 0 && $produzidoOp > $qtdOp;

        // Buscar foto do frasco atual pelo SKU da OP
        $fotoProduto = null;
        if (!empty($op['sku'])) {
            $fotoProduto = \Illuminate\Support\Facades\DB::table('frascos')
                ->where('sku', $op['sku'])
                ->value('foto');
        }

        $imagemProduto = $fotoProduto
            ? asset('Frascos sem rótulo/' . $fotoProduto)
            : asset('images/aquafast-logo.svg');

        // Embalagens de 1,5L são visualmente mais altas que as demais
        $ehEmbalagem15L = (bool) preg_match('/1[.,]5\s*l\b/i', $op['descricao'] ?? '');

        // Sopro: oee/disponibilidade/performance já vêm no topo do array $maquina
        // (AcompanharProducaoSopro), não aninhados em 'oee_tempo_real' como no Envase.
        $oeeReal  = $maquina['oee']            ?? null;
        $dispReal = $maquina['disponibilidade'] ?? null;
        $perfReal = $maquina['performance']     ?? null;
        $oeeClasse  = is_null($oeeReal)  ? 'neutro' : ($oeeReal  >= 75 ? 'verde' : ($oeeReal  >= 60 ? 'amarelo' : 'vermelho'));
        $dispClasse = is_null($dispReal) ? 'neutro' : ($dispReal >= 85 ? 'verde' : ($dispReal >= 70 ? 'amarelo' : 'vermelho'));
        $perfClasse = is_null($perfReal) ? 'neutro' : ($perfReal >= 85 ? 'verde' : ($perfReal >= 70 ? 'amarelo' : 'vermelho'));

        $prevRealClasse = $projecaoLinha === null
            ? 'neutro'
            : ($prevXRealVal > 0 ? 'verde' : ($prevXRealVal < 0 ? 'vermelho' : 'neutro'));

        // Aliases dos valores já calculados acima pros nomes de variável do card
        $produzido       = number_format($op['realizado'] ?? 0, 0, ',', '.');
        $meta            = number_format($op['programado'] ?? 0, 0, ',', '.');
        $percentual      = $pct . '%';
        $percentualBarra = $pct;
        $totalDia        = number_format($maquina['total_hoje'] ?? 0, 0, ',', '.');
        $prevDiaExibicao = $prevDiaStr;
        $prevReal        = $prevXRealStr;
        $disponibilidade = !is_null($dispReal) ? number_format($dispReal,1,',','.').'%' : '—';
        $performance     = !is_null($perfReal) ? number_format($perfReal,1,',','.').'%' : '—';
        $oee             = !is_null($oeeReal)  ? number_format($oeeReal,1,',','.').'%'  : '—';
    @endphp
    <div class="linha-card {{ $statusClasse }} {{ $pulsingClass }} {{ $ehParadaProgramada ? 'em-pausa' : ($ehIntervalo ? 'em-intervalo' : ($ehTrocaCor ? 'em-troca-kit' : ($ehTrocaMolde ? 'em-troca-liquido' : ($ehDesconexao ? 'em-desconexao' : ($ehManutencao ? 'em-manutencao' : ($ehFaltaSilos ? 'em-falta-silos' : ($ehMicroParada ? 'em-micro-parada' : ''))))))) }} {{ $temDivergencia ? 'tem-divergencia' : '' }} {{ $ehEmbalagem15L ? 'produto-15l-card' : '' }}">
        <div class="status-pill {{ $statusPillClasse }}">
            <span class="pill-label">{{ $statusTexto }}</span>
            @if($temDivergencia)
                <span class="divergencia-icon" title="OP rodando sem programação">⚠</span>
            @endif
        </div>
        <div class="linha-nome">MÁQUINA {{ $maquinaNumero }}</div>
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
        @elseif($ehTrocaCor)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🎨</div>
            <div style="font-size:26px;font-weight:800;color:#ff8c00;letter-spacing:1px;text-transform:uppercase;">Troca de Cor</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px;color:#ffb347;font-size:20px;font-weight:700;">
                <span style="font-size:22px;">⏱</span>
                <span class="cronometro-troca" data-minutos="{{ $tempoTrocaMin }}">{{ $tempoTrocaHhmm }}</span>
            </div>
        </div>
        @elseif($ehTrocaMolde)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🔩</div>
            <div style="font-size:26px;font-weight:800;color:#ff8c00;letter-spacing:1px;text-transform:uppercase;">Troca de Molde</div>
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
        @elseif($ehManutencao)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🔧</div>
            <div style="font-size:26px;font-weight:800;color:#ea580c;letter-spacing:1px;text-transform:uppercase;">Manutenção</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px;color:#fb923c;font-size:20px;font-weight:700;">
                <span style="font-size:22px;">⏱</span>
                <span class="cronometro-troca" data-minutos="{{ $tempoTrocaMin }}">{{ $tempoTrocaHhmm }}</span>
            </div>
        </div>
        @elseif($ehFaltaSilos)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🏭</div>
            <div style="font-size:26px;font-weight:800;color:#ca8a04;letter-spacing:1px;text-transform:uppercase;">Falta de Silos</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px;color:#eab308;font-size:20px;font-weight:700;">
                <span style="font-size:22px;">⏱</span>
                <span class="cronometro-troca" data-minutos="{{ $tempoTrocaMin }}">{{ $tempoTrocaHhmm }}</span>
            </div>
        </div>
        @elseif($ehMicroParada)
        <div class="status-overlay">
            <div style="font-size:50px;margin-bottom:12px;">🔩</div>
            <div style="font-size:26px;font-weight:800;color:#ff8c00;letter-spacing:1px;text-transform:uppercase;">Micro Parada</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px;color:#ffb347;font-size:20px;font-weight:700;">
                <span style="font-size:22px;">⏱</span>
                <span class="cronometro-troca" data-minutos="{{ $tempoTrocaMin }}">{{ $tempoTrocaHhmm }}</span>
            </div>
        </div>
        @endif
        <div class="card-content">
            <div class="linha-topo">
                <div class="linha-info">
                    @if($op)
                        <div class="op-info">OP {{ $op['numero_op'] }}</div>
                        <div class="produto">{{ $op['descricao'] }}</div>
                        @php
                            // Atraso de início da OP tem prioridade; se não houver, usa o
                            // equivalente em tempo do déficit de ritmo (Card "Prev. x Real."
                            // negativo). Sopro não expõe atraso_inicio_min hoje — sempre cai
                            // no fallback de ritmo.
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
                        <img class="produto-img {{ ($fotoProduto ?? null) ? '' : 'produto-img-placeholder' }} {{ $ehEmbalagem15L ? 'produto-15l' : '' }}"
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
                        <span class="cx-valor {{ $possivelErroApontamento ? 'valor-erro-apontamento' : '' }}">{{ $produzido }}</span>
                        <span class="cx-meta">
                            @if(($op['programado'] ?? 0) > 0)
                                / {{ $meta }} frascos <span>•</span> {{ $percentual }}@if($possivelErroApontamento) <span class="triangulo-apontamento">⚠</span>@endif
                            @else
                                frascos
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
                    </div>
                    <div class="total-box">
                        <div class="total-label">Total Programado</div>
                        <div class="total-valor">{{ $projecaoLinha !== null ? $prevDiaExibicao : '-' }}</div>
                    </div>
                    <div class="total-box">
                        <div class="total-label">Previsto x Realizado</div>
                        <div class="total-valor {{ $projecaoLinha !== null ? $prevRealClasse : '' }}">{{ $projecaoLinha !== null ? $prevReal : '-' }}</div>
                    </div>
                </div>
            </div>
        </div>{{-- card-content --}}
    </div>
