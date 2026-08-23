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
        Schema::create('unidad_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50); // Metro cuadrado, Metro lineal, Unidad, Hora, Litro
            $table->string('simbolo', 10); // m2, ml, unidad, hr, lt
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidad_medida');
    }
};
