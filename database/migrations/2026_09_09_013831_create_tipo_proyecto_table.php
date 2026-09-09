<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Niveles de complejidad de un trabajo (Básico / Medio / Complejo /
     * Crítico en la hoja de costos original). Reemplaza la cadena de IF
     * anidados del Excel `11_Sistema_Margen_Automatico_Xtrapubli`: en vez de
     * mapear un número 1..4 a un factor y un margen fijos en código, cada
     * nivel es una fila administrable desde el CRUD de Tipos de Proyecto, sin
     * límite de niveles.
     */
    public function up(): void
    {
        Schema::create('tipo_proyecto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            // Multiplica el costo base de la línea: 1.00 = sin recargo.
            $table->decimal('factor_complejidad', 5, 2)->default(1);
            // Fracción, NO porcentaje: 0.4500 = 45 %.
            $table->decimal('margen_minimo', 6, 4)->default(0);
            $table->unsignedInteger('orden')->default(0);
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_proyecto');
    }
};
