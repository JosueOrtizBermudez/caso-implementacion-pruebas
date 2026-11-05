<?php

namespace Database\Factories;

use App\Models\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;


class CursoFactory extends Factory
{
    protected $model = Curso::class;

    public function definition(): array
    {
        return [
            'sigla' => fake()->unique()->bothify('XX####'),
            'nombre_curso' => fake()->sentence(3),
            'creditos' => fake()->numberBetween(1, 5),
        ];
    }
}
