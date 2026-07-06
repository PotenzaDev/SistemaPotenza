<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produto>
 */
class ProdutoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cod_produto' => fake()->unique()->numerify('PROD-#####'),
            'nome' => fake()->words(3, true),
            'grupo' => fake()->word(),
            'sub_grupo' => fake()->word(),
            'empresa' => fake()->randomElement(['FBM', 'FBP']),
            'ativo' => true,
        ];
    }
}
