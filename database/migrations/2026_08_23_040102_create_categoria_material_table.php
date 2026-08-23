<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GIGANTOGRAFIA, CERRAJERIA, CARPINTERIA, OTROS_MATERIALES, PINTURAS.
     */
    public function up(): void
    {
        Schema::create('categoria_material', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_material');
    }
};
