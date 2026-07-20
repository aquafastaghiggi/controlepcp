<?php

use App\Http\Controllers\Api\CalendarioController;
use App\Http\Controllers\Api\OrdemController;
use App\Http\Controllers\Api\ProgramacaoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas da API REST do ControlePCP V2
|--------------------------------------------------------------------------
|
| Todas as rotas retornam JSON. Sem autenticação por enquanto —
| adicionar sanctum middleware quando necessário.
|
*/

Route::prefix('v1')->group(function () {

    // ── Programações ────────────────────────────────────────────────────────
    Route::get('/programacoes',                 [ProgramacaoController::class, 'index']);
    Route::post('/programacoes',                [ProgramacaoController::class, 'store']);
    Route::get('/programacoes/{programacao}',   [ProgramacaoController::class, 'show']);
    Route::post('/programacoes/{programacao}/calcular', [ProgramacaoController::class, 'calcular']);

    // ── Ordens do ERP ───────────────────────────────────────────────────────
    Route::get('/ordens', [OrdemController::class, 'index']);

    // ── Calendários ─────────────────────────────────────────────────────────
    Route::get('/calendarios/{calendario}',                         [CalendarioController::class, 'show']);
    Route::post('/calendarios/{calendario}/feriados',               [CalendarioController::class, 'adicionarFeriado']);
    Route::delete('/calendarios/{calendario}/feriados/{feriado}',   [CalendarioController::class, 'removerFeriado']);

});
