<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horas de mano de obra que suele llevar UNA unidad del producto (una
     * cabecera, una isla, un estante). "Traer insumos del producto" las
     * agrega a la hoja de costos a la tarifa de la empresa
     * (config('cotizacion.costo_hora_mano_obra')); el vendedor las ajusta
     * según el mueble. Opcional: sin horas, nada cambia.
     */
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->decimal('horas_mano_obra', 8, 2)->nullable()->after('precio_base');
        });
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn('horas_mano_obra');
        });
    }
};
