<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FichaCabecote;
use App\Models\FichaCabecotePosicao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FichaCabecotePosicao>
 */
class FichaCabecotePosicaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ficha_cabecote_id' => FichaCabecote::factory(),
            'cabecote' => (string) fake()->numberBetween(1, 10),
            'sentido' => fake()->randomElement(['inferior', 'superior', 'horizontal']),
            'largura_mm' => fake()->randomFloat(2, 1, 100),
            'deslocamento_mm' => fake()->randomFloat(2, 0, 50),
            'altura_cabecote_mm' => fake()->randomFloat(2, 1, 100),
            'obs' => fake()->optional()->sentence(),
            'ordem' => 1,
        ];
    }
}
