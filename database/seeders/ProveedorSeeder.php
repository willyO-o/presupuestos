<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    /**
     * Proveedores de prueba (ficticios), uno por rubro de material: la
     * clave es la categoría de material que abastece (HistorialComercialSeeder
     * le compra a cada uno lo de su rubro).
     *
     * @var array<string, array{nombre: string, nit: string, contacto: string, telefono: string, direccion: string}>
     */
    public const PROVEEDORES = [
        'Gigantografía' => ['nombre' => 'Importadora Gráfica Andina S.R.L.', 'nit' => '1023456021', 'contacto' => 'Rubén Calle', 'telefono' => '2-2823011', 'direccion' => 'Av. Juan Pablo II Nro. 3100, El Alto'],
        'Cerrajería' => ['nombre' => 'Aceros del Altiplano S.A.', 'nit' => '1023456022', 'contacto' => 'Marta Quispe', 'telefono' => '2-2861144', 'direccion' => 'Carretera a Oruro Km 4, El Alto'],
        'Carpintería' => ['nombre' => 'Maderas y Tableros Chapare Ltda.', 'nit' => '1023456023', 'contacto' => 'Javier Rocha', 'telefono' => '4-4250099', 'direccion' => 'Av. Blanco Galindo Km 6, Cochabamba'],
        'Otros materiales' => ['nombre' => 'Acrílicos y Accesorios Illimani', 'nit' => '1023456024', 'contacto' => 'Paola Mendoza', 'telefono' => '2-2490210', 'direccion' => 'Calle Max Paredes Nro. 640, La Paz'],
        'Pinturas' => ['nombre' => 'Pinturas Monopol Distribución Sur', 'nit' => '1023456025', 'contacto' => 'Hugo Fernández', 'telefono' => '2-2440815', 'direccion' => 'Av. Buenos Aires Nro. 910, La Paz'],
    ];

    /**
     * Idempotente (por NIT).
     */
    public function run(): void
    {
        foreach (self::PROVEEDORES as $proveedor) {
            Proveedor::firstOrCreate(
                ['nit' => $proveedor['nit']],
                [...$proveedor, 'estado' => 'ACTIVO'],
            );
        }
    }
}
