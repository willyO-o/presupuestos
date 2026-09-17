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
        Schema::table('producto', function (Blueprint $table) {
            // Ruta relativa en el disco `public`, ya convertida a JPG (ver
            // App\Services\Imagen\ConvierteImagenAJpgService). Se puede
            // reutilizar como imagen referencial al armar una línea de
            // cotización (CotizacionController::resolverImagenLinea).
            $table->string('imagen')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn('imagen');
        });
    }
};
