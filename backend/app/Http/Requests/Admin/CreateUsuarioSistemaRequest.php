<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ModuloSistema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUsuarioSistemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:6'],
            'role'                  => ['required', Rule::in(['admin', 'funcionario'])],
            'modulos_permitidos'    => ['nullable', 'array'],
            'modulos_permitidos.*'  => ['string', Rule::in(ModuloSistema::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'O nome é obrigatório.',
            'email.required'    => 'O e-mail é obrigatório.',
            'email.email'       => 'Informe um e-mail válido.',
            'email.unique'      => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.min'      => 'A senha deve ter pelo menos 6 caracteres.',
            'role.required'     => 'Selecione o perfil de acesso.',
            'role.in'           => 'Perfil de acesso inválido.',
            'modulos_permitidos.array' => 'Módulos permitidos inválidos.',
            'modulos_permitidos.*.in'  => 'Módulo inválido selecionado.',
        ];
    }
}
