<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `redondeo_compra`: los materiales se compran en unidades enteras de
     * presentación (una plancha, una barra de 6 m, una caja) y el sobrante
     * de un corte rara vez se reutiliza — así que al costear conviene
     * redondear hacia arriba la cantidad consumida a la unidad real de
     * compra, no cobrar "1/4 de plancha".
     *
     * Es el múltiplo (en la `unidad_medida` del material) al que
     * App\Services\Calculo\CosteoProductoService redondea la cantidad total
     * consumida por una línea de BOM: `ceil(cantidad / redondeo_compra) *
     * redondeo_compra`. `null` = sin redondeo (material que se corta a
     * medida: lona/vinil de rollo, líquidos). Ejemplos: `1` = unidades
     * enteras, `2` = múltiplo de 2 m² (plancha de acrílico), `6` = barra
     * de 6 m.
     */
    public function up(): void
    {
        Schema::table('material', function (Blueprint $table) {
            $table->decimal('redondeo_compra', 10, 4)->nullable()->after('stock_minimo');
        });
    }

    public function down(): void
    {
        Schema::table('material', function (Blueprint $table) {
            $table->dropColumn('redondeo_compra');
        });
    }
};
