<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarFechamentoPodioRequest extends FormRequest
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
            'data_fechamento_podio' => ['nullable', 'date'],
        ];
    }
}
