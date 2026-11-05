<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    
    public const MIN_DOCENTES = 1;
    public const MAX_DOCENTES = 2;

   
    public const MIN_ESTUDIANTES = 15;

    protected $fillable = ['numero_ciclo', 'ano', 'numero_grupo', 'abierto', 'curso_id'];

    protected $casts = ['abierto' => 'boolean'];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

    public function docentes()
    {
        return $this->belongsToMany(Docente::class, 'docente_grupo');
    }

    public function matriculas()
    {
        return $this->hasMany(EstudianteMatriculado::class);
    }

    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_matriculados')
                    ->withPivot('nota')
                    ->withTimestamps();
    }

    /**
     * RN-02: asigna un docente al grupo. Un grupo no puede tener más de 2 docentes.
     *
     * @throws DomainException si el grupo ya tiene 2 docentes
     */
    public function asignarDocente(Docente $docente): void
    {
        if ($this->docentes()->count() >= self::MAX_DOCENTES) {
            throw new DomainException('Un grupo no puede tener más de ' . self::MAX_DOCENTES . ' docentes.');
        }

        $this->docentes()->attach($docente->id);
        $this->unsetRelation('docentes');
    }

    
    public function matricular(Estudiante $estudiante): void
    {
        $this->estudiantes()->attach($estudiante->id, ['nota' => null]);
        $this->unsetRelation('estudiantes');
    }

    public function puedeAbrirse(): bool
    {
        $docentes = $this->docentes()->count();
        $estudiantes = $this->estudiantes()->count();

        return $docentes >= self::MIN_DOCENTES
            && $docentes <= self::MAX_DOCENTES
            && $estudiantes >= self::MIN_ESTUDIANTES;
    }

    /**
     * Abre el grupo y lo guarda en la base de datos.
     *
     * @throws DomainException si el grupo no cumple las reglas para abrirse
     */
    public function abrir(): void
    {
        if (! $this->puedeAbrirse()) {
            throw new DomainException(
                'El grupo necesita al menos ' . self::MIN_DOCENTES . ' docente y '
                . self::MIN_ESTUDIANTES . ' estudiantes matriculados para abrirse.'
            );
        }

        $this->abierto = true;
        $this->save();
    }
}
