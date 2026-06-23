<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CriarPedidoCheckoutRequest extends FormRequest
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
            'valor' => ['prohibited'],
            'torneio_id' => ['required', 'integer', 'exists:torneios,id'],
            'cupom_id' => ['nullable', 'integer', 'exists:cupons,id'],
            'forma_pagamento' => ['sometimes', 'in:pix,pix_direto'],
        ];
    }
}
