<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fórmulas reutilizables para calcular dinámicamente cuánto material
     * consume una línea de `producto_material` (BOM), en vez de un factor
     * fijo — necesario para productos cuyo consumo depende de más de una
     * medida a la vez (ej. letras corpóreas 3D: área de cara + perímetro de
     * canto + profundidad). `expresion` se evalúa con
     * App\Services\Calculo\FormulaCalculator (nxp/math-executor) usando las
     * variables ancho/alto/profundo/area/perimetro — ver
     * App\Services\Calculo\MedidasCotizacion::variables().
     */
    public function up(): void
    {
        Schema::create('formula', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('expresion');
            $table->text('descripcion')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula');
    }
};
