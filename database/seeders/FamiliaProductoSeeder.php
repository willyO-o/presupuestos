<?php

namespace Database\Seeders;

use App\Models\CategoriaProducto;
use App\Models\FamiliaProducto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FamiliaProductoSeeder extends Seeder
{
    /**
     * Familias comerciales y a qué categorías agrupan.
     *
     * Las categorías se asignan por palabras clave del nombre (sin acentos,
     * en minúsculas), así el seeder también clasifica categorías que se
     * crearon a mano con otros nombres ("Letras corpóreas", "Ploteo
     * vehicular"...). Solo toca categorías SIN familia: no pisa una
     * clasificación hecha desde el CRUD. Idempotente.
     *
     * @var array<string, array{descripcion: string, claves: list<string>}>
     */
    public const FAMILIAS = [
        'Impresión gran formato' => [
            'descripcion' => 'Lonas, banners, bastidores y gigantografías impresas.',
            'claves' => ['banner', 'lona', 'bastidor', 'gigant', 'impres', 'roll'],
        ],
        'Letreros y letras 3D' => [
            'descripcion' => 'Letreros luminosos, cajas de luz y letras corpóreas.',
            'claves' => ['letrero', 'letra', 'luminos', 'corpore', 'neon', 'caja de luz'],
        ],
        'Muebles y exhibidores' => [
            'descripcion' => 'Exhibidores, islas promocionales, stands y mobiliario de punto de venta.',
            'claves' => ['exhibidor', 'mueble', 'isla', 'stand', 'display', 'gondola'],
        ],
        'Rotulado y vinilos' => [
            'descripcion' => 'Vinilos de vidriera, microperforados y rotulado vehicular.',
            'claves' => ['vinil', 'vinyl', 'rotul', 'ploteo', 'vehic'],
        ],
        'Material POP' => [
            'descripcion' => 'Habladores, cenefas, stoppers y piezas chicas de punto de venta.',
            'claves' => ['pop', 'hablador', 'cenefa', 'stopper', 'afiche', 'volante'],
        ],
        'Toldos y estructuras' => [
            'descripcion' => 'Toldos publicitarios, estructuras metálicas y vallas.',
            'claves' => ['toldo', 'estructura', 'carpa', 'valla'],
        ],
    ];

    public function run(): void
    {
        $orden = 1;
        $familias = collect(self::FAMILIAS)->map(function (array $datos, string $nombre) use (&$orden) {
            return FamiliaProducto::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $datos['descripcion'], 'orden' => $orden++, 'estado' => 'ACTIVO'],
            );
        });

        CategoriaProducto::query()->whereNull('familia_producto_id')->each(function (CategoriaProducto $categoria) use ($familias): void {
            $nombre = Str::of($categoria->nombre)->ascii()->lower()->toString();

            foreach (self::FAMILIAS as $familia => $datos) {
                foreach ($datos['claves'] as $clave) {
                    if (str_contains($nombre, $clave)) {
                        $categoria->update(['familia_producto_id' => $familias[$familia]->id]);

                        return;
                    }
                }
            }
        });
    }
}
