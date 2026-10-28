<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrupoTest extends TestCase
{
    use RefreshDatabase;

    public function test_grupo_contiene_toda_la_informacion_asociada(): void
    {
        
        $cursoOtro = Curso::create([
            'sigla' => 'IF3001',
            'nombre_curso' => 'Bases de Datos',
            'creditos' => 4,
        ]);
        $curso = Curso::create([
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 3,
        ]);

        $maria = Docente::create([
            'cod_empleado' => 1001,
            'nombre_empleado' => 'María',
            'apellidos_empleado' => 'Rojas Mora',
        ]);
        $carlos = Docente::create([
            'cod_empleado' => 1002,
            'nombre_empleado' => 'Carlos',
            'apellidos_empleado' => 'Vargas Solís',
        ]);
        $laura = Docente::create([
            'cod_empleado' => 2001,
            'nombre_empleado' => 'Laura',
            'apellidos_empleado' => 'Jiménez Cruz',
        ]);

      
        $grupoA = Grupo::create([
            'numero_ciclo' => 2,
            'ano' => 2026,
            'numero_grupo' => 1,
            'abierto' => true,
            'curso_id' => $curso->id,
        ]);
        $grupoA->docentes()->attach([$maria->id, $carlos->id]);

        foreach (range(1, 15) as $i) {
            $estudiante = Estudiante::create([
                'carne' => 'C' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'nombre' => "Estudiante$i",
                'apellidos' => 'Apellido Prueba',
                'consejero_id' => $i <= 8 ? $maria->id : $carlos->id,
            ]);
            $grupoA->estudiantes()->attach($estudiante->id, ['nota' => 70 + $i]);
        }

       
        $grupoB = Grupo::create([
            'numero_ciclo' => 1,
            'ano' => 2025,
            'numero_grupo' => 2,
            'abierto' => false,
            'curso_id' => $cursoOtro->id,
        ]);
        $grupoB->docentes()->attach($laura->id);
        $extra = Estudiante::create([
            'carne' => 'C99999',
            'nombre' => 'Extra',
            'apellidos' => 'Otro Grupo',
            'consejero_id' => $laura->id,
        ]);
        $grupoB->estudiantes()->attach($extra->id, ['nota' => 100]);
        // Un estudiante del grupo A también matriculado en B, con otra nota
        $grupoB->estudiantes()->attach(
            Estudiante::where('carne', 'C00001')->value('id'),
            ['nota' => 95]
        );

       
        $grupo = Grupo::with(['curso', 'docentes', 'estudiantes.consejero', 'matriculas.estudiante'])
            ->findOrFail($grupoA->id);

        
        $this->assertSame(2, $grupo->numero_ciclo);
        $this->assertSame(2026, $grupo->ano);
        $this->assertSame(1, $grupo->numero_grupo);
        $this->assertTrue($grupo->abierto);

      
        $this->assertSame('IF6100', $grupo->curso->sigla);
        $this->assertSame('Análisis y Diseño de Sistemas', $grupo->curso->nombre_curso);
        $this->assertSame(3, $grupo->curso->creditos);

       
        $this->assertCount(2, $grupo->docentes);
        $this->assertEqualsCanonicalizing([1001, 1002], $grupo->docentes->pluck('cod_empleado')->all());
        $this->assertNotContains(2001, $grupo->docentes->pluck('cod_empleado')->all());

       
        $this->assertCount(15, $grupo->estudiantes);
        $this->assertNotContains('C99999', $grupo->estudiantes->pluck('carne')->all());

       
        $porCarne = $grupo->estudiantes->keyBy('carne');
        $this->assertSame(1001, $porCarne['C00001']->consejero->cod_empleado);
        $this->assertSame(1001, $porCarne['C00008']->consejero->cod_empleado);
        $this->assertSame(1002, $porCarne['C00009']->consejero->cod_empleado);
        $this->assertSame(1002, $porCarne['C00015']->consejero->cod_empleado);

       
        $this->assertCount(15, $grupo->matriculas);
        $notas = $grupo->matriculas->mapWithKeys(
            fn ($m) => [$m->estudiante->carne => $m->nota]
        );
        $this->assertEqualsWithDelta(71.0, $notas['C00001'], 0.001);
        $this->assertEqualsWithDelta(80.0, $notas['C00010'], 0.001);
        $this->assertEqualsWithDelta(85.0, $notas['C00015'], 0.001);

     
        $otro = Grupo::with(['curso', 'docentes', 'estudiantes'])->findOrFail($grupoB->id);
        $this->assertSame('IF3001', $otro->curso->sigla);
        $this->assertFalse($otro->abierto);
        $this->assertSame([2001], $otro->docentes->pluck('cod_empleado')->all());
        $this->assertEqualsCanonicalizing(['C00001', 'C99999'], $otro->estudiantes->pluck('carne')->all());
    }
}