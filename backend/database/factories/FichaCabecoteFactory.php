<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FichaCabecote;
use App\Models\Maquina;
use App\Models\Operario;
use App\Models\ProdutoPeca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FichaCabecote>
 */
class FichaCabecoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'produto_peca_id' => ProdutoPeca::factory(),
            'maquina_id' => Maquina::factory(),
            'operario_id' => Operario::factory(),
            'data' => fake()->date(),
            'top_esquerdo_mm' => fake()->randomFloat(2, 0, 50),
            'top_direito_mm' => fake()->randomFloat(2, 0, 50),
            'quantidade_pecas_vez' => fake()->numberBetween(1, 20),
            'velocidade_trabalho' => fake()->randomFloat(2, 1, 100),
            'observacao' => fake()->optional()->sentence(),
        ];
    }
}
