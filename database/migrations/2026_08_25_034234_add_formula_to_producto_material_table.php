<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una línea de BOM ahora puede calcular su cantidad de forma dinámica
     * vía `formula_id` en vez del factor fijo `cantidad_por_unidad`. Por
     * eso `cantidad_por_unidad` pasa a nullable — exactamente una de las
     * dos debe estar presente (se valida en el modelo/Form Request, no se
     * puede expresar como XOR en el esquema). `formula_id` usa
     * restrictOnDelete: una fórmula en uso por una receta no se puede
     * borrar (misma política que los demás catálogos, ver
     * .ai/rules/migrations.md).
     */
    public function up(): void
    {
        Schema::table('producto_material', function (Blueprint $table) {
            $table->foreignId('formula_id')->nullable()->after('material_id')->constrained('formula')->restrictOnDelete();
            $table->decimal('cantidad_por_unidad', 10, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('producto_material', function (Blueprint $table) {
            $table->dropConstrainedForeignId('formula_id');
            $table->decimal('cantidad_por_unidad', 10, 4)->nullable(false)->change();
        });
    }
};
