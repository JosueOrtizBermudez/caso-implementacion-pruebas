<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
      Schema::create('grupo_docente', function (Blueprint $table) {
    $table->id();
    $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
    $table->foreignId('docente_id')->constrained('docentes');
    $table->unique(['grupo_id', 'docente_id']);
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_docente');
    }
};
