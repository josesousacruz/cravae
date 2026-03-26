<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SalvarResultadoTorneioRequest extends FormRequest
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
            'campeao_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'vice_campeao_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'terceiro_colocado_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'artilheiro_jogador_id' => ['nullable', 'integer', 'exists:jogadores,id'],
        ];
    }
}
