<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasosPruebaGrupoTest extends TestCase
{
    use RefreshDatabase;

    private function crearCursoIF6100(): Curso
    {
        return Curso::create([
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ]);
    }

    private function crearGrupo01(Curso $curso, bool $abierto = false): Grupo
    {
        return Grupo::create([
            'numero_ciclo' => 2,
            'ano' => 2026,
            'numero_grupo' => 1,
            'abierto' => $abierto,
            'curso_id' => $curso->id,
        ]);
    }

    private function crearDocente(int $codigo): Docente
    {
        $docentes = [
            1001 => ['Ana', 'Mora Vargas'],
            1002 => ['Luis', 'Rojas Solano'],
            1003 => ['Carla', 'Jiménez Araya'],
        ];

        return Docente::create([
            'cod_empleado' => $codigo,
            'nombre_empleado' => $docentes[$codigo][0],
            'apellidos_empleado' => $docentes[$codigo][1],
        ]);
    }

    private function matricularEstudiantes(Grupo $grupo, int $cantidad, Docente $consejero): void
    {
        Estudiante::factory()
            ->count($cantidad)
            ->create(['consejero_id' => $consejero->id])
            ->each(fn (Estudiante $estudiante) => $grupo->matricular($estudiante));
    }

    public function test_cp01_registrar_grupo_con_su_informacion_y_su_curso(): void
    {
        $curso = $this->crearCursoIF6100();

        $creado = $this->crearGrupo01($curso, abierto: true);

        $grupo = Grupo::find($creado->id);

        $this->assertNotNull($grupo);
        $this->assertSame(2, $grupo->numero_ciclo);
        $this->assertSame(2026, $grupo->ano);
        $this->assertSame(1, $grupo->numero_grupo);

        $this->assertIsBool($grupo->abierto);
        $this->assertTrue($grupo->abierto);

        $this->assertSame('IF6100', $grupo->curso->sigla);
        $this->assertSame(4, $grupo->curso->creditos);

        $this->assertDatabaseCount('grupos', 1);
    }

    public function test_cp02_un_curso_tiene_varios_grupos_y_se_distinguen_los_abiertos(): void
    {
        $curso = $this->crearCursoIF6100();

        foreach ([1 => true, 2 => true, 3 => false] as $numero => $abierto) {
            Grupo::create([
                'numero_ciclo' => 2,
                'ano' => 2026,
                'numero_grupo' => $numero,
                'abierto' => $abierto,
                'curso_id' => $curso->id,
            ]);
        }

        $grupos = $curso->fresh()->grupos;
        $this->assertCount(3, $grupos);

        $abiertos = $grupos->where('abierto', true);
        $this->assertCount(2, $abiertos);
        $this->assertEqualsCanonicalizing([1, 2], $abiertos->pluck('numero_grupo')->all());

        $this->assertSame([$curso->id], $grupos->pluck('curso_id')->unique()->values()->all());
        $this->assertSame(3, Grupo::where('curso_id', $curso->id)->count());
    }

    public function test_cp03_un_grupo_acepta_dos_docentes(): void
    {
        $grupo = $this->crearGrupo01($this->crearCursoIF6100());
        $docente1001 = $this->crearDocente(1001);
        $docente1002 = $this->crearDocente(1002);
        $this->assertCount(0, $grupo->docentes);

        $grupo->asignarDocente($docente1001);
        $grupo->asignarDocente($docente1002);

        $docentes = $grupo->fresh()->docentes;
        $this->assertCount(2, $docentes);
        $this->assertEqualsCanonicalizing([1001, 1002], $docentes->pluck('cod_empleado')->all());

        $this->assertTrue($docente1001->grupos()->whereKey($grupo->id)->exists());

        $this->assertDatabaseCount('grupo_docente', 2);
    }

    public function test_cp04_un_grupo_rechaza_un_tercer_docente(): void
    {
        $grupo = $this->crearGrupo01($this->crearCursoIF6100());
        $grupo->asignarDocente($this->crearDocente(1001));
        $grupo->asignarDocente($this->crearDocente(1002));
        $docente1003 = $this->crearDocente(1003);

        $excepcionLanzada = false;
        try {
            $grupo->asignarDocente($docente1003);
        } catch (DomainException $e) {
            $excepcionLanzada = true;
        }
        $this->assertTrue($excepcionLanzada, 'Se esperaba DomainException al asignar un tercer docente.');

        $this->assertCount(2, $grupo->fresh()->docentes);
        $this->assertDatabaseCount('grupo_docente', 2);
        $this->assertDatabaseMissing('grupo_docente', [
            'grupo_id' => $grupo->id,
            'docente_id' => $docente1003->id,
        ]);
    }

    public function test_cp05_un_grupo_con_14_estudiantes_no_puede_abrirse(): void
    {
        $grupo = $this->crearGrupo01($this->crearCursoIF6100(), abierto: false);
        $docente1001 = $this->crearDocente(1001);
        $grupo->asignarDocente($docente1001);
        $this->matricularEstudiantes($grupo, 14, $docente1001);
        $this->assertSame(14, $grupo->estudiantes()->count());

        $this->assertFalse($grupo->puedeAbrirse());

        $excepcionLanzada = false;
        try {
            $grupo->abrir();
        } catch (DomainException $e) {
            $excepcionLanzada = true;
        }
        $this->assertTrue($excepcionLanzada, 'Se esperaba DomainException al abrir un grupo con 14 estudiantes.');

        $this->assertFalse($grupo->fresh()->abierto);
        $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => false]);
    }

    public function test_cp06_un_grupo_con_15_estudiantes_y_un_docente_se_abre(): void
    {
        $grupo = $this->crearGrupo01($this->crearCursoIF6100(), abierto: false);
        $docente1001 = $this->crearDocente(1001);
        $grupo->asignarDocente($docente1001);
        $this->matricularEstudiantes($grupo, 15, $docente1001);
        $this->assertSame(15, $grupo->estudiantes()->count());

        $this->assertTrue($grupo->puedeAbrirse());

        $grupo->abrir();

        $recargado = $grupo->fresh();
        $this->assertTrue($recargado->abierto);
        $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => true]);
    }
}
