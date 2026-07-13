<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrocaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('broca')?->id;

        return [
            'codigo' => ['sometimes', 'string', 'max:50', 'unique:brocas,codigo,' . $id],
            'espessura_mm' => ['sometimes', 'numeric', 'min:0.01'],
            'rotacao' => ['sometimes', 'string', 'in:direita,esquerda'],
            'altura_mm' => ['sometimes', 'numeric', 'min:0.01'],
            'furo_passante' => ['sometimes', 'boolean'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
