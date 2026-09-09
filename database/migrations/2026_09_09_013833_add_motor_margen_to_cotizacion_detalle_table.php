<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de margen a nivel de línea: cada ítem del presupuesto se cotiza
     * con su propio nivel de complejidad, igual que una hoja del Excel
     * original. `factor_complejidad` y `margen_aplicado` se guardan como
     * FOTO del tipo de proyecto al momento de cotizar — si mañana cambian los
     * valores en el CRUD, el documento histórico sigue explicando su propio
     * precio.
     *
     * Todos los costos son POR UNIDAD (igual que la receta/BOM); `cantidad`
     * los multiplica. `instalacion` es un monto manual del trabajo completo
     * (no se multiplica por cantidad) y, como en el Excel, se suma después
     * del IVA.
     */
    public function up(): void
    {
        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->foreignId('tipo_proyecto_id')->nullable()->after('producto_id')
                ->constrained('tipo_proyecto')->restrictOnDelete();
            $table->decimal('costo_base', 10, 2)->default(0)->after('cantidad');
            $table->decimal('factor_complejidad', 5, 2)->default(1)->after('costo_base');
            $table->decimal('margen_aplicado', 6, 4)->default(0)->after('factor_complejidad');
            $table->decimal('costo_ajustado', 10, 2)->default(0)->after('margen_aplicado');
            // SI = el vendedor sobreescribió el precio que sugirió el motor.
            $table->enum('precio_manual', ['SI', 'NO'])->default('NO')->after('precio_unitario');
            $table->decimal('instalacion', 10, 2)->default(0)->after('precio_manual');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->dropForeign(['tipo_proyecto_id']);
            $table->dropColumn([
                'tipo_proyecto_id', 'costo_base', 'factor_complejidad',
                'margen_aplicado', 'costo_ajustado', 'precio_manual', 'instalacion',
            ]);
        });
    }
};
