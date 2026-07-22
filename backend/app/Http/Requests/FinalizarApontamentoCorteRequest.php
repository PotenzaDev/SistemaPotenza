<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizarApontamentoCorteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fichas'                 => ['required', 'array', 'min:1'],
            'fichas.*.ficha_id'      => ['required', 'integer'],
            'fichas.*.qtd_produzida' => ['required', 'integer', 'min:0'],
            'confirmar_parcial'      => ['sometimes', 'boolean'],
        ];
    }
}
