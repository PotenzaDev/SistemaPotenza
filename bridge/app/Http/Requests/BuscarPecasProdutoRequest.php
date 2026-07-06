<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuscarPecasProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cod_produto' => $this->route('codProduto'),
        ]);
    }

    public function rules(): array
    {
        return [
            'cod_produto' => ['required', 'string'],
        ];
    }
}
