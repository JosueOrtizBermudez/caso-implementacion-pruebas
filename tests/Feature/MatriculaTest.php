<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\QueryException;

mutates(Grupo::class);

function cursoIF6100(): Curso
{
    return Curso::create([
        'sigla' => 'IF6100',
        'nombre_curso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);
}

function grupo01(?Curso $curso = null, bool $abierto = false): Grupo
{
    return Grupo::create([
        'numero_ciclo' => 2,
        'ano' => 2026,
        'numero_grupo' => 1,
        'abierto' => $abierto,
        'curso_id' => ($curso ?? cursoIF6100())->id,
    ]);
}

function docente(int $codigo): Docente
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

function estudianteC12345(Docente $consejero): Estudiante
{
    return Estudiante::create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
        'consejero_id' => $consejero->id,
    ]);
}

function matricularConFactory(Grupo $grupo, int $cantidad): void
{
    Estudiante::factory()
        ->count($cantidad)
        ->create()
        ->each(fn (Estudiante $estudiante) => $grupo->matricular($estudiante));
}

function matriculaDe(Grupo $grupo, Estudiante $estudiante): ?EstudianteMatriculado
{
    return EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();
}

test('CP-01 registra un grupo con toda su información y su curso', function () {
    $curso = cursoIF6100();

    $creado = grupo01($curso, abierto: true);
    $grupo = Grupo::find($creado->id);

    expect($grupo->numero_ciclo)->toBe(2)
        ->and($grupo->ano)->toBe(2026)
        ->and($grupo->numero_grupo)->toBe(1)
        ->and($grupo->abierto)->toBeBool()
        ->and($grupo->abierto)->toBeTrue()
        ->and($grupo->curso->sigla)->toBe('IF6100')
        ->and($grupo->curso->creditos)->toBe(4);

    $this->assertDatabaseCount('grupos', 1);
});

test('CP-02 un curso tiene varios grupos y se distinguen los abiertos', function () {
    $curso = cursoIF6100();

    foreach ([1 => true, 2 => true, 3 => false] as $numero => $abierto) {
        Grupo::create([
            'numero_ciclo' => 2,
            'ano' => 2026,
            'numero_grupo' => $numero,
            'abierto' => $abierto,
            'curso_id' => $curso->id,
        ]);
    }

    $grupos = $curso->grupos;
    $abiertos = $grupos->where('abierto', true);

    expect($grupos)->toHaveCount(3)
        ->and($abiertos)->toHaveCount(2)
        ->and($abiertos->pluck('numero_grupo')->all())->toEqualCanonicalizing([1, 2])
        ->and($grupos->pluck('curso_id')->unique()->values()->all())->toBe([$curso->id]);
});

test('CP-03 un grupo acepta dos docentes', function () {
    $grupo = grupo01();
    $docente1001 = docente(1001);
    $docente1002 = docente(1002);
    expect($grupo->docentes)->toHaveCount(0);

    $grupo->asignarDocente($docente1001);
    $grupo->asignarDocente($docente1002);

    expect($grupo->docentes)->toHaveCount(2)
        ->and($grupo->docentes->pluck('cod_empleado')->all())->toEqualCanonicalizing([1001, 1002])
        ->and($docente1001->grupos->pluck('id')->all())->toBe([$grupo->id]);

    $this->assertDatabaseCount('grupo_docente', 2);
});

test('CP-04 rechaza un tercer docente', function () {
    $grupo = grupo01();
    $grupo->asignarDocente(docente(1001));
    $grupo->asignarDocente(docente(1002));
    $docente1003 = docente(1003);

    expect(fn () => $grupo->asignarDocente($docente1003))
        ->toThrow(DomainException::class, 'Un grupo no puede tener más de 2 docentes.');

    expect($grupo->fresh()->docentes)->toHaveCount(2);
    $this->assertDatabaseCount('grupo_docente', 2);
    $this->assertDatabaseMissing('grupo_docente', ['docente_id' => $docente1003->id]);
});

test('CP-05 un grupo con 14 estudiantes no puede abrirse', function () {
    $grupo = grupo01();
    $grupo->asignarDocente(docente(1001));
    matricularConFactory($grupo, 14);

    expect($grupo->puedeAbrirse())->toBeFalse()
        ->and(fn () => $grupo->abrir())
        ->toThrow(DomainException::class, 'El grupo no cumple los requisitos para abrirse.');

    expect($grupo->fresh()->abierto)->toBeFalse();
    $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => false]);
});

test('CP-06 un grupo con 15 estudiantes y un docente se abre', function () {
    $grupo = grupo01();
    $grupo->asignarDocente(docente(1001));
    matricularConFactory($grupo, 15);

    expect($grupo->puedeAbrirse())->toBeTrue();

    $grupo->abrir();

    expect($grupo->fresh()->abierto)->toBeTrue();
    $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => true]);
});

test('CP-07 un grupo sin docente no se abre aunque tenga cupo completo', function () {
    $grupo = grupo01();
    matricularConFactory($grupo, 20);
    expect($grupo->docentes()->count())->toBe(0)
        ->and($grupo->estudiantes()->count())->toBe(20);

    expect($grupo->puedeAbrirse())->toBeFalse()
        ->and(fn () => $grupo->abrir())
        ->toThrow(DomainException::class, 'El grupo no cumple los requisitos para abrirse.');

    expect($grupo->fresh()->abierto)->toBeFalse();
    $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => false]);
});

test('CP-08 un estudiante queda ligado a su profesor consejero', function () {
    $docente = docente(1001);
    expect($docente->estudiantes()->count())->toBe(0);

    $estudiante = estudianteC12345($docente);

    expect($estudiante->consejero->cod_empleado)->toBe(1001)
        ->and($docente->estudiantes)->toHaveCount(1)
        ->and($docente->estudiantes->first()->carne)->toBe('C12345');

    $this->assertDatabaseHas('estudiantes', ['carne' => 'C12345', 'consejero_id' => $docente->id]);
});

test('CP-09 cambiar de consejero reemplaza al anterior, no lo suma', function () {
    $docente1001 = docente(1001);
    $docente1002 = docente(1002);
    $estudiante = estudianteC12345($docente1001);

    $estudiante->consejero()->associate($docente1002);
    $estudiante->save();

    $docente1001 = $docente1001->fresh();
    $docente1002 = $docente1002->fresh();

    expect($estudiante->fresh()->consejero->cod_empleado)->toBe(1002)
        ->and($docente1001->estudiantes)->toHaveCount(0)
        ->and($docente1002->estudiantes)->toHaveCount(1)
        ->and($docente1002->estudiantes->first()->carne)->toBe('C12345');

    $this->assertDatabaseCount('estudiantes', 1);
    $this->assertDatabaseHas('estudiantes', ['carne' => 'C12345', 'consejero_id' => $docente1002->id]);
});

test('CP-10 al matricular, la nota queda vacía', function () {
    $grupo = grupo01();
    $estudiante = estudianteC12345(docente(1001));
    expect($grupo->estudiantes)->toHaveCount(0);

    $grupo->matricular($estudiante);

    $pivote = $grupo->estudiantes->first()->pivot;

    expect($pivote)->toBeInstanceOf(EstudianteMatriculado::class)
        ->and($pivote->nota)->toBeNull();

    $this->assertDatabaseCount('estudiantes_matriculados', 1);
    $this->assertDatabaseHas('estudiantes_matriculados', [
        'grupo_id' => $grupo->id,
        'estudiante_id' => $estudiante->id,
        'nota' => null,
    ]);
    expect(matriculaDe($grupo, $estudiante)->created_at)->not->toBeNull()
        ->and($grupo->matriculas)->toHaveCount(1)
        ->and($grupo->matriculas->first()->estudiante_id)->toBe($estudiante->id)
        ->and($estudiante->grupos->first()->pivot)->toBeInstanceOf(EstudianteMatriculado::class);
});

test('CP-11 registra la nota al finalizar el curso', function () {
    $grupo = grupo01();
    $otro = Estudiante::factory()->create();
    $estudiante = estudianteC12345(docente(1001));
    $grupo->matricular($otro);
    $grupo->matricular($estudiante);
    $antes = matriculaDe($grupo, $estudiante);
    expect($antes->nota)->toBeNull();

    $grupo->registrarNota($estudiante, 8.5);
    $despues = EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();

    expect($despues->nota)->toBeFloat()
        ->and($despues->nota)->toBe(8.5)
        ->and($grupo->fresh()->estudiantes->firstWhere('carne', 'C12345')->pivot->nota)->toBe(8.5);

    expect($despues->id)->toBe($antes->id)
        ->and($despues->created_at->equalTo($antes->created_at))->toBeTrue()
        ->and($despues->grupo_id)->toBe($grupo->id)
        ->and($despues->estudiante_id)->toBe($estudiante->id)
        ->and(matriculaDe($grupo, $otro)->nota)->toBeNull();
    $this->assertDatabaseCount('estudiantes_matriculados', 2);
});

test('CP-12 rechaza notas menores que 0 o mayores que 10', function (float $valor) {
    $grupo = grupo01();
    $estudiante = estudianteC12345(docente(1001));
    $grupo->matricular($estudiante);

    expect(fn () => $grupo->registrarNota($estudiante, $valor))
        ->toThrow(InvalidArgumentException::class, 'La nota debe estar entre 0 y 10.');

    expect(matriculaDe($grupo, $estudiante)->nota)->toBeNull();
})->with([-1, 10.5]);

test('CP-13 un estudiante no se matricula dos veces en el mismo grupo', function () {
    $grupo = grupo01();
    $estudiante = estudianteC12345(docente(1001));
    $grupo->matricular($estudiante);

    expect(fn () => $grupo->matricular($estudiante))->toThrow(QueryException::class);

    expect(EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->count())->toBe(1);
});

test('C3 acepta las notas en los límites 0 y 10', function (float $valor) {
    $grupo = grupo01();
    $estudiante = estudianteC12345(docente(1001));
    $grupo->matricular($estudiante);

    $grupo->registrarNota($estudiante, $valor);

    expect(matriculaDe($grupo, $estudiante)->nota)->toBe($valor);
})->with([0.0, 10.0]);
