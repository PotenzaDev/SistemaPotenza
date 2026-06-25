<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessao;

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
            'maquina_id'         => ['required', 'integer', 'exists:maquinas,id'],
            'sessao_pausada_id'  => ['nullable', 'integer', 'exists:sessoes_trabalho,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'maquina_id.required' => 'O campo máquina é obrigatório.',
            'maquina_id.exists'   => 'Máquina não encontrada.',
            'sessao_pausada_id.exists' => 'Sessão pausada não encontrada.',
        ];
    }
}
