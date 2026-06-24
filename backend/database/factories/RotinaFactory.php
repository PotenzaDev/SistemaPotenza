<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rotina>
 */
class RotinaFactory extends Factory
{
    public function definition(): array
    {
        $nome = fake()->unique()->words(2, true);
        $slug = Str::slug($nome, '_');

        return [
            'nome'      => $nome,
            'slug'      => $slug,
            'pagina'    => '/' . Str::slug($nome),
            'icone'     => 'Circle',
            'parent_id' => null,
            'ordem'     => 0,
            'ativo'     => true,
        ];
    }
}
