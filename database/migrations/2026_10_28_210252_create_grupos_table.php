<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('grupos', function (Blueprint $table) {
    $table->id();
    $table->integer('numero_ciclo');
    $table->integer('ano');
    $table->integer('numero_grupo');
    $table->boolean('abierto')->default(true);
    $table->foreignId('curso_id')->constrained('cursos');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
