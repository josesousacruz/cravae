<?php

namespace App\Http\Controllers;

use App\Models\Torneio;
use Illuminate\Http\JsonResponse;

class BolaoController extends Controller
{
    public function index(): JsonResponse
    {
        $boloes = Torneio::query()
            ->whereIn('status', ['publicado', 'encerrado'])
            ->orderByDesc('id')
            ->get(['id', 'nome', 'edicao', 'status', 'valor_cupom', 'compras_abertas', 'imagem_url', 'data_inicio', 'data_fim']);

        return response()->json([
            'ativos' => $boloes->where('status', 'publicado')->values(),
            'encerrados' => $boloes->where('status', 'encerrado')->values(),
        ]);
    }
}
