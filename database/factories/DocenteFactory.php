<?php

namespace Database\Factories;

use App\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;


class DocenteFactory extends Factory
{
    protected $model = Docente::class;

    public function definition(): array
    {
        return [
            // Rango alto para no chocar con los docentes fijos 1001, 1002 y 1003
            'cod_empleado' => fake()->unique()->numberBetween(5000, 99999),
            'nombre_empleado' => fake()->firstName(),
            'apellidos_empleado' => fake()->lastName() . ' ' . fake()->lastName(),
        ];
    }
}
