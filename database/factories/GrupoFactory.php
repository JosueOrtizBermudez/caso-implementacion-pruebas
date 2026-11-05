<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    public function definition(): array
    {
        return [
            'numero_ciclo' => fake()->numberBetween(1, 3),
            'ano' => 2026,
            'numero_grupo' => fake()->numberBetween(1, 10),
            'abierto' => false,
            'curso_id' => Curso::factory(),
        ];
    }
}
