<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Departamentos de produccion/administracion: VENTAS, DISENO,
     * GIGANTOGRAFIA, CERRAJERIA, CARPINTERIA, ACABADO, ADMINISTRACION.
     */
    public function up(): void
    {
        Schema::create('area', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area');
    }
};
