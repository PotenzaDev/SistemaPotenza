<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IniciarSessaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maquina_id'              => ['required', 'integer', 'exists:maquinas,id'],
            'sessao_pausada_id'       => ['nullable', 'integer', 'exists:sessoes_trabalho,id'],
            'turno_informado_inicio'  => ['nullable', 'date_format:H:i'],
            'turno_informado_fim'     => ['nullable', 'date_format:H:i', 'after:turno_informado_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'maquina_id.required' => 'O campo máquina é obrigatório.',
            'maquina_id.exists'   => 'Máquina não encontrada.',
            'sessao_pausada_id.exists' => 'Sessão pausada não encontrada.',
            'turno_informado_inicio.date_format' => 'Horário de início inválido.',
            'turno_informado_fim.date_format' => 'Horário de fim inválido.',
            'turno_informado_fim.after' => 'Horário de fim deve ser depois do início.',
        ];
    }
}
