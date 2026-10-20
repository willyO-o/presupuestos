<?php

namespace App\Http\Controllers;

use App\Http\Requests\Seguimiento\BuscarPedidoRequest;
use App\Models\Pedido;
use App\Services\Pedido\SeguimientoPublicoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Seguimiento público de pedidos (`/seguimiento`), en Blade como el resto del
 * sitio público y sin una línea de JavaScript.
 *
 * El acceso pide DOS datos: el número de pedido y los últimos 4 dígitos del
 * teléfono o NIT del cliente. Al verificarlos, el id del pedido queda en la
 * SESIÓN, y `/seguimiento/{numero}` solo se muestra a una sesión que lo
 * verificó: así la página se puede recargar, pero el enlace reenviado por
 * WhatsApp no le abre el pedido a quien lo reciba sin los dígitos.
 */
class SeguimientoPedidoController extends Controller
{
    /**
     * Clave de sesión con los ids de pedido ya verificados en este navegador.
     */
    public const SESION_VERIFICADOS = 'seguimiento.pedidos';

    public function __construct(
        private readonly SeguimientoPublicoService $seguimiento,
    ) {}

    /**
     * Formulario de consulta. Acepta `?pedido=PED-...` para llegar con el
     * número ya escrito: es el enlace que el vendedor le pasa al cliente.
     */
    public function index(Request $request): View
    {
        return view('publico.seguimiento', [
            'numeroSugerido' => mb_strtoupper(trim((string) $request->query('pedido', ''))),
            'pasos' => SeguimientoPublicoService::PASOS,
        ]);
    }

    public function buscar(BuscarPedidoRequest $request): RedirectResponse
    {
        $pedido = $this->seguimiento->buscar($request->validated('numero'), $request->validated('digitos'));

        if ($pedido === null) {
            return redirect()->route('seguimiento')
                ->withInput($request->only('numero'))
                ->withErrors(['busqueda' => 'No encontramos un pedido con esos datos. Revisa el número y los 4 dígitos.']);
        }

        // Regenera el id de sesión al "subir de privilegio", como un login.
        $request->session()->regenerate();

        $verificados = collect($request->session()->get(self::SESION_VERIFICADOS, []))
            ->reject(fn (int $id): bool => $id === $pedido->id)
            ->push($pedido->id)
            ->take(-config('seguimiento.pedidos_por_sesion'))
            ->values()
            ->all();

        $request->session()->put(self::SESION_VERIFICADOS, $verificados);

        return redirect()->route('seguimiento.show', $pedido->numero_pedido);
    }

    /**
     * Estado del pedido. `noindex` en el layout, `Disallow` en robots.txt y
     * `no-store`: lleva montos y el nombre del cliente, y no puede quedar en
     * la caché de un proxy ni de una computadora compartida.
     */
    public function show(Request $request, string $numero): Response|RedirectResponse
    {
        $pedido = Pedido::query()->where('numero_pedido', $numero)->first();

        if ($pedido === null || ! in_array($pedido->id, $request->session()->get(self::SESION_VERIFICADOS, []), true)) {
            // Misma salida exista o no el pedido: el formulario con el número
            // ya escrito, para que solo falten los dígitos.
            return redirect()->route('seguimiento', ['pedido' => $numero]);
        }

        return response()
            ->view('publico.seguimiento-pedido', [
                'seguimiento' => $this->seguimiento->resumen($pedido),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Olvida los pedidos verificados en este navegador (cibercafé, equipo de
     * la oficina compartido).
     */
    public function cerrar(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESION_VERIFICADOS);

        return redirect()->route('seguimiento')
            ->with('estado', 'Cerramos la consulta en este navegador.');
    }
}
