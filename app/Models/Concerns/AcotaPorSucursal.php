<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Da a un modelo el scope `visiblePara($user)`, que lo acota a las sucursales
 * que ese usuario administra (ver `TieneAlcanceSucursal`).
 *
 * Solo DOS tablas del esquema llevan `sucursal_id` —`empleado` y `cotizacion`—;
 * el resto llega a la sucursal por herencia:
 *
 *     cotizacion.sucursal_id
 *        └── pedido.cotizacion_id
 *              ├── nota_entrega          ├── pago
 *              ├── orden_compra_cliente  └── seguimiento_postventa
 *
 * Por eso cada modelo declara nada más CÓMO llega (`rutaSucursal()`): una
 * implementación acá y una línea allá, en vez de repetir el `whereHas` en cada
 * controlador. `compra`, `material`, `proveedor`, `cliente` y `producto` NO
 * tienen sucursal (inventario y compras son de toda la empresa): no usan este
 * trait, y en pantalla se rotulan como dato global.
 *
 * Uso:
 *
 *     Pedido::query()->visiblePara($request->user())->paginate(10);
 *
 * **Falla cerrado**: si el usuario no ve ninguna sucursal (cuenta sin ficha de
 * empleado), `whereIn` con lista vacía compila a `0 = 1` y no devuelve filas.
 * Es deliberado — lo fija `AlcanceSucursalTest`.
 */
trait AcotaPorSucursal
{
    /**
     * Cómo llega este modelo a `cotizacion.sucursal_id` / `empleado.sucursal_id`.
     *
     * `null` = la columna `sucursal_id` es propia. Si no, la relación (admite
     * notación de punto: `'pedido.cotizacion'`).
     *
     * Es abstracto a propósito: equivocarse acá no rompe nada visible, solo
     * deja de filtrar. Que PHP obligue a escribirlo fuerza a pensarlo.
     */
    abstract protected static function rutaSucursal(): ?string;

    /**
     * Módulo de `config/acl.php` cuyo permiso `<modulo>.ver_todas_sucursales`
     * amplía el alcance solo para este modelo. `null` = sin override.
     *
     * Solo `Pedido` lo usa hoy (`pedidos.ver_todas_sucursales`, que ya
     * existía). Para habilitarlo en otro módulo: declarar el permiso en
     * `config/acl.php` y devolver su nombre acá.
     */
    protected static function moduloSucursal(): ?string
    {
        return null;
    }

    /**
     * true si `$user` puede ver ESTE registro. Para las rutas de detalle
     * (`show`, `edit`, PDF), que reciben el modelo ya resuelto por el route
     * model binding y no pasan por el listado.
     *
     * Se responde volviendo a la base con el MISMO scope en vez de comparar
     * `sucursal_id` a mano: la comparación a mano es una segunda copia de la
     * regla, y el día que una diverja aparece el clásico "veo un registro en la
     * lista que después me da 403" (o peor, al revés). Cuesta una consulta
     * barata por `whereKey`.
     */
    public function esVisiblePara(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visiblePara($user)->exists();
    }

    /**
     * Restringe la consulta a lo que `$user` puede ver. Sin límite de
     * sucursal, no toca la consulta.
     */
    #[Scope]
    protected function visiblePara(Builder $query, User $user): void
    {
        $sucursales = $user->sucursalesVisibles(static::moduloSucursal());

        if ($sucursales === null) {
            return;
        }

        $ruta = static::rutaSucursal();

        if ($ruta === null) {
            $query->whereIn($query->qualifyColumn('sucursal_id'), $sucursales);

            return;
        }

        $query->whereHas(
            $ruta,
            fn (Builder $relacionada) => $relacionada->whereIn(
                $relacionada->qualifyColumn('sucursal_id'),
                $sucursales,
            ),
        );
    }
}
