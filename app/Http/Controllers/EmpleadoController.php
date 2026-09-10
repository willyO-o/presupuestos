<?php

namespace App\Http\Controllers;

use App\Http\Requests\Empleado\StoreEmpleadoRequest;
use App\Http\Requests\Empleado\UpdateEmpleadoRequest;
use App\Models\Area;
use App\Models\Empleado;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class EmpleadoController extends Controller
{
    /**
     * Listado paginado, con búsqueda (nombre/CI/cargo), filtros por
     * sucursal/área y por estado. `withQueryString()` mantiene
     * search/sucursal/area/estado/page al navegar entre páginas del
     * paginador.
     */
    public function index(Request $request): Response
    {
        $empleados = Empleado::query()
            ->with(['sucursal', 'area'])
            ->visiblePara($request->user())
            ->search($request->query('search'))
            ->sucursalId($request->query('sucursal'))
            ->areaId($request->query('area'))
            ->estado($request->query('estado'))
            ->orderBy('nombres')
            ->paginate(10)
            ->withQueryString();

        return inertia('Empleados/Index', [
            'empleados' => $empleados,
            // Ver PedidoController::index: el desplegable no ofrece sucursales
            // ajenas, que ademas el Form Request rechazaria al guardar.
            'sucursales' => $request->user()->sucursalesDisponibles(soloActivas: true),
            'areas' => Area::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            // Cuentas de acceso disponibles para vincular a la ficha del
            // empleado (opcional: no todo empleado necesita login). Un
            // usuario ya vinculado a otro empleado se rechaza al guardar
            // (Rule::unique en el Form Request), no se filtra aquí.
            'usuarios' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            // Los cargos salen de los roles del negocio en config/acl.php:
            // antes era texto libre que repetía esos mismos nombres.
            'cargos' => Empleado::cargos(),
            'filters' => $request->only(['search', 'sucursal', 'area', 'estado']),
            'pageTitle' => 'Empleados',
            'breadcrumbs' => ['Organización', 'Empleados'],
        ]);
    }

    public function store(StoreEmpleadoRequest $request): RedirectResponse
    {
        Empleado::create($request->validated());

        return redirect()->route('empleados.index')
            ->with('success', 'Empleado creado correctamente.');
    }

    public function update(UpdateEmpleadoRequest $request, Empleado $empleado): RedirectResponse
    {
        // La ficha destino tambien tiene que ser de una sucursal administrada:
        // el Form Request valida la sucursal NUEVA, esto valida la actual.
        abort_unless($empleado->esVisiblePara($request->user()), 403);

        $empleado->update($request->validated());

        $this->sincronizarAccesoDelUsuario($empleado);

        return redirect()->route('empleados.index')
            ->with('success', 'Empleado actualizado correctamente.');
    }

    /**
     * Dar de baja a un empleado también le quita el acceso al sistema: sin
     * esto, `empleado.estado` y `users.estado` eran dos banderas separadas
     * para la misma persona y un empleado dado de baja seguía pudiendo
     * entrar (`LoginRequest` solo mira `users.estado`).
     *
     * La reactivación NO es automática: volver a habilitar una cuenta es una
     * decisión de seguridad y se hace desde el módulo de Usuarios.
     */
    private function sincronizarAccesoDelUsuario(Empleado $empleado): void
    {
        if ($empleado->estado === 'INACTIVO') {
            $empleado->user?->update(['estado' => 'INACTIVO']);
        }
    }

    public function destroy(Request $request, Empleado $empleado): RedirectResponse
    {
        abort_unless($empleado->esVisiblePara($request->user()), 403);

        $empleado->delete();

        return redirect()->route('empleados.index')
            ->with('success', 'Empleado eliminado correctamente.');
    }
}
