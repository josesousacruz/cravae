<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SalvarApostasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'apostas' => ['required', 'array', 'min:1'],
            'apostas.*.tipo' => ['required', 'string'],
            'apostas.*.torneio_id' => ['nullable', 'integer', 'exists:torneios,id'],
            'apostas.*.jogo_id' => ['nullable', 'integer', 'exists:jogos,id'],
            'apostas.*.grupo_id' => ['nullable', 'integer', 'exists:grupos,id'],
            'apostas.*.jogador_id' => ['nullable', 'integer', 'exists:jogadores,id'],
            'apostas.*.selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.placar_mandante' => ['nullable', 'integer', 'min:0'],
            'apostas.*.placar_visitante' => ['nullable', 'integer', 'min:0'],
            'apostas.*.selecao_classificada_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.primeiro_colocado_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.segundo_colocado_id' => ['nullable', 'integer', 'exists:selecoes,id'],
        ];
    }
}
