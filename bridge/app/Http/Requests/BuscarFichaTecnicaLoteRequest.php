<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuscarFichaTecnicaLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lote'     => ['required', 'string'],
            'cod_peca' => ['required', 'string'],
        ];
    }
}
