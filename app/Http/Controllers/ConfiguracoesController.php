<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Linha;
use Illuminate\View\View;

/**
 * Página de configurações do sistema — somente leitura por enquanto
 * (parâmetros documentados aqui, edição fica para uma etapa futura).
 */
class ConfiguracoesController extends Controller
{
    /** Horários de início dos 4 turnos fixos do calendário produtivo (T1–T4) */
    private const HORAS_TURNOS_FIXOS = ['07:05', '13:27', '17:45', '23:00'];

    /** A partir deste horário de início um turno é considerado noturno */
    private const HORA_LIMITE_NOTURNO = '17:45';

    public function index(): View
    {
        $linha = Linha::ativas()
            ->with('calendario.intervalosAtivos')
            ->orderBy('codigo')
            ->first();

        $intervalos = $linha?->calendario?->intervalosAtivos ?? collect();

        $turnos = $intervalos
            ->map(function ($intervalo) {
                $horaInicio = substr((string) $intervalo->hora_inicio, 0, 5);
                $horaFim = substr((string) $intervalo->hora_fim, 0, 5);

                return [
                    'nome' => $intervalo->nome,
                    'hora_inicio' => $horaInicio,
                    'hora_fim' => $horaFim,
                    'noturno' => $horaInicio >= self::HORA_LIMITE_NOTURNO,
                ];
            })
            ->filter(fn (array $turno) => in_array($turno['hora_inicio'], self::HORAS_TURNOS_FIXOS, true))
            ->sortBy('hora_inicio')
            ->values()
            ->all();

        $formulaDe = 'taxa_por_hora × eficiência × minutos úteis na janela (06:00 → 03:00), '
            . 'limitado à quantidade da OP';

        return view('configuracoes.index', compact('turnos', 'formulaDe'));
    }
}
