<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina `pago.estado` (2026-09-09).
     *
     * La columna no describía el pago: describía el saldo del PEDIDO después
     * de ese pago. Era un derivado que `Pedido::estadoPago()` ya calcula, y
     * su valor 'PENDIENTE' era además inalcanzable (una fila de pago siempre
     * deja el pedido en PARCIAL o PAGADO). Guardarlo por fila significaba que
     * anular o corregir un pago dejaba mintiendo a todas las filas anteriores.
     *
     * Quien necesite el estado de cobranza lo pide al pedido, que es su dueño.
     */
    public function up(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }

    /**
     * Al revertir se reconstruye el valor histórico: para cada pago, el
     * estado que tenía el saldo del pedido justo después de registrarlo.
     */
    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->enum('estado', ['PENDIENTE', 'PAGADO', 'PARCIAL'])->default('PARCIAL')->after('metodo_pago');
        });

        DB::table('pago')->orderBy('pedido_id')->orderBy('id')->each(function (object $pago): void {
            $acumulado = (float) DB::table('pago')
                ->where('pedido_id', $pago->pedido_id)
                ->where('id', '<=', $pago->id)
                ->sum('monto');

            $total = (float) DB::table('pedido')->where('id', $pago->pedido_id)->value('total');

            DB::table('pago')->where('id', $pago->id)
                ->update(['estado' => $acumulado >= $total ? 'PAGADO' : 'PARCIAL']);
        });
    }
};
