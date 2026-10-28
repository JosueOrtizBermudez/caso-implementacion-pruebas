<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
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
}