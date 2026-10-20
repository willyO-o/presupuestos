<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logo opcional del cliente (ruta en el disco `public`, ya convertida a JPG
 * por App\Services\Imagen\ConvierteImagenAJpgService). Lo usa el cuadro
 * "Clientes que más compran" del BI; sin logo se muestran las iniciales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('ciudad');
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
