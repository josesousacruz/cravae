<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SalvarResultadoJogoRequest extends FormRequest
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
            'placar_mandante' => ['required', 'integer', 'min:0'],
            'placar_visitante' => ['required', 'integer', 'min:0'],
            'selecao_classificada_id' => ['nullable', 'integer', 'exists:selecoes,id'],
        ];
    }
}
