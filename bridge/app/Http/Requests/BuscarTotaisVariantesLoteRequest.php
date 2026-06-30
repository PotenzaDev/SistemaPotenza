<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuscarTotaisVariantesLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lote'        => ['required', 'string', 'max:20'],
            'prefixo_cod' => ['required', 'string', 'size:5'],
        ];
    }
}
