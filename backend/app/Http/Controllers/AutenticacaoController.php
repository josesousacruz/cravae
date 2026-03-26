<?php

namespace App\Http\Controllers;

use App\Http\Requests\CadastrarUsuarioRequest;
use App\Http\Requests\EntrarRequest;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AutenticacaoController extends Controller
{
    public function cadastrar(CadastrarUsuarioRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->create([
            'nome' => $request->string('nome')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'perfil' => 'usuario',
        ]);

        return response()->json([
            'token' => $usuario->createToken('web')->plainTextToken,
            'usuario' => $usuario,
        ], 201);
    }

    public function entrar(EntrarRequest $request): JsonResponse
    {
        $usuario = Usuario::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $usuario || ! Hash::check($request->string('password')->toString(), $usuario->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 422);
        }

        $usuario->tokens()->delete();

        return response()->json([
            'token' => $usuario->createToken('web')->plainTextToken,
            'usuario' => $usuario,
        ]);
    }

    public function usuarioAutenticado(Request $request): JsonResponse
    {
        return response()->json([
            'usuario' => $request->user(),
        ]);
    }

    public function sair(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }
}
