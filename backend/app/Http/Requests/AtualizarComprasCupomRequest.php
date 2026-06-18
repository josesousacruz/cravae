<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarComprasCupomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota ja protegida por can:acessar-area-admin
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'compras_abertas' => ['required', 'boolean'],
        ];
    }
}
