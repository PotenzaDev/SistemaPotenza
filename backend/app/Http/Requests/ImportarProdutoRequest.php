<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportarProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cod_produto' => ['required', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:255'],
            'grupo' => ['nullable', 'string', 'max:255'],
            'sub_grupo' => ['nullable', 'string', 'max:255'],
            'empresa' => ['required', 'string', 'in:FBM,FBP'],
        ];
    }
}
