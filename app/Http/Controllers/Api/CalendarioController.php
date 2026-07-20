<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendario;
use App\Models\Feriado;
use App\Models\Intervalo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API para consulta e configuração de calendários de trabalho.
 */
class CalendarioController extends Controller
{
    /** Retorna o calendário completo de uma linha (turnos + feriados) */
    public function show(Calendario $calendario): JsonResponse
    {
        return response()->json(
            $calendario->load(['intervalosAtivos.diasUteis', 'feriados'])
        );
    }

    /** Adiciona um feriado ao calendário */
    public function adicionarFeriado(Request $request, Calendario $calendario): JsonResponse
    {
        $dados = $request->validate([
            'data'      => "required|date|unique:feriados,data,NULL,id,calendario_id,{$calendario->id}",
            'descricao' => 'nullable|string|max:150',
        ]);

        $feriado = $calendario->feriados()->create($dados);

        return response()->json($feriado, 201);
    }

    /** Remove um feriado do calendário */
    public function removerFeriado(Calendario $calendario, Feriado $feriado): JsonResponse
    {
        if ($feriado->calendario_id !== $calendario->id) {
            abort(404, 'Feriado não pertence a este calendário.');
        }

        $feriado->delete();

        return response()->json(['mensagem' => 'Feriado removido com sucesso.']);
    }
}
