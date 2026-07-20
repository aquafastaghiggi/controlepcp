<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Linha;
use App\Services\IntegracaoOrdemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint para buscar ordens disponíveis do ERP.
 * Permite ao frontend listar ordens antes de criar uma programação.
 */
class OrdemController extends Controller
{
    /** Busca ordens abertas no ERP prontas para programação */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'linha_id' => 'nullable|exists:linhas,id',
        ]);

        $codigoLinha = null;

        if ($request->linha_id) {
            $codigoLinha = Linha::findOrFail($request->linha_id)->codigo;
        }

        $erp    = IntegracaoOrdemService::fromConfig();
        $ordens = $erp->buscarOrdens($codigoLinha);

        return response()->json([
            'data'  => $ordens,
            'total' => $ordens->count(),
            'configurada' => ! empty(config('services.erp.url')),
        ]);
    }
}
