<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resultado agregado del motor de margen (App\Services\Calculo\MotorMargenService)
     * sobre todo el presupuesto. Son valores CACHEADOS: la fuente de verdad son
     * los insumos de cada línea (`cotizacion_detalle_item`) y su tipo de
     * proyecto; el controlador los recalcula y sobreescribe en cada guardado.
     *
     * `subtotal` (ya existente) sigue siendo el precio antes de impuestos,
     * `impuesto` pasa a ser exactamente el IVA que calcula el motor y
     * `total` = subtotal − descuento + IVA + instalación.
     */
    public function up(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->decimal('costo_base', 10, 2)->default(0)->after('sucursal_id');
            $table->decimal('costo_ajustado', 10, 2)->default(0)->after('costo_base');
            $table->decimal('it', 10, 2)->default(0)->after('impuesto');
            $table->decimal('iue', 10, 2)->default(0)->after('it');
            $table->decimal('utilidad_real', 10, 2)->default(0)->after('iue');
            $table->decimal('instalacion', 10, 2)->default(0)->after('utilidad_real');
            $table->enum('estado_margen', ['VERDE', 'AMARILLO', 'ROJO'])->nullable()->after('instalacion');
            $table->string('recomendacion')->nullable()->after('estado_margen');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropColumn([
                'costo_base', 'costo_ajustado', 'it', 'iue',
                'utilidad_real', 'instalacion', 'estado_margen', 'recomendacion',
            ]);
        });
    }
};
