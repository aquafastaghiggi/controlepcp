<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarioSopro;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

/**
 * Gerencia calendário de trabalho para o módulo Sopro.
 *
 * Equivalente ao CalendarioService do Envase, adaptado para:
 *   - CalendarioSopro em vez de Calendario
 *   - Sem relação diasUteis (turnos Sopro valem para todos os dias no override)
 */
class CalendarioSoproService
{
    private const LIMITE_BUSCA_DIAS = 365;

    /** @var array<int, CalendarioSopro> */
    private array $cacheCalendarios = [];

    public function distribuirMinutos(
        DateTimeImmutable $inicio,
        int $minutosNecessarios,
        int $calendarioId,
        array $diasOverride = []
    ): array {
        if ($minutosNecessarios <= 0) {
            return ['fim' => $inicio, 'segmentos' => [], 'memoria' => ''];
        }

        $atual    = $this->proximoMomentoValido($inicio, $calendarioId, $diasOverride);
        $restante = $minutosNecessarios;
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
                'inicio'        => $atual,
                'fim'           => $fimSegmento,
                'minutos'       => $consumido,
                'turno_nome'    => $turno['nome'],
                'turno_inicio'  => $turno['inicio'],
                'turno_fim'     => $turno['fim'],
                'turno_minutos' => $turnoMin,
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
            self::LIMITE_BUSCA_DIAS . " dias (calendário sopro id={$calendarioId})."
        );
    }

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

    public function limparCache(): void
    {
        $this->cacheCalendarios = [];
    }

    // ─── Privados ────────────────────────────────────────────────────────────

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

    private function instanciarTurnosDoDia(
        DateTimeImmutable $dia,
        int $calendarioId,
        array $diasOverride
    ): array {
        $dataStr   = $dia->format('Y-m-d');
        $diaSemana = (int) $dia->format('N');

        $turnosPermitidos = $this->resolverTurnosPermitidos(
            $dataStr, $diaSemana, $calendarioId, $diasOverride
        );

        if ($turnosPermitidos === null) {
            return [];
        }

        if ($this->ehFeriado($dia, $calendarioId)) {
            return [];
        }

        $calendario = $this->carregarCalendario($calendarioId);
        $resultado  = [];

        foreach ($calendario->intervalosAtivos as $intervalo) {
            if ($turnosPermitidos !== [] && ! in_array($intervalo->id, $turnosPermitidos, true)) {
                continue;
            }

            // Sopro: sem diasUteis — turnos valem para todos os dias quando override ativo
            // Sem override: todos os dias úteis (Seg-Sáb) são válidos por padrão
            if (empty($diasOverride)) {
                if ($diaSemana === 7) { // bloqueia domingo sem override
                    continue;
                }
            }

            [$hIni, $mIni] = array_map('intval', explode(':', substr($intervalo->hora_inicio, 0, 5)));
            [$hFim, $mFim] = array_map('intval', explode(':', substr($intervalo->hora_fim, 0, 5)));

            $inicio = $dia->setTime($hIni, $mIni, 0);
            $fim    = $dia->setTime($hFim, $mFim, 0);

            if ($fim <= $inicio) {
                $diaSeguinte = $dia->add(new DateInterval('P1D'));
                $fim         = $diaSeguinte->setTime($hFim, $mFim, 0);

                $diaSegStr    = $diaSeguinte->format('Y-m-d');
                $diaSegSemana = (int) $diaSeguinte->format('N');

                $diaSegPermitido = $this->resolverTurnosPermitidos(
                    $diaSegStr, $diaSegSemana, $calendarioId, $diasOverride
                );

                if (
                    $diaSegPermitido === null
                    || $diaSegSemana === 7
                    || $this->ehFeriado($diaSeguinte, $calendarioId)
                ) {
                    $fim = $diaSeguinte->setTime(0, 0, 0);
                }
            }

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

    private function resolverTurnosPermitidos(
        string $dataStr,
        int $diaSemana,
        int $calendarioId,
        array $diasOverride
    ): ?array {
        if (empty($diasOverride)) {
            return [];
        }

        $primeiraChave = (string) array_key_first($diasOverride);
        $isFormatoData = strlen($primeiraChave) === 10;

        if ($isFormatoData) {
            if (isset($diasOverride[$dataStr])) {
                $config    = $diasOverride[$dataStr];
                $turnosRaw = $config['turnos'] ?? [];
                return $this->normalizarTurnoIds($turnosRaw);
            }

            $padrao = $this->extrairPadraoDaSemana($diasOverride);

            if (isset($padrao[$diaSemana])) {
                return $padrao[$diaSemana];
            }

            return null;
        }

        if (isset($diasOverride[0])) {
            return in_array($diaSemana, $diasOverride, true) ? [] : null;
        }

        if (isset($diasOverride[$diaSemana])) {
            $turnosRaw = $diasOverride[$diaSemana];
            return is_array($turnosRaw) ? array_values(array_map('intval', $turnosRaw)) : [];
        }

        return null;
    }

    private function extrairPadraoDaSemana(array $diasOverride): array
    {
        $padrao = [];

        foreach ($diasOverride as $dataStr => $config) {
            $diaSemana = (int) (new DateTimeImmutable($dataStr))->format('N');
            $turnosIds = $this->normalizarTurnoIds($config['turnos'] ?? []);

            if (! isset($padrao[$diaSemana])) {
                $padrao[$diaSemana] = $turnosIds;
            } else {
                $padrao[$diaSemana] = array_values(
                    array_unique(array_merge($padrao[$diaSemana], $turnosIds))
                );
            }
        }

        return $padrao;
    }

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

    private function ehFeriado(DateTimeImmutable $data, int $calendarioId): bool
    {
        $calendario = $this->carregarCalendario($calendarioId);
        $dataStr    = $data->format('Y-m-d');

        return $calendario->feriados->contains(
            fn ($f) => $f->data->format('Y-m-d') === $dataStr
        );
    }

    private function carregarCalendario(int $calendarioId): CalendarioSopro
    {
        if (! isset($this->cacheCalendarios[$calendarioId])) {
            $this->cacheCalendarios[$calendarioId] = CalendarioSopro::with([
                'intervalosAtivos',
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
