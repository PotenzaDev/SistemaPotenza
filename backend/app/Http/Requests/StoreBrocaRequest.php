<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrocaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50', 'unique:brocas,codigo'],
            'espessura_mm' => ['required', 'numeric', 'min:0.01'],
            'rotacao' => ['required', 'string', 'in:direita,esquerda'],
            'altura_mm' => ['required', 'numeric', 'min:0.01'],
            'furo_passante' => ['required', 'boolean'],
            'ativo' => ['boolean'],
        ];
    }
}
