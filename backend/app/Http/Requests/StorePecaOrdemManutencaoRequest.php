<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePecaOrdemManutencaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:200'],
            'quantidade' => ['required', 'numeric', 'min:0.001'],
            'preco_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }
}
