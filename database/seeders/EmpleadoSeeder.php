<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmpleadoSeeder extends Seeder
{
    /**
     * Plantel de prueba (nombres ficticios), repartido por sucursal y área.
     *
     * Los vendedores llevan un `perfil` que HistorialComercialSeeder usa para
     * que el BI tenga diferencias reales entre ellos:
     * - estrella: más cotizaciones, mejor tasa de aprobación;
     * - regular: el promedio;
     * - descuentero: cierra bien pero regala margen (descuentos altos);
     * - inactivo_reciente: vendía normal y dejó de cotizar hace ~5 semanas
     *   (debe aparecer como anomalía "vendedor sin actividad").
     *
     * @var list<array{nombres: string, paterno: string, materno: string, ci: string, cargo: string, sucursal: string, area: string, ingreso: string, perfil?: string}>
     */
    public const EMPLEADOS = [
        // El Alto · Casa matriz
        ['nombres' => 'Rolando', 'paterno' => 'Quispe', 'materno' => 'Mamani', 'ci' => '4120011', 'cargo' => 'Gerente', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Administración', 'ingreso' => '2018-02-01'],
        ['nombres' => 'Patricia', 'paterno' => 'Limachi', 'materno' => 'Ortiz', 'ci' => '4120012', 'cargo' => 'Administrador', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Administración', 'ingreso' => '2019-03-15'],
        ['nombres' => 'Edwin', 'paterno' => 'Choque', 'materno' => 'Callisaya', 'ci' => '4120013', 'cargo' => 'Contador', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Administración', 'ingreso' => '2020-01-10'],
        ['nombres' => 'Lourdes', 'paterno' => 'Apaza', 'materno' => 'Tola', 'ci' => '4120014', 'cargo' => 'Secretaria', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Ventas', 'ingreso' => '2021-06-01'],
        ['nombres' => 'Freddy', 'paterno' => 'Condori', 'materno' => 'Huanca', 'ci' => '4120015', 'cargo' => 'Jefe de Producción', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Acabado', 'ingreso' => '2018-08-20'],
        ['nombres' => 'Cintia', 'paterno' => 'Mendoza', 'materno' => 'Paz', 'ci' => '4120016', 'cargo' => 'Diseñador Gráfico', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Diseño', 'ingreso' => '2022-02-14'],
        ['nombres' => 'Brayan', 'paterno' => 'Ticona', 'materno' => 'Laura', 'ci' => '4120017', 'cargo' => 'Diseñador Gráfico', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Diseño', 'ingreso' => '2023-05-02'],
        ['nombres' => 'Julio', 'paterno' => 'Alanoca', 'materno' => 'Copa', 'ci' => '4120018', 'cargo' => 'Operario de Producción', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Gigantografía', 'ingreso' => '2021-09-01'],
        ['nombres' => 'Víctor', 'paterno' => 'Poma', 'materno' => 'Gutiérrez', 'ci' => '4120019', 'cargo' => 'Operario de Producción', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Cerrajería', 'ingreso' => '2020-11-16'],
        ['nombres' => 'René', 'paterno' => 'Flores', 'materno' => 'Yujra', 'ci' => '4120020', 'cargo' => 'Operario de Producción', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Carpintería', 'ingreso' => '2022-07-04'],
        ['nombres' => 'Daniel', 'paterno' => 'Huarachi', 'materno' => 'Calle', 'ci' => '4120021', 'cargo' => 'Vendedor', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Ventas', 'ingreso' => '2019-04-01', 'perfil' => 'estrella'],
        ['nombres' => 'Noemí', 'paterno' => 'Laura', 'materno' => 'Chura', 'ci' => '4120022', 'cargo' => 'Vendedor', 'sucursal' => 'El Alto · Casa matriz', 'area' => 'Ventas', 'ingreso' => '2022-10-03', 'perfil' => 'regular'],

        // La Paz · Sopocachi
        ['nombres' => 'Alejandra', 'paterno' => 'Villegas', 'materno' => 'Rivera', 'ci' => '4120031', 'cargo' => 'Vendedor', 'sucursal' => 'La Paz · Sopocachi', 'area' => 'Ventas', 'ingreso' => '2020-02-17', 'perfil' => 'descuentero'],
        ['nombres' => 'Gonzalo', 'paterno' => 'Siles', 'materno' => 'Prado', 'ci' => '4120032', 'cargo' => 'Vendedor', 'sucursal' => 'La Paz · Sopocachi', 'area' => 'Ventas', 'ingreso' => '2021-01-11', 'perfil' => 'inactivo_reciente'],
        ['nombres' => 'Mariela', 'paterno' => 'Aguilar', 'materno' => 'Soto', 'ci' => '4120033', 'cargo' => 'Diseñador Gráfico', 'sucursal' => 'La Paz · Sopocachi', 'area' => 'Diseño', 'ingreso' => '2023-03-06'],
        ['nombres' => 'Karen', 'paterno' => 'Mollinedo', 'materno' => 'Vera', 'ci' => '4120034', 'cargo' => 'Secretaria', 'sucursal' => 'La Paz · Sopocachi', 'area' => 'Ventas', 'ingreso' => '2022-08-22'],

        // Cochabamba · Centro
        ['nombres' => 'Esteban', 'paterno' => 'Claros', 'materno' => 'Rojas', 'ci' => '4120041', 'cargo' => 'Vendedor', 'sucursal' => 'Cochabamba · Centro', 'area' => 'Ventas', 'ingreso' => '2020-06-08', 'perfil' => 'regular'],
        ['nombres' => 'Wilma', 'paterno' => 'Terrazas', 'materno' => 'Antezana', 'ci' => '4120042', 'cargo' => 'Operario de Producción', 'sucursal' => 'Cochabamba · Centro', 'area' => 'Acabado', 'ingreso' => '2021-04-19'],

        // Santa Cruz · Equipetrol
        ['nombres' => 'Carlos', 'paterno' => 'Justiniano', 'materno' => 'Áñez', 'ci' => '4120051', 'cargo' => 'Vendedor', 'sucursal' => 'Santa Cruz · Equipetrol', 'area' => 'Ventas', 'ingreso' => '2025-05-05', 'perfil' => 'estrella'],
        ['nombres' => 'Valeria', 'paterno' => 'Suárez', 'materno' => 'Roca', 'ci' => '4120052', 'cargo' => 'Vendedor', 'sucursal' => 'Santa Cruz · Equipetrol', 'area' => 'Ventas', 'ingreso' => '2025-06-02', 'perfil' => 'regular'],
        ['nombres' => 'Marco', 'paterno' => 'Saucedo', 'materno' => 'Peña', 'ci' => '4120053', 'cargo' => 'Diseñador Gráfico', 'sucursal' => 'Santa Cruz · Equipetrol', 'area' => 'Diseño', 'ingreso' => '2025-06-02'],
    ];

    /**
     * Cuentas de prueba (solo fuera de producción) para revisar el sistema
     * con distintos alcances: gerencia ve toda la empresa; el vendedor, solo
     * su sucursal. Clave `admin123`.
     *
     * @var array<string, array{ci: string, rol: string, alcance: string}>
     */
    public const CUENTAS = [
        'gerente@gmail.com' => ['ci' => '4120011', 'rol' => 'gerente', 'alcance' => 'TODAS'],
        'vendedor@gmail.com' => ['ci' => '4120031', 'rol' => 'vendedor', 'alcance' => 'PROPIA'],
    ];

    /**
     * Idempotente (por CI).
     */
    public function run(): void
    {
        $sucursales = Sucursal::query()->pluck('id', 'nombre');
        $areas = Area::query()->pluck('id', 'nombre');

        foreach (self::EMPLEADOS as $i => $empleado) {
            $sucursalId = $sucursales[$empleado['sucursal']] ?? $sucursales->first();
            $areaId = $areas[$empleado['area']] ?? $areas->first();

            if ($sucursalId === null || $areaId === null) {
                continue;
            }

            Empleado::firstOrCreate(
                ['ci' => $empleado['ci']],
                [
                    'sucursal_id' => $sucursalId,
                    'area_id' => $areaId,
                    'nombres' => $empleado['nombres'],
                    'paterno' => $empleado['paterno'],
                    'materno' => $empleado['materno'],
                    'cargo' => $empleado['cargo'],
                    'telefono' => '7'.str_pad((string) (3100000 + $i * 6007), 7, '0', STR_PAD_LEFT),
                    'fecha_ingreso' => $empleado['ingreso'],
                    'estado' => 'ACTIVO',
                ],
            );
        }

        if (! app()->environment('production')) {
            $this->crearCuentasDePrueba();
        }

        $this->vincularCuentasDeAcceso();
    }

    private function crearCuentasDePrueba(): void
    {
        foreach (self::CUENTAS as $email => $cuenta) {
            $empleado = Empleado::query()->where('ci', $cuenta['ci'])->first();

            if ($empleado === null) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $empleado->nombre_completo,
                    'password' => Hash::make('admin123'),
                    'estado' => 'ACTIVO',
                    'alcance_sucursal' => $cuenta['alcance'],
                ],
            );

            $user->syncRoles([$cuenta['rol']]);

            if ($empleado->user_id === null) {
                $empleado->update(['user_id' => $user->id]);
            }
        }
    }

    /**
     * Le da ficha de empleado a las cuentas del personal que no la tienen.
     *
     * No es cosmético: el scoping por sucursal de Pedidos
     * (`Pedido::visiblePara()`) resuelve la sucursal del usuario a través de
     * `users -> empleado`, y sin esa ficha devuelve CERO pedidos (falla
     * cerrado, a propósito). Las cuentas del portal de clientes no son
     * personal: no reciben ficha.
     */
    private function vincularCuentasDeAcceso(): void
    {
        $sinFicha = User::query()
            ->whereDoesntHave('empleado')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'cliente'))
            ->get();

        if ($sinFicha->isEmpty()) {
            return;
        }

        // Primero el personal administrativo: es el que tiene sentido para
        // una cuenta genérica (super-admin, test@example.com).
        $disponibles = Empleado::query()
            ->whereNull('user_id')
            ->orderByRaw("CASE cargo WHEN 'Administrador' THEN 0 WHEN 'Contador' THEN 1 ELSE 2 END")
            ->get();

        foreach ($sinFicha as $indice => $user) {
            $empleado = $disponibles->get($indice);

            if ($empleado === null) {
                return;
            }

            $empleado->update(['user_id' => $user->id]);
        }
    }
}
