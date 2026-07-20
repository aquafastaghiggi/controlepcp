<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConfiguracoesController;
use App\Http\Controllers\PaginaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\PortalSsoController;
use App\Http\Controllers\TvController;
use Illuminate\Support\Facades\Route;

// ─── Autenticação ────────────────────────────────────────────────────────────

Route::get('/login',  [LoginController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'autenticar'])->name('login.post');
Route::post('/logout',[LoginController::class, 'sair'])->name('logout');
Route::get('/portal_sso.php', [PortalSsoController::class, 'handle'])->name('sso.handle');

// ─── Área protegida ───────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {
    Route::get('/',             fn () => redirect()->route('dashboard'));
    Route::get('/dashboard',    [PaginaController::class, 'dashboard'])->name('dashboard');
    Route::get('/programacoes',        [PaginaController::class, 'selecionarProgramacao'])->name('programacoes');
    Route::get('/programacoes/envase', [PaginaController::class, 'programacoes'])->name('programacoes.envase');
    Route::get('/programacoes/sopro',  [PaginaController::class, 'programacoesSopro'])->name('programacoes.sopro');
    Route::get('/historico',    [PaginaController::class, 'historico'])->name('historico');
    Route::get('/calendario',   [PaginaController::class, 'calendario'])->name('calendario');
    Route::get('/produtos/exportar', [PaginaController::class, 'exportarProdutos'])->name('produtos.exportar');
    Route::get('/produtos',          [PaginaController::class, 'produtos'])->name('produtos');
    Route::get('/produtos/matrizes', [PaginaController::class, 'matrizes'])->name('produtos.matrizes');
    Route::get('/produtos/fotos', [PaginaController::class, 'fotos'])->name('produtos.fotos');
    Route::get('/programacoes/{id}/imprimir', [PaginaController::class, 'imprimirProgramacao'])->name('programacoes.imprimir');
    Route::get('/desempenho', [PaginaController::class, 'desempenho'])->name('desempenho');
    Route::get('/ordens',     [PaginaController::class, 'ordens'])->name('ordens');
    Route::get('/acompanhar-producao', [PaginaController::class, 'acompanharProducao'])->name('acompanhar');
    Route::get('/divergencias',        [PaginaController::class, 'divergencias'])->name('divergencias');
    Route::get('/sopro/maquinas', [PaginaController::class, 'soproMaquinas'])->name('sopro.maquinas');
    Route::get('/sopro/frascos',  [PaginaController::class, 'soproFrascos'])->name('sopro.frascos');
    Route::get('/sopro/frascos/fotos', [PaginaController::class, 'soproFotosFrascos'])->name('sopro.frascos.fotos');
    Route::get('/sopro/matriz-setup', [PaginaController::class, 'soproMatrizSetup'])->name('sopro.matriz');
    Route::get('/sopro/acompanhar', [PaginaController::class, 'soproAcompanhar'])->name('sopro.acompanhar');
    Route::get('/sopro/programacoes', [PaginaController::class, 'soproProgramacoes'])->name('sopro.programacoes');
    Route::get('/sopro/programacoes/{id}/resultado', [PaginaController::class, 'soproResultado'])->name('sopro.resultado');
    Route::get('/sopro/programacoes/{id}/imprimir', [PaginaController::class, 'soproImprimir'])->name('sopro.imprimir');
    Route::get('/sopro/calendario',   [PaginaController::class, 'soproCalendario'])->name('sopro.calendario');
    Route::redirect('/marketing/projecao-tvs', '/marketingtv/tv.html')->name('marketing.projecao-tv');
    Route::get('/relatorios/desempenho',       [RelatorioController::class, 'desempenho'])->name('relatorios.desempenho');
    Route::get('/relatorios/desempenho/print', [RelatorioController::class, 'desempenhoPrint'])->name('relatorios.desempenho.print');
    Route::get('/relatorios/prazo',            [RelatorioController::class, 'prazo'])->name('relatorios.prazo');
    Route::get('/relatorios/prazo/print',      [RelatorioController::class, 'prazoPrint'])->name('relatorios.prazo.print');
    Route::get('/relatorios/setup',            [RelatorioController::class, 'setup'])->name('relatorios.setup');
    Route::get('/relatorios/setup/print',      [RelatorioController::class, 'setupPrint'])->name('relatorios.setup.print');
    Route::get('/configuracoes', [ConfiguracoesController::class, 'index'])->name('configuracoes.index');
});

Route::get('/tv', \App\Livewire\Tv\TvDashboard::class)->name('tv');
Route::get('/tv-static', [App\Http\Controllers\TvStaticController::class, 'index'])->name('tv.static');
Route::get('/tv-static2', [App\Http\Controllers\TvStaticController::class, 'index2'])->name('tv.static2');
Route::get('/tv-sopro-static', [App\Http\Controllers\TvStaticSoproController::class, 'index'])->name('tv.sopro.static');
