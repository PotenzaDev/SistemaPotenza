<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hora_inicio'                     => ['required', 'date_format:H:i'],
            'hora_fim'                        => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_inicio'                => ['nullable', 'date_format:H:i', 'required_with:intervalo_fim', 'after:hora_inicio', 'before:hora_fim'],
            'intervalo_fim'                   => ['nullable', 'date_format:H:i', 'required_with:intervalo_inicio', 'after:intervalo_inicio', 'before:hora_fim'],
            'tolerancia_finalizacao_minutos'  => ['required', 'integer', 'min:0', 'max:120'],
            'ativo'                           => ['boolean'],
        ];
    }
}
