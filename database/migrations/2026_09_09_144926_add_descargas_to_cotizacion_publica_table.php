<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuántas veces se emitió el documento imprimible de una estimación pública.
 *
 * No es estadística: es el tope. Generar el documento arma la página completa
 * de un presupuesto para un visitante anónimo, y sin límite es un endpoint que
 * cualquiera puede pedir en bucle con un solo código válido. El máximo vive en
 * `config('cotizador.descargas_maximas')`.
 *
 * `descargado_en` es además lo que decide si el botón "Descargar" sigue
 * apareciendo en la página de la estimación: una vez que el visitante se
 * llevó su PDF, el botón desaparece.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_publica', function (Blueprint $table): void {
            $table->unsignedTinyInteger('descargas')->default(0)->after('estado');
            $table->timestamp('descargado_en')->nullable()->after('descargas');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_publica', function (Blueprint $table): void {
            $table->dropColumn(['descargas', 'descargado_en']);
        });
    }
};
