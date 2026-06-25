<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuscarFichasLoteCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lote'     => ['required', 'string', 'max:20'],
            'cod_peca' => ['required', 'string', 'max:20'],
        ];
    }
}
