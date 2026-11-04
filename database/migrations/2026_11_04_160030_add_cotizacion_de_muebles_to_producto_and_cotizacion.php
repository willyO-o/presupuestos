<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizador de muebles: los muebles (islas, cabeceras, exhibidores) se
 * cotizan aparte de las lonas y demás trabajos de impresión, con su propio
 * asistente y su propia tarifa de mano de obra (el carpintero).
 *
 * - `producto.modo_cotizacion`: en qué cotizador aparece el producto. Un
 *   producto MUEBLE es una plantilla: su receta predefine los materiales.
 * - `producto.costo_mano_obra`: tarifa de mano de obra por mueble (Bs). Varía
 *   según el tipo de mueble; el vendedor puede ajustarla al cotizar.
 * - `cotizacion.tipo`: GENERAL o MUEBLE. No se mezclan en una cotización.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->enum('modo_cotizacion', ['GENERAL', 'MUEBLE'])->default('GENERAL')->after('cotizable_web');
            $table->decimal('costo_mano_obra', 10, 2)->nullable()->after('horas_mano_obra');
        });

        Schema::table('cotizacion', function (Blueprint $table) {
            $table->enum('tipo', ['GENERAL', 'MUEBLE'])->default('GENERAL')->after('codigo_verificacion');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->dropColumn('tipo');
        });

        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn(['modo_cotizacion', 'costo_mano_obra']);
        });
    }
};
