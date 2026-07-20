<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Linha;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Linha>
 */
class LinhaFactory extends Factory
{
    protected $model = Linha::class;

    public function definition(): array
    {
        return [
            'codigo' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{2}'),
            'nome'   => $this->faker->words(3, true),
            'ativo'  => true,
        ];
    }
}
