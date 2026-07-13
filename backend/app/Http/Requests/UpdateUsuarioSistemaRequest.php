<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioSistemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('usuario')?->id;

        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'email'         => ['sometimes', 'email', 'unique:users,email,' . $id],
            'password'      => ['sometimes', 'nullable', 'string', 'min:6'],
            'role'          => ['sometimes', Rule::in(['admin', 'funcionario'])],
            'rotina_ids'    => ['sometimes', 'nullable', 'array'],
            'rotina_ids.*'  => ['integer', 'exists:rotinas,id'],
            'ativo'         => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'         => 'Informe um e-mail válido.',
            'email.unique'        => 'Este e-mail já está cadastrado.',
            'password.min'        => 'A senha deve ter pelo menos 6 caracteres.',
            'role.in'             => 'Perfil de acesso inválido.',
            'rotina_ids.array'    => 'Rotinas permitidas inválidas.',
            'rotina_ids.*.exists' => 'Rotina inválida selecionada.',
        ];
    }
}
