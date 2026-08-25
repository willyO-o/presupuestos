<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * Siembra los departamentos fijos del organigrama (ver
     * database-design.md y el comentario de la migración `area`). Se usa
     * `firstOrCreate` en vez de la factory para mantener nombres limpios
     * (sin sufijo aleatorio) y que el seeder sea idempotente.
     */
    public function run(): void
    {
        $areas = [
            'Ventas' => 'Cotización y atención al cliente.',
            'Diseño' => 'Elaboración del arte/diseño gráfico del pedido.',
            'Gigantografía' => 'Impresión de lonas, banners y gigantografías.',
            'Cerrajería' => 'Estructuras metálicas (bastidores, letreros).',
            'Carpintería' => 'Estructuras de madera y MDF (exhibidores, muebles).',
            'Acabado' => 'Terminación, ensamblado e instalación final.',
            'Administración' => 'Gestión administrativa y financiera.',
        ];

        foreach ($areas as $nombre => $descripcion) {
            Area::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion, 'estado' => 'ACTIVO'],
            );
        }
    }
}
