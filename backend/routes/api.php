<?php

use App\Http\Controllers\AutenticacaoController;
use App\Http\Controllers\ApostaController;
use App\Http\Controllers\BolaoController;
use App\Http\Controllers\CupomController;
use App\Http\Controllers\PainelAdministradorController;
use App\Http\Controllers\PedidoCheckoutController;
use App\Http\Controllers\TorneioController;
use App\Http\Controllers\WebhookAsaasController;
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
Route::get('/boloes', [BolaoController::class, 'index']);
Route::get('/torneios/{torneio}/ranking', [TorneioController::class, 'ranking']);
Route::get('/torneios/{torneio}', [TorneioController::class, 'show']);
Route::get('/ranking/cupons/{cupom}/eventos', [TorneioController::class, 'eventosCupom']);
Route::get('/jogos/{jogo}/palpiteiros', [TorneioController::class, 'palpiteiros']);

Route::post('/cadastro', [AutenticacaoController::class, 'cadastrar']);
Route::post('/entrar', [AutenticacaoController::class, 'entrar'])->middleware('throttle:entrar');
Route::post('/webhooks/asaas/pagamentos', [WebhookAsaasController::class, 'pagamentos']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/usuario', [AutenticacaoController::class, 'usuarioAutenticado']);
    Route::put('/usuario', [AutenticacaoController::class, 'atualizarPerfil']);
    Route::post('/usuario/foto', [AutenticacaoController::class, 'atualizarFoto'])->middleware('throttle:10,1');
    Route::post('/sair', [AutenticacaoController::class, 'sair']);
    Route::get('/cupons', [CupomController::class, 'index']);
    Route::get('/cupons/{cupom}', [CupomController::class, 'show']);
    Route::get('/cupons/{cupom}/bracket', [CupomController::class, 'bracket']);
    Route::get('/cupons/{cupom}/apostas', [ApostaController::class, 'index']);
    Route::post('/cupons/{cupom}/apostas/lote', [ApostaController::class, 'salvarLote']);
    Route::post('/cupons/{cupom}/apostas/remover', [ApostaController::class, 'removerLote']);
    Route::post('/pedidos-checkout', [PedidoCheckoutController::class, 'store']);
    Route::get('/pedidos-checkout/{pedidoCheckout}', [PedidoCheckoutController::class, 'show']);
    Route::post('/pedidos-checkout/{pedidoCheckout}/confirmar-sandbox', [PedidoCheckoutController::class, 'confirmarSandbox']);
    Route::post('/cupons/{cupom}/pagamento', [PedidoCheckoutController::class, 'pagamentoCupom']);

    Route::middleware('can:acessar-area-admin')->group(function () {
        Route::get('/admin/resumo', [PainelAdministradorController::class, 'resumo']);
        Route::get('/admin/dados', [PainelAdministradorController::class, 'dados']);
        Route::get('/admin/cupons-pendentes', [PainelAdministradorController::class, 'cuponsPendentes']);
        Route::get('/admin/pagamentos', [PainelAdministradorController::class, 'pagamentos']);
        Route::post('/admin/cupons/{cupom}/marcar-pago', [PainelAdministradorController::class, 'marcarCupomPago']);
        Route::post('/admin/cupons/{cupom}/marcar-nao-pago', [PainelAdministradorController::class, 'marcarCupomNaoPago']);
        Route::put('/admin/jogos/{jogo}/resultado', [PainelAdministradorController::class, 'salvarResultadoJogo']);
        Route::delete('/admin/jogos/{jogo}/resultado', [PainelAdministradorController::class, 'limparResultadoJogo']);
        Route::put('/admin/jogos/{jogo}/evento-externo', [PainelAdministradorController::class, 'vincularEventoJogo']);
        Route::put('/admin/torneios/{torneio}/resultado', [PainelAdministradorController::class, 'salvarResultadoTorneio']);
        Route::put('/admin/torneios/{torneio}/compras', [PainelAdministradorController::class, 'atualizarComprasAbertas']);
        Route::put('/admin/torneios/{torneio}/fechamento-podio', [PainelAdministradorController::class, 'atualizarFechamentoPodio']);
        Route::post('/admin/regras-pontuacao', [PainelAdministradorController::class, 'criarRegraPontuacao']);
        Route::put('/admin/regras-pontuacao/{regraPontuacao}', [PainelAdministradorController::class, 'atualizarRegraPontuacao']);
        Route::delete('/admin/regras-pontuacao/{regraPontuacao}', [PainelAdministradorController::class, 'excluirRegraPontuacao']);
    });
});
