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
        // Agrupa las categorías de producto en familias comerciales (lonas,
        // letreros y letras 3D, muebles y exhibidores...). Las categorías son
        // demasiado finas para pronosticar o comparar: una familia junta las
        // ventas suficientes para que una tendencia signifique algo.
        Schema::create('familia_producto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('familia_producto');
    }
};
