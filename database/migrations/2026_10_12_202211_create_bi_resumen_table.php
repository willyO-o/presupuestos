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
        // Resumen semanal del BI. `hechos` son los números calculados en PHP
        // (la fuente de verdad); `contenido` es el texto redactado a partir de
        // ellos. `sucursal_id` null = consolidado de toda la empresa.
        Schema::create('bi_resumen', function (Blueprint $table) {
            $table->id();
            $table->date('semana_inicio');
            $table->date('semana_fin');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursal')->restrictOnDelete();
            $table->json('hechos');
            $table->json('contenido');
            $table->enum('origen', ['IA', 'PLANTILLA']);
            $table->string('proveedor', 30)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['semana_inicio', 'sucursal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bi_resumen');
    }
};
