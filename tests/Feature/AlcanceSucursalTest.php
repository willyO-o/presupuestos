<?php

use App\Models\Concerns\AcotaPorSucursal;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Alcance por sucursal — `App\Models\Concerns\TieneAlcanceSucursal` y
 * `AcotaPorSucursal`.
 *
 * Es la frontera que decide qué datos de qué sucursal ve cada cuenta, así que
 * se prueba entera y por separado de los módulos que la consumen: si esto se
 * rompe, se rompe callado (se ve de más, no falla nada).
 *
 * Los dos casos que más importan son los que fallan CERRADO: una cuenta sin
 * ficha de empleado y una cuenta `ASIGNADAS` sin nada marcado no ven NADA, no
 * lo ven todo.
 */
uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Modelos de prueba
|--------------------------------------------------------------------------
|
| El trait se prueba contra las tablas reales (para que las relaciones y el
| SQL sean los de verdad) pero sobre subclases anónimas, no sobre los modelos
| del dominio: en esta fase todavía no se les conectó el trait, y este test no
| debe depender de ese cableado para pasar. Cubren los tres caminos posibles
| de `rutaSucursal()`.
|
*/

/** `sucursal_id` propia — el caso de `cotizacion` y `empleado`. */
function modeloColumnaPropia(): Cotizacion
{
    return new class extends Cotizacion
    {
        use AcotaPorSucursal;

        protected static function rutaSucursal(): ?string
        {
            return null;
        }
    };
}

/** Un salto de relación — el caso de `pedido`. */
function modeloUnSalto(): Pedido
{
    return new class extends Pedido
    {
        use AcotaPorSucursal;

        protected static function rutaSucursal(): ?string
        {
            return 'cotizacion';
        }
    };
}

/** Relación anidada + override por módulo — el caso de `pago`, `nota_entrega`… */
function modeloAnidado(): Pago
{
    return new class extends Pago
    {
        use AcotaPorSucursal;

        protected static function rutaSucursal(): ?string
        {
            return 'pedido.cotizacion';
        }

        protected static function moduloSucursal(): ?string
        {
            return 'pedidos';
        }
    };
}

/** Cuenta con ficha de empleado en la sucursal dada. */
function usuarioDeSucursal(Sucursal $sucursal, string $alcance = 'PROPIA'): User
{
    $user = User::factory()->create(['alcance_sucursal' => $alcance]);
    Empleado::factory()->create(['user_id' => $user->id, 'sucursal_id' => $sucursal->id]);

    return $user->fresh();
}

/*
|--------------------------------------------------------------------------
| Los tres modos de users.alcance_sucursal
|--------------------------------------------------------------------------
*/

test('PROPIA es el default y limita a la sucursal de la ficha de empleado', function () {
    // Default en la migración: la columna no cambió lo que veía nadie.
    $sucursal = Sucursal::factory()->create();
    $user = usuarioDeSucursal($sucursal);

    expect($user->alcance_sucursal)->toBe('PROPIA')
        ->and($user->sucursalesVisibles())->toBe([$sucursal->id])
        ->and($user->veTodasLasSucursales())->toBeFalse();
});

test('ASIGNADAS usa el pivote y NO agrega la sucursal de la ficha', function () {
    // El pivote es la lista literal: si el vendedor tiene que seguir viendo
    // la suya, se marca. Que se colara sola sería una regla invisible.
    $propia = Sucursal::factory()->create();
    $otra = Sucursal::factory()->create();

    $user = usuarioDeSucursal($propia, 'ASIGNADAS');
    $user->sucursales()->sync([$otra->id]);

    expect($user->fresh()->sucursalesVisibles())->toBe([$otra->id]);
});

test('ASIGNADAS puede cubrir varias sucursales a la vez', function () {
    // El caso que motivó todo esto: el contador regional.
    $sucursales = Sucursal::factory()->count(3)->create();
    $user = User::factory()->sucursalesAsignadas()->create();
    $user->sucursales()->sync($sucursales->pluck('id'));

    expect($user->fresh()->sucursalesVisibles())
        ->toEqualCanonicalizing($sucursales->pluck('id')->all());
});

test('TODAS no aplica ningun filtro', function () {
    $user = User::factory()->todasLasSucursales()->create();

    expect($user->sucursalesVisibles())->toBeNull()
        ->and($user->veTodasLasSucursales())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Fallar cerrado: null (todas) NO es lo mismo que [] (ninguna)
|--------------------------------------------------------------------------
*/

test('una cuenta sin ficha de empleado no ve NINGUNA sucursal, no todas', function () {
    // La confusión clásica: `empty($ids)` trataría esto como "sin filtro" y le
    // abriría la empresa entera a una cuenta recién creada.
    $user = User::factory()->create();

    expect($user->sucursalesVisibles())->toBe([])
        ->and($user->sucursalesVisibles())->not->toBeNull()
        ->and($user->veTodasLasSucursales())->toBeFalse();
});

test('ASIGNADAS sin nada marcado tampoco ve ninguna', function () {
    $user = User::factory()->sucursalesAsignadas()->create();

    expect($user->sucursalesVisibles())->toBe([]);
});

test('sin sucursales visibles la consulta devuelve cero filas, no todas', function () {
    // El `whereIn` con lista vacía tiene que compilar a `0 = 1`. Si algún día
    // cambiara, este test es el que avisa.
    Cotizacion::factory()->count(3)->create();

    $ciego = User::factory()->create();

    expect(modeloColumnaPropia()->newQuery()->visiblePara($ciego)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Atajos que saltan la columna: rol global y override por módulo
|--------------------------------------------------------------------------
*/

test('los roles globales ven todo aunque su columna diga PROPIA', function (string $rol) {
    Role::findOrCreate($rol, 'web');

    $user = User::factory()->create(['alcance_sucursal' => 'PROPIA']);
    $user->assignRole($rol);

    expect($user->sucursalesVisibles())->toBeNull();
})->with(['super-admin', 'administrador']);

test('los roles globales salen de config, no estan escritos en el codigo', function () {
    // Se resuelve por config y no con Gate::before a propósito: ese bypass le
    // daría al administrador TODOS los permisos, y hay varios que no tiene.
    expect(config('acl.sucursales.roles_globales'))
        ->toContain('super-admin')
        ->toContain('administrador');
});

test('el override por modulo amplia solo ese modulo', function () {
    // `pedidos.ver_todas_sucursales` ya existía y se conserva: amplía el
    // alcance sin tocar la ficha del usuario.
    Permission::findOrCreate('pedidos.ver_todas_sucursales', 'web');

    $sucursal = Sucursal::factory()->create();
    $user = usuarioDeSucursal($sucursal);
    $user->givePermissionTo('pedidos.ver_todas_sucursales');

    expect($user->sucursalesVisibles('pedidos'))->toBeNull()
        // Sin módulo, o en otro módulo, el permiso no aplica.
        ->and($user->sucursalesVisibles())->toBe([$sucursal->id])
        ->and($user->sucursalesVisibles('pagos'))->toBe([$sucursal->id]);
});

test('el override solo amplia: nunca recorta lo que el alcance ya permitia', function () {
    Permission::findOrCreate('pedidos.ver_todas_sucursales', 'web');

    $user = User::factory()->todasLasSucursales()->create();

    expect($user->sucursalesVisibles('pedidos'))->toBeNull()
        ->and($user->sucursalesVisibles('compras'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| puedeVerSucursal()
|--------------------------------------------------------------------------
*/

test('puedeVerSucursal respeta el alcance y rechaza el null salvo sin limite', function () {
    $propia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $acotado = usuarioDeSucursal($propia);
    $global = User::factory()->todasLasSucursales()->create();

    expect($acotado->puedeVerSucursal($propia->id))->toBeTrue()
        ->and($acotado->puedeVerSucursal($ajena->id))->toBeFalse()
        // Un registro sin sucursal solo lo ve quien no tiene límite.
        ->and($acotado->puedeVerSucursal(null))->toBeFalse()
        ->and($global->puedeVerSucursal($ajena->id))->toBeTrue()
        ->and($global->puedeVerSucursal(null))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| El scope visiblePara() sobre los tres caminos de rutaSucursal()
|--------------------------------------------------------------------------
*/

test('visiblePara filtra por la columna propia del modelo', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $visible = Cotizacion::factory()->create(['sucursal_id' => $mia->id]);
    Cotizacion::factory()->create(['sucursal_id' => $ajena->id]);

    $user = usuarioDeSucursal($mia);

    $ids = modeloColumnaPropia()->newQuery()->visiblePara($user)->pluck('id');

    expect($ids->all())->toBe([$visible->id]);
});

test('visiblePara filtra saltando una relacion', function () {
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $propio = Pedido::factory()->for(Cotizacion::factory()->create(['sucursal_id' => $mia->id]))->create();
    Pedido::factory()->for(Cotizacion::factory()->create(['sucursal_id' => $ajena->id]))->create();

    $user = usuarioDeSucursal($mia);

    $ids = modeloUnSalto()->newQuery()->visiblePara($user)->pluck('id');

    expect($ids->all())->toBe([$propio->id]);
});

test('visiblePara filtra por una relacion anidada', function () {
    // pago -> pedido -> cotizacion.sucursal_id: es el camino de la mayoría de
    // los documentos del sistema.
    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    $pedidoPropio = Pedido::factory()->for(Cotizacion::factory()->create(['sucursal_id' => $mia->id]))->create();
    $pedidoAjeno = Pedido::factory()->for(Cotizacion::factory()->create(['sucursal_id' => $ajena->id]))->create();

    $propio = Pago::factory()->create(['pedido_id' => $pedidoPropio->id]);
    Pago::factory()->create(['pedido_id' => $pedidoAjeno->id]);

    $user = usuarioDeSucursal($mia);

    $ids = modeloAnidado()->newQuery()->visiblePara($user)->pluck('id');

    expect($ids->all())->toBe([$propio->id]);
});

test('visiblePara no toca la consulta de quien ve todas', function () {
    Cotizacion::factory()->count(3)->create();

    $global = User::factory()->todasLasSucursales()->create();

    expect(modeloColumnaPropia()->newQuery()->visiblePara($global)->count())->toBe(3);
});

test('visiblePara aplica el override del modulo que declara el modelo', function () {
    // El modelo anidado declara moduloSucursal() = 'pedidos', así que el
    // permiso tiene que llegarle sin que el que consulta lo pase a mano.
    Permission::findOrCreate('pedidos.ver_todas_sucursales', 'web');

    $mia = Sucursal::factory()->create();
    $ajena = Sucursal::factory()->create();

    foreach ([$mia, $ajena] as $sucursal) {
        $pedido = Pedido::factory()->for(Cotizacion::factory()->create(['sucursal_id' => $sucursal->id]))->create();
        Pago::factory()->create(['pedido_id' => $pedido->id]);
    }

    $user = usuarioDeSucursal($mia);
    expect(modeloAnidado()->newQuery()->visiblePara($user)->count())->toBe(1);

    $user->givePermissionTo('pedidos.ver_todas_sucursales');
    expect(modeloAnidado()->newQuery()->visiblePara($user->fresh())->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Cache por request
|--------------------------------------------------------------------------
*/

test('el alcance se memoriza y olvidarAlcanceSucursal lo descarta', function () {
    // Sin olvidar, guardar sucursales nuevas en UsuarioController y volver a
    // leer el alcance en el mismo request devolvería el de ANTES de guardar.
    $primera = Sucursal::factory()->create();
    $segunda = Sucursal::factory()->create();

    $user = User::factory()->sucursalesAsignadas()->create();
    $user->sucursales()->sync([$primera->id]);

    expect($user->sucursalesVisibles())->toBe([$primera->id]);

    $user->sucursales()->sync([$segunda->id]);
    expect($user->sucursalesVisibles())->toBe([$primera->id]);

    $user->olvidarAlcanceSucursal();
    expect($user->sucursalesVisibles())->toBe([$segunda->id]);
});

/*
|--------------------------------------------------------------------------
| Esquema
|--------------------------------------------------------------------------
*/

test('el pivote no deja asignar dos veces la misma sucursal', function () {
    $sucursal = Sucursal::factory()->create();
    $user = User::factory()->sucursalesAsignadas()->create();

    $user->sucursales()->attach($sucursal->id);

    expect(fn () => $user->sucursales()->attach($sucursal->id))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('borrar la cuenta se lleva sus asignaciones', function () {
    $sucursal = Sucursal::factory()->create();
    $user = User::factory()->sucursalesAsignadas()->create();
    $user->sucursales()->attach($sucursal->id);

    $user->delete();

    expect(DB::table('sucursal_user')->count())->toBe(0);
});
