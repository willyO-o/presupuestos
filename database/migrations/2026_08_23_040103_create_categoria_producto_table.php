<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BASTIDORES, BANNERS, GIGANTOGRAFIAS, VINYL_ROTULADO, EXHIBIDORES,
     * MATERIAL_POP, TOLDOS, LETREROS_LUMINOSOS, ROTULADO_VEHICULAR.
     */
    public function up(): void
    {
        Schema::create('categoria_producto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_producto');
    }
};
