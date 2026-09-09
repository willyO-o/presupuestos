<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca qué productos se pueden cotizar desde el sitio público (`/cotizador`).
 *
 * Es una lista blanca y arranca en 'NO' a propósito: el catálogo interno tiene
 * ítems de prueba, productos descontinuados y trabajos que solo tienen sentido
 * conversando con un vendedor. Publicar el catálogo entero le regalaría la
 * lista de precios a la competencia y le daría al visitante números que la
 * empresa no puede sostener.
 *
 * Se administra desde Productos → columna "Web" (ProductoController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table): void {
            $table->enum('cotizable_web', ['SI', 'NO'])
                ->default('NO')
                ->after('requiere_medidas');
        });
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table): void {
            $table->dropColumn('cotizable_web');
        });
    }
};
