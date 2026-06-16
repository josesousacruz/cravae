<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtualizarPerfilRequest;
use App\Http\Requests\CadastrarUsuarioRequest;
use App\Http\Requests\EntrarRequest;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AutenticacaoController extends Controller
{
    public function cadastrar(CadastrarUsuarioRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->create([
            'nome' => $request->string('nome')->toString(),
            'email' => $request->string('email')->toString(),
            'telefone' => $request->string('telefone')->toString(),
            'cpf_cnpj' => preg_replace('/\D+/', '', $request->string('cpf_cnpj')->toString()),
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

    public function atualizarPerfil(AtualizarPerfilRequest $request): JsonResponse
    {
        $usuario = $request->user();

        $usuario->forceFill([
            'nome' => $request->string('nome')->toString(),
            'telefone' => $request->string('telefone')->toString(),
            'cpf_cnpj' => preg_replace('/\D+/', '', $request->string('cpf_cnpj')->toString()),
        ])->save();

        return response()->json([
            'usuario' => $usuario->fresh(),
        ]);
    }

    public function atualizarFoto(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $arquivo = $request->file('foto');

        // Barreira: confirma pelos magic bytes que o conteudo e mesmo um raster
        // JPEG/PNG/WebP valido. getimagesize() faz parte do nucleo do PHP (nao
        // depende de GD/Imagick). Bloqueia SVG, arquivos disfarcados, corrompidos,
        // e dimensoes fora do padrao (degeneradas ou bomba de descompressao).
        $info = @getimagesize($arquivo->getRealPath());
        $extensoes = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        if (
            $info === false
            || ! isset($extensoes[$info[2]])
            || $info[0] < 16 || $info[1] < 16
            || $info[0] > 6000 || $info[1] > 6000
            || ($info[0] * $info[1]) > 30000000
        ) {
            throw ValidationException::withMessages([
                'foto' => 'Envie uma imagem JPEG, PNG ou WebP valida (entre 16 e 6000 px por lado).',
            ]);
        }

        $usuario = $request->user();

        if ($usuario->foto && Storage::disk('public')->exists($usuario->foto)) {
            Storage::disk('public')->delete($usuario->foto);
        }

        // Nome aleatorio + extensao derivada do tipo real detectado (nunca do nome
        // enviado): impede .php, path traversal e sobrescrever a foto de outro usuario.
        $caminho = $arquivo->storeAs('avatares', Str::random(40).'.'.$extensoes[$info[2]], 'public');

        $usuario->forceFill(['foto' => $caminho])->save();

        return response()->json([
            'usuario' => $usuario->fresh(),
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
