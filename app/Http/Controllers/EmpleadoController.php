<?php

namespace App\Http\Controllers;

use App\Http\Requests\Empleado\StoreEmpleadoRequest;
use App\Http\Requests\Empleado\UpdateEmpleadoRequest;
use App\Models\Area;
use App\Models\Empleado;
use App\Models\Sucursal;
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
            ->search($request->query('search'))
            ->sucursalId($request->query('sucursal'))
            ->areaId($request->query('area'))
            ->estado($request->query('estado'))
            ->orderBy('nombres')
            ->paginate(10)
            ->withQueryString();

        return inertia('Empleados/Index', [
            'empleados' => $empleados,
            'sucursales' => Sucursal::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::query()->estado('ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            // Cuentas de acceso disponibles para vincular a la ficha del
            // empleado (opcional: no todo empleado necesita login). Un
            // usuario ya vinculado a otro empleado se rechaza al guardar
            // (Rule::unique en el Form Request), no se filtra aquí.
            'usuarios' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
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
        $empleado->update($request->validated());

        return redirect()->route('empleados.index')
            ->with('success', 'Empleado actualizado correctamente.');
    }

    public function destroy(Empleado $empleado): RedirectResponse
    {
        $empleado->delete();

        return redirect()->route('empleados.index')
            ->with('success', 'Empleado eliminado correctamente.');
    }
}
