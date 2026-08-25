<?php

namespace App\Http\Controllers;

use App\Models\Assinatura;
use App\Models\CicloAssinatura;
use App\Models\Plano;
use App\Services\ServicoAssinatura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssinaturaController extends Controller
{
    public function __construct(private readonly ServicoAssinatura $servicoAssinatura)
    {
    }

    /**
     * Público: planos ativos com o contador de vagas de fundador restantes.
     */
    public function planos(): JsonResponse
    {
        $planos = Plano::query()->where('ativo', true)->get()->map(fn (Plano $plano) => [
            'id' => $plano->id,
            'nome' => $plano->nome,
            'slug' => $plano->slug,
            'preco_centavos' => $plano->preco_centavos,
            'preco_fundador_centavos' => $plano->preco_fundador_centavos,
            'vagas_fundador_restantes' => $plano->vagasFundadorRestantes(),
            'max_cupons' => $plano->max_cupons,
        ]);

        return response()->json(['planos' => $planos]);
    }

    public function minha(Request $request): JsonResponse
    {
        $assinatura = $request->user()->assinaturaAtual();

        return response()->json([
            'assinatura' => $assinatura?->load('plano', 'ciclos'),
            'elegivel_no_mes' => $assinatura
                ? $this->servicoAssinatura->usuarioElegivelNaCompetencia($request->user()->id)
                : false,
        ]);
    }

    public function contratar(Request $request): JsonResponse
    {
        $dados = $request->validate(['plano_id' => ['required', 'integer', 'exists:planos,id']]);

        $plano = Plano::query()->where('ativo', true)->findOrFail($dados['plano_id']);
        $assinatura = $this->servicoAssinatura->contratar($request->user(), $plano);

        return response()->json([
            'assinatura' => $assinatura->load('plano', 'ciclos'),
        ], 201);
    }

    public function cancelar(Request $request): JsonResponse
    {
        $assinatura = $request->user()->assinaturaAtual();

        abort_unless($assinatura !== null, 404, 'Nenhuma assinatura em vigor.');

        return response()->json([
            'assinatura' => $this->servicoAssinatura->cancelar($assinatura)->load('plano'),
        ]);
    }

    /**
     * Sandbox/local (Asaas desligado): confirma manualmente o ciclo corrente.
     */
    public function confirmarCicloSandbox(Request $request): JsonResponse
    {
        abort_if($this->servicoAssinatura->asaasHabilitado() && ! str_contains((string) config('services.asaas.base_url'), 'api-sandbox'), 403, 'Disponível apenas em ambiente de testes.');

        $assinatura = $request->user()->assinaturaAtual();

        abort_unless($assinatura !== null, 404, 'Nenhuma assinatura em vigor.');

        $ciclo = $this->servicoAssinatura->registrarPagamentoCiclo(
            $assinatura,
            $this->servicoAssinatura->competenciaCorrente(),
        );

        return response()->json(['ciclo' => $ciclo]);
    }
}
