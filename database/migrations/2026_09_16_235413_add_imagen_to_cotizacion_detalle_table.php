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
        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            // Imagen referencial de la línea: subida a mano o copiada de
            // `producto.imagen` al guardar (foto histórica, igual que
            // factor_complejidad/margen_aplicado — ver .ai/rules/cotizaciones.md).
            // Ya convertida a JPG por App\Services\Imagen\ConvierteImagenAJpgService.
            $table->string('imagen')->nullable()->after('area_m2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->dropColumn('imagen');
        });
    }
};
