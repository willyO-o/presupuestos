<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limpieza de dos redundancias del motor de margen (2026-09-09):
     *
     * 1. `impuesto` → `iva`. Desde que la tabla guarda también `it` e `iue`,
     *    "impuesto" a secas era ambiguo: los tres son impuestos, pero esta
     *    columna es específicamente el IVA que ve el cliente.
     *
     * 2. Se elimina `recomendacion`. Era 100 % derivable de `estado_margen`
     *    (mapa 1:1 en MotorMargenService::RECOMENDACIONES), así que guardarla
     *    solo abría la puerta a que ambas se desincronizaran. Ahora es un
     *    accesor del modelo (`Cotizacion::$recomendacion`), no una columna.
     */
    public function up(): void
    {
        // Renombrar y borrar van en llamadas separadas: en SQLite (el motor
        // de los tests) cada `Schema::table` reconstruye la tabla, y mezclar
        // ambas operaciones en la misma pasada no es fiable.
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->renameColumn('impuesto', 'iva');
        });

        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropColumn('recomendacion');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->renameColumn('iva', 'impuesto');
        });

        Schema::table('cotizacion', function (Blueprint $table) {
            $table->string('recomendacion')->nullable()->after('estado_margen');
        });
    }
};
