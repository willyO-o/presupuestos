<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ClienteSeeder extends Seeder
{
    /**
     * Cartera de clientes de prueba: nombres FICTICIOS con estructura de
     * mercado creíble para una empresa de publicidad y material POP.
     *
     * `segmento` alimenta a HistorialComercialSeeder:
     * - corporativo: pocas cuentas, compras grandes y frecuentes (el grupo
     *   Pareto que hace la mayor parte de las ventas);
     * - agencia: agencias de publicidad que tercerizan producción;
     * - pyme: comercios, compras chicas y esporádicas;
     * - natural: personas, compras únicas o casi.
     *
     * `ciudad` debe coincidir con la de alguna sucursal: el cliente compra
     * sobre todo en la sucursal de su ciudad.
     *
     * @var list<array{razon_social: string, tipo: string, nit: string, contacto: string, ciudad: string, segmento: string}>
     */
    public const CLIENTES = [
        // Corporativos
        ['razon_social' => 'Cervecería del Altiplano S.A.', 'tipo' => 'JURIDICO', 'nit' => '2011001018', 'contacto' => 'Gabriela Torrico', 'ciudad' => 'La Paz', 'segmento' => 'corporativo'],
        ['razon_social' => 'Bebidas Tropicales del Oriente S.A.', 'tipo' => 'JURIDICO', 'nit' => '2011001026', 'contacto' => 'Marcelo Suárez', 'ciudad' => 'Santa Cruz de la Sierra', 'segmento' => 'corporativo'],
        ['razon_social' => 'Telecomunicaciones Andinas S.A.', 'tipo' => 'JURIDICO', 'nit' => '2011001034', 'contacto' => 'Lucía Paredes', 'ciudad' => 'La Paz', 'segmento' => 'corporativo'],
        ['razon_social' => 'Banco Andino de Fomento S.A.', 'tipo' => 'JURIDICO', 'nit' => '2011001042', 'contacto' => 'Ricardo Velasco', 'ciudad' => 'La Paz', 'segmento' => 'corporativo'],
        ['razon_social' => 'Lácteos del Valle S.A.', 'tipo' => 'JURIDICO', 'nit' => '2011001050', 'contacto' => 'Carla Antezana', 'ciudad' => 'Cochabamba', 'segmento' => 'corporativo'],
        ['razon_social' => 'Supermercados Tunari S.R.L.', 'tipo' => 'JURIDICO', 'nit' => '2011001068', 'contacto' => 'Fernando Rojas', 'ciudad' => 'Cochabamba', 'segmento' => 'corporativo'],
        ['razon_social' => 'Farmacias Sajama S.A.', 'tipo' => 'JURIDICO', 'nit' => '2011001076', 'contacto' => 'Verónica Choque', 'ciudad' => 'El Alto', 'segmento' => 'corporativo'],
        ['razon_social' => 'Distribuidora Illimani S.R.L.', 'tipo' => 'JURIDICO', 'nit' => '2011001084', 'contacto' => 'Óscar Mamani', 'ciudad' => 'El Alto', 'segmento' => 'corporativo'],
        ['razon_social' => 'Cooperativa de Ahorro Pachamama Ltda.', 'tipo' => 'JURIDICO', 'nit' => '2011001092', 'contacto' => 'Rosa Apaza', 'ciudad' => 'El Alto', 'segmento' => 'corporativo'],
        ['razon_social' => 'Agroindustrias Guapay S.A.', 'tipo' => 'JURIDICO', 'nit' => '2011001105', 'contacto' => 'Diego Áñez', 'ciudad' => 'Santa Cruz de la Sierra', 'segmento' => 'corporativo'],

        // Agencias
        ['razon_social' => 'Kuntur Publicidad S.R.L.', 'tipo' => 'JURIDICO', 'nit' => '3022002013', 'contacto' => 'Andrea Gutiérrez', 'ciudad' => 'La Paz', 'segmento' => 'agencia'],
        ['razon_social' => 'Agencia Wiphala Creativa', 'tipo' => 'JURIDICO', 'nit' => '3022002021', 'contacto' => 'Sergio Ticona', 'ciudad' => 'El Alto', 'segmento' => 'agencia'],
        ['razon_social' => 'Oriente Brand Studio S.R.L.', 'tipo' => 'JURIDICO', 'nit' => '3022002039', 'contacto' => 'Natalia Justiniano', 'ciudad' => 'Santa Cruz de la Sierra', 'segmento' => 'agencia'],
        ['razon_social' => 'Valle Medios y Marketing', 'tipo' => 'JURIDICO', 'nit' => '3022002047', 'contacto' => 'Mauricio Claros', 'ciudad' => 'Cochabamba', 'segmento' => 'agencia'],
        ['razon_social' => 'Punto Activo BTL S.R.L.', 'tipo' => 'JURIDICO', 'nit' => '3022002055', 'contacto' => 'Daniela Pinto', 'ciudad' => 'La Paz', 'segmento' => 'agencia'],

        // Pymes
        ['razon_social' => 'Ferretería El Constructor', 'tipo' => 'JURIDICO', 'nit' => '4033003012', 'contacto' => 'Pedro Condori', 'ciudad' => 'El Alto', 'segmento' => 'pyme'],
        ['razon_social' => 'Panificadora Copacabana', 'tipo' => 'JURIDICO', 'nit' => '4033003020', 'contacto' => 'Elena Huanca', 'ciudad' => 'El Alto', 'segmento' => 'pyme'],
        ['razon_social' => 'Moda Urbana Chuquiago', 'tipo' => 'JURIDICO', 'nit' => '4033003038', 'contacto' => 'Kevin Alanoca', 'ciudad' => 'La Paz', 'segmento' => 'pyme'],
        ['razon_social' => 'Café Yungas Gourmet', 'tipo' => 'JURIDICO', 'nit' => '4033003046', 'contacto' => 'Sofía Arce', 'ciudad' => 'La Paz', 'segmento' => 'pyme'],
        ['razon_social' => 'Clínica Dental Sonrisa Andina', 'tipo' => 'JURIDICO', 'nit' => '4033003054', 'contacto' => 'Dr. Iván Salinas', 'ciudad' => 'La Paz', 'segmento' => 'pyme'],
        ['razon_social' => 'Gimnasio Fuerza Altura', 'tipo' => 'JURIDICO', 'nit' => '4033003062', 'contacto' => 'Wilson Poma', 'ciudad' => 'El Alto', 'segmento' => 'pyme'],
        ['razon_social' => 'Restaurante Sabores del Valle', 'tipo' => 'JURIDICO', 'nit' => '4033003070', 'contacto' => 'Mónica Vargas', 'ciudad' => 'Cochabamba', 'segmento' => 'pyme'],
        ['razon_social' => 'Librería y Papelería Tunupa', 'tipo' => 'JURIDICO', 'nit' => '4033003088', 'contacto' => 'Ana Ramos', 'ciudad' => 'Cochabamba', 'segmento' => 'pyme'],
        ['razon_social' => 'Automotriz Chiriguano S.R.L.', 'tipo' => 'JURIDICO', 'nit' => '4033003096', 'contacto' => 'Luis Melgar', 'ciudad' => 'Santa Cruz de la Sierra', 'segmento' => 'pyme'],
        ['razon_social' => 'Heladería Polar Camba', 'tipo' => 'JURIDICO', 'nit' => '4033003109', 'contacto' => 'Patricia Saucedo', 'ciudad' => 'Santa Cruz de la Sierra', 'segmento' => 'pyme'],
        ['razon_social' => 'Inmobiliaria Horizonte Sur', 'tipo' => 'JURIDICO', 'nit' => '4033003117', 'contacto' => 'Jorge Zeballos', 'ciudad' => 'La Paz', 'segmento' => 'pyme'],
        ['razon_social' => 'Óptica Visión Clara', 'tipo' => 'JURIDICO', 'nit' => '4033003125', 'contacto' => 'Carmen Flores', 'ciudad' => 'Cochabamba', 'segmento' => 'pyme'],
        ['razon_social' => 'Colegio Particular San Andrés del Alto', 'tipo' => 'JURIDICO', 'nit' => '4033003133', 'contacto' => 'Lic. Teresa Mamani', 'ciudad' => 'El Alto', 'segmento' => 'pyme'],
        ['razon_social' => 'Tienda Deportiva Gol de Oro', 'tipo' => 'JURIDICO', 'nit' => '4033003141', 'contacto' => 'Rodrigo Calle', 'ciudad' => 'Santa Cruz de la Sierra', 'segmento' => 'pyme'],
        ['razon_social' => 'Hotel Mirador Andino', 'tipo' => 'JURIDICO', 'nit' => '4033003159', 'contacto' => 'Silvia Cardozo', 'ciudad' => 'La Paz', 'segmento' => 'pyme'],
        ['razon_social' => 'Electrodomésticos Hogar Feliz', 'tipo' => 'JURIDICO', 'nit' => '4033003167', 'contacto' => 'Ramiro Ortiz', 'ciudad' => 'El Alto', 'segmento' => 'pyme'],
        ['razon_social' => 'Veterinaria Patitas', 'tipo' => 'JURIDICO', 'nit' => '4033003175', 'contacto' => 'Dra. Paula Rivero', 'ciudad' => 'Cochabamba', 'segmento' => 'pyme'],
        ['razon_social' => 'Pollos El Gallo de Oro', 'tipo' => 'JURIDICO', 'nit' => '4033003183', 'contacto' => 'Javier Loza', 'ciudad' => 'El Alto', 'segmento' => 'pyme'],
        ['razon_social' => 'Cosméticos Bella Flor', 'tipo' => 'JURIDICO', 'nit' => '4033003191', 'contacto' => 'Lorena Guzmán', 'ciudad' => 'Santa Cruz de la Sierra', 'segmento' => 'pyme'],

        // Personas naturales
        ['razon_social' => 'Juan Carlos Quisbert', 'tipo' => 'NATURAL', 'nit' => '6845123', 'contacto' => 'Juan Carlos Quisbert', 'ciudad' => 'El Alto', 'segmento' => 'natural'],
        ['razon_social' => 'María Eugenia Rocha', 'tipo' => 'NATURAL', 'nit' => '5521987', 'contacto' => 'María Eugenia Rocha', 'ciudad' => 'La Paz', 'segmento' => 'natural'],
        ['razon_social' => 'Fabiola Céspedes', 'tipo' => 'NATURAL', 'nit' => '7712450', 'contacto' => 'Fabiola Céspedes', 'ciudad' => 'Cochabamba', 'segmento' => 'natural'],
        ['razon_social' => 'Germán Ribera', 'tipo' => 'NATURAL', 'nit' => '4398211', 'contacto' => 'Germán Ribera', 'ciudad' => 'Santa Cruz de la Sierra', 'segmento' => 'natural'],
        ['razon_social' => 'Silvia Choquehuanca', 'tipo' => 'NATURAL', 'nit' => '8120934', 'contacto' => 'Silvia Choquehuanca', 'ciudad' => 'El Alto', 'segmento' => 'natural'],
        ['razon_social' => 'Andrés Villarroel', 'tipo' => 'NATURAL', 'nit' => '6034877', 'contacto' => 'Andrés Villarroel', 'ciudad' => 'La Paz', 'segmento' => 'natural'],
    ];

    /**
     * Idempotente (por NIT). Fuera de producción también crea 2 usuarios del
     * portal de clientes (`cliente0@gmail.com`/`cliente1@gmail.com`, clave
     * `cliente123`) vinculados a las dos primeras pymes.
     */
    public function run(): void
    {
        foreach (self::CLIENTES as $i => $cliente) {
            $slug = str($cliente['razon_social'])->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(24, '')->toString();

            Cliente::firstOrCreate(
                ['nit' => $cliente['nit']],
                [
                    'tipo' => $cliente['tipo'],
                    'razon_social' => $cliente['razon_social'],
                    'contacto_nombre' => $cliente['contacto'],
                    'telefono' => '7'.str_pad((string) (1200000 + $i * 7919 % 8000000), 7, '0', STR_PAD_LEFT),
                    'email' => "contacto@{$slug}.example",
                    'direccion' => null,
                    'ciudad' => $cliente['ciudad'],
                    'estado' => 'ACTIVO',
                ],
            );
        }

        if (app()->environment('production')) {
            return;
        }

        Role::findOrCreate('cliente', 'web');

        Cliente::query()->whereIn('nit', ['4033003012', '4033003020'])->orderBy('nit')->get()->each(function (Cliente $cliente, int $i): void {
            $user = User::firstOrCreate(
                ['email' => "cliente{$i}@gmail.com"],
                ['name' => $cliente->razon_social, 'password' => Hash::make('cliente123'), 'estado' => 'ACTIVO'],
            );
            $user->syncRoles(['cliente']);
            $cliente->update(['user_id' => $user->id]);
        });
    }
}
