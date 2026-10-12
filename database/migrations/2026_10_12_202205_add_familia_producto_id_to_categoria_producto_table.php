<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categoria_producto', function (Blueprint $table) {
            // Nullable: una categoría nueva puede quedar sin familia hasta
            // que alguien la clasifique (el BI la agrupa como "Sin familia").
            $table->foreignId('familia_producto_id')
                ->nullable()
                ->after('id')
                ->constrained('familia_producto')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria_producto', function (Blueprint $table) {
            $table->dropConstrainedForeignId('familia_producto_id');
        });
    }
};
