<?php

namespace App\Models\Concerns;

use App\Models\Empleado;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Alcance de sucursales de una cuenta de usuario (`App\Models\User`).
 *
 * ES EL ÚNICO SITIO donde se decide qué sucursales ve alguien. Antes la regla
 * estaba copiada en `Pedido::visiblePara()`, `PedidoController::puedeVer()` y
 * `DocumentoPdfController` — tres copias del mismo `if` para un solo módulo.
 * Cualquier pantalla, consulta o Form Request nuevo pregunta acá; no vuelvas a
 * escribir la condición a mano.
 *
 * Tres modos (`users.alcance_sucursal`), más dos atajos que los saltan:
 *
 * - `PROPIA` (default) — la sucursal de la ficha de empleado. Es el
 *   comportamiento histórico: la migración no cambió lo que ve nadie.
 * - `ASIGNADAS` — las marcadas en el pivote `sucursal_user`. Sirve para el
 *   contador regional o el vendedor que cubre dos plazas.
 * - `TODAS` — sin filtro.
 * - Rol global (`config('acl.sucursales.roles_globales')`) — ve todo por su
 *   rol, ignorando la columna.
 * - Override por módulo (`<modulo>.ver_todas_sucursales`) — amplía a todas
 *   SOLO en ese módulo, sin tocar la ficha. `pedidos.ver_todas_sucursales`
 *   sigue funcionando igual que siempre.
 *
 * **TRAMPA — `null` NO es lo mismo que `[]`**: `sucursalesVisibles()` devuelve
 * `null` para "sin filtro, ve todas" y `[]` para "no ve ninguna" (usuario sin
 * ficha de empleado, o `ASIGNADAS` sin nada marcado). Un `empty($ids)` los
 * confunde y le abre TODO a quien no debería ver nada. Comprobá siempre
 * `$ids === null` primero — es lo que hace `AcotaPorSucursal`.
 *
 * Lo que el trait espera del modelo que lo usa (`App\Models\User`):
 *
 * @property string $alcance_sucursal Columna `users.alcance_sucursal`.
 * @property-read Empleado|null $empleado Ficha de empleado (relación de User).
 * @property-read Collection<int, Sucursal> $sucursales
 */
trait TieneAlcanceSucursal
{
    /**
     * Valores de `users.alcance_sucursal`. Accesibles como `User::ALCANCES`.
     *
     * @var list<string>
     */
    public const ALCANCES = ['PROPIA', 'ASIGNADAS', 'TODAS'];

    /**
     * Cache por request del cálculo de alcance, indexada por módulo ('' = sin
     * módulo). `sucursalesVisibles()` se llama una vez por consulta scopeada y
     * otra por cada Form Request; sin esto, cada llamada vuelve a pegarle a
     * Spatie y al pivote.
     *
     * @var array<string, list<int>|null>
     */
    private array $alcanceResuelto = [];

    /**
     * Sucursales asignadas a mano a esta cuenta (solo se leen con alcance
     * `ASIGNADAS`).
     */
    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'sucursal_user')->withTimestamps();
    }

    /**
     * true si el usuario no tiene ningún límite de sucursal.
     *
     * @param  string|null  $modulo  Módulo para el override
     *                               `<modulo>.ver_todas_sucursales` (ej. 'pedidos').
     */
    public function veTodasLasSucursales(?string $modulo = null): bool
    {
        return $this->sucursalesVisibles($modulo) === null;
    }

    /**
     * IDs de sucursal que este usuario puede ver.
     *
     * @param  string|null  $modulo  Módulo para el override por permiso.
     * @return list<int>|null `null` = sin filtro (ve todas). Un array vacío =
     *                        no ve ninguna. Ver la TRAMPA del docblock de arriba.
     */
    public function sucursalesVisibles(?string $modulo = null): ?array
    {
        return $this->alcanceResuelto[$modulo ?? ''] ??= $this->resolverAlcance($modulo);
    }

    /**
     * Catálogo de sucursales que este usuario puede elegir, para los
     * desplegables (filtros de listado y selectores de formulario).
     *
     * Ofrecer una sucursal ajena es ofrecer un filtro que siempre devuelve
     * vacío —que parece un error del sistema, no una restricción— o, en un
     * formulario, dejar crear algo que después no se podrá ni abrir.
     *
     * @return Collection<int, Sucursal>
     */
    public function sucursalesDisponibles(bool $soloActivas = false): Collection
    {
        $visibles = $this->sucursalesVisibles();

        return Sucursal::query()
            ->when($soloActivas, fn ($q) => $q->estado('ACTIVO'))
            // `null` = sin límite. Ojo con confundirlo con `[]` (no ve
            // ninguna): ver la TRAMPA del docblock de arriba.
            ->when($visibles !== null, fn ($q) => $q->whereIn('id', $visibles ?? []))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'ciudad']);
    }

    /**
     * true si el usuario puede ver la sucursal dada. Un `null` (registro sin
     * sucursal) solo lo ve quien no tiene límite.
     */
    public function puedeVerSucursal(?int $sucursalId, ?string $modulo = null): bool
    {
        $visibles = $this->sucursalesVisibles($modulo);

        if ($visibles === null) {
            return true;
        }

        return $sucursalId !== null && in_array($sucursalId, $visibles, true);
    }

    /**
     * Descarta el alcance memorizado. Hay que llamarlo tras cambiarle a la
     * cuenta el rol, el alcance o las sucursales asignadas dentro del mismo
     * request (ver `UsuarioController`): si no, lo que se lea después sigue
     * siendo el alcance de ANTES de guardar.
     */
    public function olvidarAlcanceSucursal(): void
    {
        $this->alcanceResuelto = [];
        $this->unsetRelation('sucursales');
    }

    /**
     * @return list<int>|null
     */
    private function resolverAlcance(?string $modulo): ?array
    {
        if ($this->hasAnyRole(config('acl.sucursales.roles_globales', []))) {
            return null;
        }

        if ($this->alcance_sucursal === 'TODAS') {
            return null;
        }

        // El override solo amplía. Se consulta después del alcance para que un
        // usuario que ya ve todo no pague la búsqueda de permisos.
        if ($modulo !== null && $this->can($modulo.'.'.config('acl.sucursales.sufijo_override'))) {
            return null;
        }

        if ($this->alcance_sucursal === 'ASIGNADAS') {
            return $this->sucursales->pluck('id')->map(intval(...))->values()->all();
        }

        // PROPIA: la ficha de empleado. Sin ficha no ve ninguna — falla
        // cerrado, igual que el `whereRaw('1 = 0')` que había en Pedido.
        $propia = $this->empleado?->sucursal_id;

        return $propia === null ? [] : [(int) $propia];
    }
}
