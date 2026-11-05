<?php

namespace Tests\Feature;

use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_un_curso_y_lo_guarda_en_la_base_de_datos(): void
    {
        $datos = [
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ];

        $respuesta = $this->postJson(route('cursos.store'), $datos);

        $respuesta->assertCreated()->assertJsonFragment($datos);

        $this->assertDatabaseHas('cursos', $datos);
        $this->assertDatabaseCount('cursos', 1);

       
        $curso = Curso::where('sigla', 'IF6100')->firstOrFail();
        $this->assertSame('Análisis y Diseño de Sistemas', $curso->nombre_curso);
        $this->assertSame(4, $curso->creditos);
        $this->assertSame($curso->id, $respuesta->json('id'));
    }

    public function test_store_rechaza_datos_invalidos_y_no_guarda_nada(): void
    {
        $respuesta = $this->postJson(route('cursos.store'), [
            'sigla' => '',
            'nombre_curso' => '',
            'creditos' => 'cuatro',
        ]);

        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['sigla', 'nombre_curso', 'creditos']);

        $this->assertDatabaseCount('cursos', 0);
    }

    public function test_store_no_permite_siglas_repetidas(): void
    {
        Curso::create([
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ]);

        $respuesta = $this->postJson(route('cursos.store'), [
            'sigla' => 'IF6100',
            'nombre_curso' => 'Otro curso',
            'creditos' => 3,
        ]);

        $respuesta->assertUnprocessable()->assertJsonValidationErrors(['sigla']);
        $this->assertDatabaseCount('cursos', 1);
        $this->assertDatabaseMissing('cursos', ['nombre_curso' => 'Otro curso']);
    }
}
