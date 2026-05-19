<?php

use App\Http\Controllers\AutenticacaoController;
use App\Http\Controllers\ApostaController;
use App\Http\Controllers\CupomController;
use App\Http\Controllers\PainelAdministradorController;
use App\Http\Controllers\PedidoCheckoutController;
use App\Http\Controllers\TorneioController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('entrar', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
});

Route::get('/status', function () {
    return response()->json([
        'aplicacao' => 'Inter World Cup',
        'status' => 'ok',
    ]);
});

Route::get('/torneio', [TorneioController::class, 'publico']);
Route::get('/torneios/{torneio}/ranking', [TorneioController::class, 'ranking']);
Route::get('/jogos/{jogo}/palpiteiros', [TorneioController::class, 'palpiteiros']);

Route::post('/cadastro', [AutenticacaoController::class, 'cadastrar']);
Route::post('/entrar', [AutenticacaoController::class, 'entrar'])->middleware('throttle:entrar');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/usuario', [AutenticacaoController::class, 'usuarioAutenticado']);
    Route::post('/sair', [AutenticacaoController::class, 'sair']);
    Route::get('/cupons', [CupomController::class, 'index']);
    Route::get('/cupons/{cupom}', [CupomController::class, 'show']);
    Route::get('/cupons/{cupom}/bracket', [CupomController::class, 'bracket']);
    Route::get('/cupons/{cupom}/apostas', [ApostaController::class, 'index']);
    Route::post('/cupons/{cupom}/apostas/lote', [ApostaController::class, 'salvarLote']);
    Route::post('/pedidos-checkout', [PedidoCheckoutController::class, 'store']);
    Route::post('/pedidos-checkout/{pedidoCheckout}/simular-pagamento', [PedidoCheckoutController::class, 'simularPagamento']);

    Route::middleware('can:acessar-area-admin')->group(function () {
        Route::get('/admin/resumo', [PainelAdministradorController::class, 'resumo']);
        Route::get('/admin/dados', [PainelAdministradorController::class, 'dados']);
        Route::put('/admin/jogos/{jogo}/resultado', [PainelAdministradorController::class, 'salvarResultadoJogo']);
        Route::put('/admin/torneios/{torneio}/resultado', [PainelAdministradorController::class, 'salvarResultadoTorneio']);
        Route::put('/admin/regras-pontuacao/{regraPontuacao}', [PainelAdministradorController::class, 'atualizarRegraPontuacao']);
    });
});
