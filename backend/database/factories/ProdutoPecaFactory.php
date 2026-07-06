<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Produto;
use App\Models\ProdutoPeca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdutoPeca>
 */
class ProdutoPecaFactory extends Factory
{
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 9000);

        return [
            'produto_id' => Produto::factory(),
            'numero' => $numero,
            'nome' => fake()->words(2, true),
            'sub_grupo' => fake()->word(),
            'dimensao' => fake()->numerify('###x###x##').'mm',
            'material' => fake()->word(),
            'ordem' => $numero,
        ];
    }
}
