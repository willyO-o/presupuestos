<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para las columnas por las que el sistema realmente filtra y
     * ordena (2026-09-09). Hasta acá solo existían los de PK, FK y `unique`,
     * así que todo listado y todo reporte de BI hacía full scan.
     *
     * Cada índice sigue el orden en que se usa: primero la columna de
     * igualdad (`estado`) y después la de rango/orden (`fecha`), que es como
     * MySQL puede aprovechar el índice completo — ver los scopes de los
     * modelos y App\Services\Reporte\*.
     */
    public function up(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            // Cotizaciones/Index filtra por estado y ordena por fecha desc.
            $table->index(['estado', 'fecha'], 'cotizacion_estado_fecha_index');
            // ReporteFinancieroService y el comando de vencimiento barren por fecha.
            $table->index('fecha', 'cotizacion_fecha_index');
        });

        Schema::table('pedido', function (Blueprint $table) {
            $table->index(['estado', 'fecha_pedido'], 'pedido_estado_fecha_index');
            $table->index('fecha_entrega_real', 'pedido_fecha_entrega_real_index');
        });

        Schema::table('compra', function (Blueprint $table) {
            $table->index(['estado', 'fecha'], 'compra_estado_fecha_index');
        });

        Schema::table('pago', function (Blueprint $table) {
            $table->index('fecha_pago', 'pago_fecha_pago_index');
        });

        Schema::table('material', function (Blueprint $table) {
            // Materiales/Index filtra por estado; el aviso de stock bajo
            // compara stock_actual contra stock_minimo sobre los ACTIVOS.
            $table->index(['estado', 'nombre'], 'material_estado_nombre_index');
        });

        Schema::table('producto', function (Blueprint $table) {
            $table->index(['estado', 'nombre'], 'producto_estado_nombre_index');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropIndex('cotizacion_estado_fecha_index');
            $table->dropIndex('cotizacion_fecha_index');
        });

        Schema::table('pedido', function (Blueprint $table) {
            $table->dropIndex('pedido_estado_fecha_index');
            $table->dropIndex('pedido_fecha_entrega_real_index');
        });

        Schema::table('compra', function (Blueprint $table) {
            $table->dropIndex('compra_estado_fecha_index');
        });

        Schema::table('pago', function (Blueprint $table) {
            $table->dropIndex('pago_fecha_pago_index');
        });

        Schema::table('material', function (Blueprint $table) {
            $table->dropIndex('material_estado_nombre_index');
        });

        Schema::table('producto', function (Blueprint $table) {
            $table->dropIndex('producto_estado_nombre_index');
        });
    }
};
