<?php

namespace App\Http\Controllers;

use App\Http\Requests\SeguimientoPostventa\RegistrarSeguimientoRequest;
use App\Models\SeguimientoPostventa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Bandeja del último paso del flujo comercial: "Seguimiento 7 días" del
 * Proceso 3. Los seguimientos no se crean acá — los programa
 * App\Services\Pedido\ProgramarPostventaService cuando el pedido queda
 * ENTREGADO —, esta pantalla solo sirve para trabajarlos.
 */
class SeguimientoPostventaController extends Controller
{
    /**
     * Listado paginado con búsqueda por pedido/cliente y filtro por estado.
     * Por defecto muestra los PENDIENTES (la bandeja de trabajo), no todo el
     * histórico.
     */
    public function index(Request $request): Response
    {
        $estado = $request->query('estado', 'PENDIENTE');

        $seguimientos = SeguimientoPostventa::query()
            ->with([
                'pedido:id,cotizacion_id,numero_pedido,fecha_entrega_real,total',
                'pedido.cotizacion:id,cliente_id',
                'pedido.cotizacion.cliente:id,razon_social,telefono,email',
                'empleado:id,nombres,paterno,materno',
            ])
            ->search($request->query('search'))
            ->estado($estado)
            ->orderBy('fecha_programada')
            ->paginate(10)
            ->withQueryString();

        return inertia('SeguimientosPostventa/Index', [
            'seguimientos' => $seguimientos,
            'resumen' => $this->resumen(),
            'estados' => SeguimientoPostventa::ESTADOS,
            'medios' => SeguimientoPostventa::MEDIOS,
            'filters' => ['search' => $request->query('search'), 'estado' => $estado],
            'config' => [
                'dias_seguimiento' => (int) config('postventa.dias_seguimiento'),
                'satisfaccion_maxima' => (int) config('postventa.satisfaccion_maxima'),
            ],
            'pageTitle' => 'Seguimiento postventa',
            'breadcrumbs' => ['Ventas', 'Seguimiento postventa'],
        ]);
    }

    /**
     * Registra el resultado del contacto con el cliente. Un seguimiento ya
     * REALIZADO no se vuelve a tocar: es la constancia de una llamada que
     * ocurrió.
     */
    public function registrar(RegistrarSeguimientoRequest $request, SeguimientoPostventa $seguimientoPostventa): RedirectResponse
    {
        if ($seguimientoPostventa->estado === 'REALIZADO') {
            return redirect()->back()
                ->with('error', 'Este seguimiento ya fue registrado.');
        }

        $datos = $request->validated();

        $seguimientoPostventa->update([
            ...$datos,
            'empleado_id' => $request->user()->empleado?->id,
            'fecha_contacto' => now()->toDateString(),
        ]);

        return redirect()->back()
            ->with('success', 'Seguimiento postventa registrado correctamente.');
    }

    /**
     * Contadores de la bandeja: cuántos están vencidos (había que llamar y
     * no se llamó), cuántos vienen y cuántos clientes reportaron un problema
     * que sigue abierto.
     *
     * @return array<string, int|float|null>
     */
    private function resumen(): array
    {
        $realizados = SeguimientoPostventa::query()->where('estado', 'REALIZADO');

        return [
            'vencidos' => SeguimientoPostventa::query()->pendientesDeContacto()->count(),
            'pendientes' => SeguimientoPostventa::query()->where('estado', 'PENDIENTE')->count(),
            'requieren_accion' => SeguimientoPostventa::query()
                ->where('requiere_accion', 'SI')->where('estado', 'REALIZADO')->count(),
            'satisfaccion_promedio' => $realizados->clone()->whereNotNull('satisfaccion')->avg('satisfaccion'),
            'realizados' => $realizados->count(),
        ];
    }
}
