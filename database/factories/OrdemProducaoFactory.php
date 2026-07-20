<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrdemProducao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdemProducao>
 */
class OrdemProducaoFactory extends Factory
{
    protected $model = OrdemProducao::class;

    public function definition(): array
    {
        return [
            'numero_op'         => 'OP' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'sku'               => strtoupper($this->faker->bothify('SKU-###??')),
            'descricao_produto' => $this->faker->sentence(3),
            'quantidade'        => $this->faker->randomFloat(3, 10, 10000),
            'data_entrega'      => $this->faker->dateTimeBetween('now', '+60 days')->format('Y-m-d'),
            'prioridade'        => $this->faker->numberBetween(1, 10),
            'status'            => 'pendente',
            'origem'            => 'manual',
            'linha_id'          => null,
            'programacao_id'    => null,
            'observacoes'       => null,
        ];
    }

    public function programada(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'programada',
        ]);
    }

    public function emProducao(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'em_producao',
        ]);
    }

    public function concluida(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'concluida',
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelada',
        ]);
    }
}
