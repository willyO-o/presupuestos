<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    /**
     * Sucursales de prueba de una empresa de publicidad con planta en El Alto.
     *
     * `abre_mes`: mes (contado desde el inicio del historial de 24 meses de
     * HistorialComercialSeeder) en que la sucursal empieza a cotizar. Santa
     * Cruz abre más tarde: así el BI muestra el efecto de una apertura en la
     * curva de ventas. `peso`: participación aproximada en las ventas.
     *
     * @var list<array{nombre: string, ciudad: string, direccion: string, telefono: string, abre_mes: int, peso: float}>
     */
    public const SUCURSALES = [
        ['nombre' => 'El Alto · Casa matriz', 'ciudad' => 'El Alto', 'direccion' => 'Av. 6 de Marzo Nro. 1450, Zona 12 de Octubre', 'telefono' => '2-2851200', 'abre_mes' => 0, 'peso' => 0.34],
        ['nombre' => 'La Paz · Sopocachi', 'ciudad' => 'La Paz', 'direccion' => 'Av. 20 de Octubre Nro. 2310', 'telefono' => '2-2419800', 'abre_mes' => 0, 'peso' => 0.30],
        ['nombre' => 'Cochabamba · Centro', 'ciudad' => 'Cochabamba', 'direccion' => 'Av. Ballivián Nro. 880', 'telefono' => '4-4521300', 'abre_mes' => 0, 'peso' => 0.16],
        ['nombre' => 'Santa Cruz · Equipetrol', 'ciudad' => 'Santa Cruz de la Sierra', 'direccion' => 'Av. San Martín Nro. 1200, Equipetrol', 'telefono' => '3-3412700', 'abre_mes' => 8, 'peso' => 0.20],
    ];

    /**
     * Idempotente: no duplica al correr `db:seed` de nuevo.
     */
    public function run(): void
    {
        foreach (self::SUCURSALES as $sucursal) {
            Sucursal::firstOrCreate(
                ['nombre' => $sucursal['nombre']],
                [
                    'ciudad' => $sucursal['ciudad'],
                    'direccion' => $sucursal['direccion'],
                    'telefono' => $sucursal['telefono'],
                    'estado' => 'ACTIVO',
                ],
            );
        }
    }
}
