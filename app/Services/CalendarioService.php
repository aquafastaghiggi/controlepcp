<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Calendario;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

/**
 * Gerencia toda a lógica de calendário de trabalho.
 *
 * Comportamentos-chave:
 * - Turno overnight (ex: 23:00–03:00) pertence ao dia que INICIOU.
 * - Regra overnight: se o dia seguinte ao overnight for bloqueado (não está no
 *   override/padrão, é feriado ou é domingo), o turno é TRUNCADO à meia-noite.
 * - Com $diasOverride date-keyed: datas não listadas usam o padrão semanal
 *   extraído do override para permitir produção além dos 10 dias configurados.
 * - Override vazio → regras de dias úteis do banco (diasUteis do intervalo).
 */
class CalendarioService
{
    private const LIMITE_BUSCA_DIAS = 365;

    /** @var array<int, Calendario> */
    private array $cacheCalendarios = [];

    // ─── Interface pública ───────────────────────────────────────────────────

    /**
     * Distribui $minutosNecessarios a partir de $inicio respeitando turnos.
     *
     * Retorna:
     *   'fim'       → DateTimeImmutable com o término real
     *   'segmentos' → [{inicio, fim, minutos, turno_nome, turno_inicio, turno_fim, turno_minutos}]
     *   'memoria'   → string legível para auditoria
     */
    public function distribuirMinutos(
        DateTimeImmutable $inicio,
        int $minutosNecessarios,
        int $calendarioId,
        array $diasOverride = []
    ): array {
        if ($minutosNecessarios <= 0) {
            return ['fim' => $inicio, 'segmentos' => [], 'memoria' => ''];
        }

        $atual     = $this->proximoMomentoValido($inicio, $calendarioId, $diasOverride);
        $restante  = $minutosNecessarios;
        $segmentos = [];

        while ($restante > 0) {
            $turno = $this->encontrarTurnoAtivo($atual, $calendarioId, $diasOverride);

            if ($turno === null) {
                $atual = $this->proximoMomentoValido($atual, $calendarioId, $diasOverride);
                continue;
            }

            $disponivel = (int) floor(
                ($turno['fim']->getTimestamp() - $atual->getTimestamp()) / 60
            );

            if ($disponivel <= 0) {
                $atual = $this->proximoMomentoValido($turno['fim'], $calendarioId, $diasOverride);
                continue;
            }

            $consumido   = min($restante, $disponivel);
            $fimSegmento = $atual->add(new DateInterval("PT{$consumido}M"));

            $turnoMin = (int) floor(
                ($turno['fim']->getTimestamp() - $turno['inicio']->getTimestamp()) / 60
            );

            $segmentos[] = [
                'inicio'         => $atual,
                'fim'            => $fimSegmento,
                'minutos'        => $consumido,
                'turno_nome'     => $turno['nome'],
                'turno_inicio'   => $turno['inicio'],
                'turno_fim'      => $turno['fim'],
                'turno_minutos'  => $turnoMin,
            ];

            $restante -= $consumido;

            if ($restante > 0) {
                $atual = $this->proximoMomentoValido($turno['fim'], $calendarioId, $diasOverride);
            } else {
                return [
                    'fim'       => $fimSegmento,
                    'segmentos' => $segmentos,
                    'memoria'   => $this->montarMemoriaCalculo($segmentos, $minutosNecessarios),
                ];
            }
        }

        $fim = empty($segmentos) ? $atual : end($segmentos)['fim'];

        return [
            'fim'       => $fim,
            'segmentos' => $segmentos,
            'memoria'   => $this->montarMemoriaCalculo($segmentos, $minutosNecessarios),
        ];
    }

    /**
     * Avança para o próximo momento dentro de um turno válido.
     * Se já estiver dentro de um turno, retorna o mesmo momento.
     */
    public function proximoMomentoValido(
        DateTimeImmutable $momento,
        int $calendarioId,
        array $diasOverride = []
    ): DateTimeImmutable {
        if ($this->encontrarTurnoAtivo($momento, $calendarioId, $diasOverride) !== null) {
            return $momento;
        }

        for ($offset = 0; $offset <= self::LIMITE_BUSCA_DIAS; $offset++) {
            $dia = $momento->setTime(0, 0)->add(new DateInterval("P{$offset}D"));

            foreach ($this->instanciarTurnosDoDia($dia, $calendarioId, $diasOverride) as $turno) {
                if ($momento <= $turno['inicio']) {
                    return $turno['inicio'];
                }
                if ($momento >= $turno['inicio'] && $momento < $turno['fim']) {
                    return $momento;
                }
            }
        }

        throw new RuntimeException(
            "Não foi possível encontrar um turno válido nos próximos " .
            self::LIMITE_BUSCA_DIAS . " dias (calendário id={$calendarioId})."
        );
    }

    /**
     * Calcula minutos úteis entre dois momentos (descontando pausas e dias bloqueados).
     */
    public function minutosUteisEntre(
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        int $calendarioId,
        array $diasOverride = []
    ): int {
        if ($fim <= $inicio) {
            return 0;
        }

        $minutos = 0;
        $cursor  = $inicio;

        while ($cursor < $fim) {
            $cursorValido = $this->proximoMomentoValido($cursor, $calendarioId, $diasOverride);

            if ($cursorValido >= $fim) {
                break;
            }

            $turno = $this->encontrarTurnoAtivo($cursorValido, $calendarioId, $diasOverride);

            if ($turno === null) {
                break;
            }

            $fimSegmento = $turno['fim'] < $fim ? $turno['fim'] : $fim;
            $minutos    += (int) floor(
                ($fimSegmento->getTimestamp() - $cursorValido->getTimestamp()) / 60
            );
            $cursor = $fimSegmento;
        }

        return $minutos;
    }

    /**
     * Retorna true se o dia tem pelo menos um turno ativo e não é feriado.
     */
    public function diaEstaAberto(
        DateTimeImmutable $data,
        int $calendarioId,
        array $diasOverride = []
    ): bool {
        return $this->instanciarTurnosDoDia($data, $calendarioId, $diasOverride) !== [];
    }

    public function limparCache(): void
    {
        $this->cacheCalendarios = [];
    }

    // ─── Métodos privados ────────────────────────────────────────────────────

    /**
     * Encontra o turno ativo para um momento.
     * Verifica o dia anterior (para overnight) e depois o dia atual.
     */
    private function encontrarTurnoAtivo(
        DateTimeImmutable $momento,
        int $calendarioId,
        array $diasOverride
    ): ?array {
        $diaAtual    = $momento->setTime(0, 0);
        $diaAnterior = $diaAtual->sub(new DateInterval('P1D'));

        foreach ($this->instanciarTurnosDoDia($diaAnterior, $calendarioId, $diasOverride) as $turno) {
            if ($momento >= $turno['inicio'] && $momento < $turno['fim']) {
                return $turno;
            }
        }

        foreach ($this->instanciarTurnosDoDia($diaAtual, $calendarioId, $diasOverride) as $turno) {
            if ($momento >= $turno['inicio'] && $momento < $turno['fim']) {
                return $turno;
            }
        }

        return null;
    }

    /**
     * Instancia os turnos válidos para um dia específico.
     *
     * Cada turno retornado tem: id, nome, inicio (DateTimeImmutable), fim (DateTimeImmutable).
     * Turnos overnight: fim = dia+1 setTime(hFim, mFim).
     * Regra overnight: se dia+1 for bloqueado/domingo/feriado → trunca fim em dia+1 00:00.
     */
    private function instanciarTurnosDoDia(
        DateTimeImmutable $dia,
        int $calendarioId,
        array $diasOverride
    ): array {
        $dataStr   = $dia->format('Y-m-d');
        $diaSemana = (int) $dia->format('N'); // 1=Seg … 7=Dom

        $turnosPermitidos = $this->resolverTurnosPermitidos(
            $dataStr, $diaSemana, $calendarioId, $diasOverride
        );

        // null = dia completamente bloqueado
        if ($turnosPermitidos === null) {
            return [];
        }

        if ($this->ehFeriado($dia, $calendarioId)) {
            return [];
        }

        $calendario = $this->carregarCalendario($calendarioId);
        $resultado  = [];

        foreach ($calendario->intervalosAtivos as $intervalo) {
            // Filtrar por IDs permitidos (array vazio = todos)
            if ($turnosPermitidos !== [] && ! in_array($intervalo->id, $turnosPermitidos, true)) {
                continue;
            }

            // Se sem override: respeitar diasUteis do banco
            // ATENÇÃO: quando HÁ override e o dia foi liberado via fallback de
            // diaSemanaTemTurnoNoBanco() (dia ausente do padrão do override, mas
            // com diasUteis no banco para ALGUM turno), esse filtro por-turno é
            // pulado — todos os intervalosAtivos do calendário são candidatos
            // nesse dia, não só o(s) turno(s) cujo diasUteis realmente bate.
            // Risco dormente: só se manifesta se turnos do mesmo calendário
            // tiverem diasUteis heterogêneos entre si (não ocorre nos calendários
            // atuais, mas pode ocorrer em configurações futuras).
            if (empty($diasOverride)) {
                $diasValidos = $intervalo->diasUteis->pluck('dia_semana')->toArray();
                if (! in_array($diaSemana, $diasValidos, true)) {
                    continue;
                }
            }

            [$hIni, $mIni] = array_map('intval', explode(':', substr($intervalo->hora_inicio, 0, 5)));
            [$hFim, $mFim] = array_map('intval', explode(':', substr($intervalo->hora_fim, 0, 5)));

            $inicio = $dia->setTime($hIni, $mIni, 0);
            $fim    = $dia->setTime($hFim, $mFim, 0);

            // Overnight: fim <= início → fim é no dia seguinte
            if ($fim <= $inicio) {
                $diaSeguinte = $dia->add(new DateInterval('P1D'));
                $fim         = $diaSeguinte->setTime($hFim, $mFim, 0);

                // Regra B (da v1): se dia seguinte for bloqueado, truncar em meia-noite
                $diaSegStr    = $diaSeguinte->format('Y-m-d');
                $diaSegSemana = (int) $diaSeguinte->format('N');

                $diaSegPermitido = $this->resolverTurnosPermitidos(
                    $diaSegStr, $diaSegSemana, $calendarioId, $diasOverride
                );

                if (
                    $diaSegPermitido === null              // dia seguinte bloqueado pelo override
                    || $diaSegSemana === 7                 // domingo
                    || $this->ehFeriado($diaSeguinte, $calendarioId)
                ) {
                    $fim = $diaSeguinte->setTime(0, 0, 0);
                }
            }

            // Turno vazio após truncagem → ignorar
            if ($fim <= $inicio) {
                continue;
            }

            $resultado[] = [
                'id'     => $intervalo->id,
                'nome'   => $intervalo->nome,
                'inicio' => $inicio,
                'fim'    => $fim,
            ];
        }

        usort($resultado, static fn ($a, $b) => $a['inicio'] <=> $b['inicio']);

        return $resultado;
    }

    /**
     * Resolve quais IDs de turno são permitidos para uma data/dia da semana.
     *
     * Retorna:
     *   []    → todos os turnos do dia são permitidos
     *   [ids] → apenas esses IDs
     *   null  → dia completamente bloqueado (não trabalha)
     */
    private function resolverTurnosPermitidos(
        string $dataStr,
        int $diaSemana,
        int $calendarioId,
        array $diasOverride
    ): ?array {
        // Sem override → banco decide (todos os turnos habilitados aqui; filtro por diasUteis acontece depois)
        if (empty($diasOverride)) {
            return [];
        }

        $primeiraChave = (string) array_key_first($diasOverride);
        $isFormatoData = strlen($primeiraChave) === 10; // 'Y-m-d'

        if ($isFormatoData) {
            // Data explicitamente no override
            if (isset($diasOverride[$dataStr])) {
                $config    = $diasOverride[$dataStr];
                $turnosRaw = $config['turnos'] ?? [];

                return $this->normalizarTurnoIds($turnosRaw);
            }

            // Data fora dos 10 dias → usar padrão semanal extraído do override
            $padrao = $this->extrairPadraoDaSemana($diasOverride);

            if (isset($padrao[$diaSemana])) {
                return $padrao[$diaSemana];
            }

            // Dia da semana ausente do padrão derivado do override: antes de bloquear,
            // verificar se o turno tem configuração real no banco para esse dia da semana
            if ($this->diaSemanaTemTurnoNoBanco($diaSemana, $calendarioId)) {
                return [];
            }

            return null; // Dia da semana não trabalha nem no padrão nem no banco
        }

        // Formato legado int-keyed: [diaSemana => [turnoId, ...]] ou [0=>1, 1=>2, ...]
        if (isset($diasOverride[0])) {
            // Formato simples: lista de dias da semana
            return in_array($diaSemana, $diasOverride, true) ? [] : null;
        }

        if (isset($diasOverride[$diaSemana])) {
            $turnosRaw = $diasOverride[$diaSemana];
            return is_array($turnosRaw) ? array_values(array_map('intval', $turnosRaw)) : [];
        }

        return null;
    }

    /**
     * Extrai o padrão recorrente de dias da semana a partir do override de datas.
     * Usado para calcular produção além dos dias explicitamente configurados.
     *
     * Exemplo: se no override Seg=>[T1,T2] e Sex=>[T1,T2,T3],
     * produção que cai numa Seg da semana seguinte usará [T1,T2].
     * Dias que não aparecem no override = bloqueados (não trabalham).
     */
    private function extrairPadraoDaSemana(array $diasOverride): array
    {
        $padrao = []; // [diaSemana => [turnoIds]]

        foreach ($diasOverride as $dataStr => $config) {
            $diaSemana = (int) (new DateTimeImmutable($dataStr))->format('N');
            $turnosIds = $this->normalizarTurnoIds($config['turnos'] ?? []);

            if (! isset($padrao[$diaSemana])) {
                $padrao[$diaSemana] = $turnosIds;
            } else {
                // Mesmo dia da semana aparece múltiplas vezes → union de IDs
                $padrao[$diaSemana] = array_values(
                    array_unique(array_merge($padrao[$diaSemana], $turnosIds))
                );
            }
        }

        return $padrao;
    }

    /**
     * Normaliza o array de turnos do override para sempre ser [int, int, ...].
     * Aceita:
     *   - [9, 10, 11]                        → já normalizado
     *   - [['id'=>9,'ativo'=>true], ...]      → extrai IDs dos ativos
     */
    private function normalizarTurnoIds(array $turnosRaw): array
    {
        if (empty($turnosRaw)) {
            return [];
        }

        $ids = [];

        foreach ($turnosRaw as $t) {
            if (is_array($t)) {
                if (! empty($t['ativo'])) {
                    $ids[] = (int) $t['id'];
                }
            } else {
                $ids[] = (int) $t;
            }
        }

        return array_values($ids);
    }

    /**
     * Verifica se existe, no banco, algum turno do calendário com diasUteis
     * configurado para o dia da semana informado (exclui domingo por regra de negócio).
     */
    private function diaSemanaTemTurnoNoBanco(int $diaSemana, int $calendarioId): bool
    {
        if ($diaSemana === 7) {
            return false;
        }

        $calendario = $this->carregarCalendario($calendarioId);

        foreach ($calendario->intervalosAtivos as $intervalo) {
            if ($intervalo->diasUteis->pluck('dia_semana')->contains($diaSemana)) {
                return true;
            }
        }

        return false;
    }

    private function ehFeriado(DateTimeImmutable $data, int $calendarioId): bool
    {
        $calendario = $this->carregarCalendario($calendarioId);
        $dataStr    = $data->format('Y-m-d');

        return $calendario->feriados->contains(
            fn ($f) => $f->data->format('Y-m-d') === $dataStr
        );
    }

    private function carregarCalendario(int $calendarioId): Calendario
    {
        if (! isset($this->cacheCalendarios[$calendarioId])) {
            $this->cacheCalendarios[$calendarioId] = Calendario::with([
                'intervalosAtivos.diasUteis',
                'feriados',
            ])->findOrFail($calendarioId);
        }

        return $this->cacheCalendarios[$calendarioId];
    }

    private function montarMemoriaCalculo(array $segmentos, int $totalNecessario): string
    {
        if (empty($segmentos)) {
            return '';
        }

        $partes          = [];
        $totalUsado      = 0;
        $totalDisponivel = 0;

        foreach ($segmentos as $s) {
            $usadoMin        = (int) $s['minutos'];
            $dispMin         = (int) $s['turno_minutos'];
            $totalUsado     += $usadoMin;
            $totalDisponivel += $dispMin;

            $partes[] = sprintf(
                '%s %s (%s–%s) | usado %s–%s = %s',
                $s['inicio']->format('d/m'),
                $s['turno_nome'],
                $s['turno_inicio']->format('H:i'),
                $s['turno_fim']->format('H:i'),
                $s['inicio']->format('H:i'),
                $s['fim']->format('H:i'),
                $this->formatarDuracao($usadoMin)
            );
        }

        $partes[] = sprintf(
            'total = %s / %s necessários',
            $this->formatarDuracao($totalUsado),
            $this->formatarDuracao($totalNecessario)
        );

        return implode(' | ', $partes);
    }

    private function formatarDuracao(int $minutos): string
    {
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        if ($h > 0 && $m > 0) {
            return "{$h}h{$m}m";
        }

        return $h > 0 ? "{$h}h" : "{$m}m";
    }
}
