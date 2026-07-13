<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MotivoPausa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MotivoPausa>
 */
class MotivoPausaFactory extends Factory
{
    protected $model = MotivoPausa::class;

    public function definition(): array
    {
        return [
            'nome' => ucfirst(fake()->unique()->words(2, true)),
            'ativo' => true,
            'is_sistema' => false,
        ];
    }
}
