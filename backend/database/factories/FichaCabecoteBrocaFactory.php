<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Broca;
use App\Models\FichaCabecote;
use App\Models\FichaCabecoteBroca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FichaCabecoteBroca>
 */
class FichaCabecoteBrocaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ficha_cabecote_id' => FichaCabecote::factory(),
            'cabecote' => (string) fake()->numberBetween(1, 10),
            'sentido' => fake()->randomElement(['inferior', 'superior', 'horizontal']),
            'posicao' => (string) fake()->numberBetween(1, 20),
            'broca_id' => Broca::factory(),
            'passante' => true,
            'profundidade_mm' => null,
            'agregado' => fake()->optional()->word(),
            'obs' => fake()->optional()->sentence(),
            'ordem' => 1,
        ];
    }
}
