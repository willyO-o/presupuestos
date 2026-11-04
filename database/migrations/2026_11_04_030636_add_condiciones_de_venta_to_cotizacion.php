<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Condiciones de venta propias de cada cotización, como en las que la
     * empresa armaba a mano ("Forma de pago: 15 días crédito"). Opcionales:
     * si quedan vacías, el documento muestra solo las condiciones generales.
     */
    public function up(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->string('forma_pago')->nullable()->after('observaciones');
            $table->string('tiempo_entrega')->nullable()->after('forma_pago');
            $table->string('lugar_entrega')->nullable()->after('tiempo_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropColumn(['forma_pago', 'tiempo_entrega', 'lugar_entrega']);
        });
    }
};
