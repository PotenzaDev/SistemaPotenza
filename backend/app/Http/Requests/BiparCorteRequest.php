<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiparCorteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cod_peca'    => ['required', 'string'],
            'ordem_lote'  => ['required', 'string'],
            'qtd_peca'    => ['required', 'integer', 'min:1'],
            'pilha'       => ['required', 'integer', 'min:1'],
            'cod_produto' => ['required', 'string'],
            'cor_codigo'  => ['required', 'string'],
        ];
    }
}
