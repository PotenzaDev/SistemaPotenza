<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListProdutoErpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa' => ['required', 'string', 'in:FBM,FBP'],
            'nome' => ['nullable', 'string', 'max:255', 'required_without:sub_grupo'],
            'sub_grupo' => ['nullable', 'string', 'max:255', 'required_without:nome'],
        ];
    }
}
