<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ej. "Lona FrontLight 3,20x50m", "Tubo 20x20x0,9mm". `precio_unitario`
     * (costo por m2/metro/unidad) es el dato que se usa al cotizar;
     * `precio_presentacion` es el costo total de la presentacion comprada
     * (rollo/plancha/litro/barra).
     */
    public function up(): void
    {
        Schema::create('material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_material_id')->constrained('categoria_material')->restrictOnDelete();
            $table->string('nombre');
            $table->string('presentacion');
            $table->enum('unidad_medida', ['M2', 'METRO', 'UNIDAD', 'LITRO']);
            $table->decimal('precio_presentacion', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('stock_actual', 10, 2)->default(0);
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material');
    }
};
