<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Broca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broca>
 */
class BrocaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => 'BR-'.fake()->unique()->numerify('####'),
            'espessura_mm' => fake()->randomFloat(2, 1, 20),
            'rotacao' => fake()->randomElement(['direita', 'esquerda']),
            'altura_mm' => fake()->randomFloat(2, 10, 150),
            'furo_passante' => fake()->boolean(),
            'ativo' => true,
        ];
    }
}
