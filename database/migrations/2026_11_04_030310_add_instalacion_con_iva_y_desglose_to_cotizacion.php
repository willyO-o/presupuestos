<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instalación: cómo se arma y si paga IVA.
     *
     * - `cotizacion.instalacion_con_iva`: el vendedor decide por cotización si
     *   la instalación entra en la base del IVA. 'NO' reproduce el Excel de
     *   margen (instalación después del IVA), que es como estaban todas las
     *   cotizaciones hasta ahora.
     * - `cotizacion_detalle.instalacion_detalle`: desglose opcional con el que
     *   se calculó el monto (personas, horas, Bs/hora, nocturna + recargo,
     *   transporte). Sin desglose, `instalacion` sigue siendo un monto
     *   escrito a mano.
     */
    public function up(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->enum('instalacion_con_iva', ['SI', 'NO'])->default('NO')->after('instalacion');
        });

        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->json('instalacion_detalle')->nullable()->after('instalacion');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->dropColumn('instalacion_detalle');
        });

        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropColumn('instalacion_con_iva');
        });
    }
};
