<?php

namespace App\Http\Controllers;

use App\Exceptions\FormulaInvalidaException;
use App\Http\Requests\Cotizador\CalcularEstimacionRequest;
use App\Http\Requests\Cotizador\GuardarEstimacionRequest;
use App\Models\CotizacionPublica;
use App\Services\Cotizador\CotizadorPublicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * Cotizador en línea del sitio público.
 *
 * Tercera página Blade del sitio (junto con la portada y la galería): tiene
 * que llegar renderizada para que Google la indexe, y no puede arrastrar el
 * bundle del panel. La interactividad la resuelve `resources/js/publico.js`,
 * unos pocos KB de JavaScript sin framework — y el formulario funciona igual
 * sin JavaScript, con un trabajo por envío.
 *
 * Reparto de responsabilidades: el navegador dice QUÉ se quiere cotizar, el
 * servidor decide CUÁNTO cuesta. Ningún precio que llegue en la petición se
 * usa nunca (ver App\Services\Cotizador\CotizadorPublicoService).
 */
class CotizadorPublicoController extends Controller
{
    public function __construct(
        private readonly CotizadorPublicoService $cotizador,
    ) {}

    /**
     * Formulario del cotizador.
     *
     * De paso deja en sesión el momento en que se abrió: es la marca contra
     * la que `GuardarEstimacionRequest` mide el tiempo mínimo de envío. Va en
     * sesión y no en un input oculto justamente para que no se pueda falsear.
     */
    public function index(Request $request): View
    {
        $request->session()->put(GuardarEstimacionRequest::SESION_ABIERTO_EN, time());

        $productos = $this->cotizador->catalogo();

        return view('publico.cotizador', [
            'productos' => $productos,
            'agrupados' => $productos->groupBy(fn ($producto): string => $producto->categoriaProducto?->nombre ?? 'Otros'),
            'limites' => config('cotizador.limites'),
            'vigenciaDias' => (int) config('cotizador.vigencia_dias'),
            'holgura' => (float) config('cotizador.holgura'),
            'datosEstructurados' => $this->cotizadorJsonLd(),
        ]);
    }

    /**
     * Estimación en vivo, sin guardar nada (lo que llama el JS mientras el
     * visitante cambia medidas o cantidades).
     */
    public function calcular(CalcularEstimacionRequest $request): JsonResponse
    {
        try {
            return response()->json($this->cotizador->estimar($request->validated('lineas')));
        } catch (InvalidArgumentException|FormulaInvalidaException $e) {
            return response()->json(['error' => $this->mensajePublico($e)], 422);
        }
    }

    /**
     * Guarda la estimación con su código y su vigencia.
     *
     * El detalle se vuelve a calcular acá desde cero: lo que el visitante vio
     * en pantalla salió de `calcular()`, pero entre una llamada y otra pudo
     * cambiar el precio de un material — y, sobre todo, nada impide mandar un
     * POST directo con los números que se quiera. Lo que se guarda es
     * siempre lo que dice el servidor.
     */
    public function store(GuardarEstimacionRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $estimacion = $this->cotizador->estimar($datos['lineas']);
        } catch (InvalidArgumentException|FormulaInvalidaException $e) {
            return back()->withInput()->with('error', $this->mensajePublico($e));
        }

        $cotizacion = CotizacionPublica::create([
            'codigo' => $this->generarCodigo(),
            'nombre' => $datos['nombre'],
            'empresa' => $datos['empresa'] ?? null,
            'email' => $datos['email'] ?? null,
            'telefono' => $datos['telefono'],
            'mensaje' => $datos['mensaje'] ?? null,
            'detalle' => $estimacion['lineas'],
            'subtotal' => $estimacion['subtotal'],
            'iva' => $estimacion['iva'],
            'total' => $estimacion['total'],
            'estimado_min' => $estimacion['estimado_min'],
            'estimado_max' => $estimacion['estimado_max'],
            'holgura' => $estimacion['holgura'],
            'vigencia_dias' => $estimacion['vigencia_dias'],
            'fecha_vencimiento' => $estimacion['fecha_vencimiento'],
            'estado' => 'NUEVA',
            'ip' => $request->ip(),
            // La columna es varchar(255): un User-Agent falsificado puede ser
            // arbitrariamente largo y reventar el INSERT.
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        // Quema la marca de tiempo: sin ella, un reenvío del mismo formulario
        // (F5 sobre el POST) vuelve a caer en el control de "demasiado
        // rápido" en vez de duplicar la solicitud.
        $request->session()->forget(GuardarEstimacionRequest::SESION_ABIERTO_EN);

        return redirect()->route('cotizador.show', $cotizacion->codigo);
    }

    /**
     * Consulta pública de una estimación por su código.
     *
     * Es la página que el visitante comparte con el vendedor. El código es
     * aleatorio y no correlativo justamente porque esta URL no pide login:
     * con `WEB-20260909-00001` cualquiera leería el contacto y el presupuesto
     * del resto probando números.
     */
    public function show(string $codigo): View
    {
        $cotizacion = $this->buscarPorCodigo($codigo);

        return view('publico.cotizacion', [
            'cotizacion' => $cotizacion,
            'vigente' => $cotizacion->estaVigente(),
        ]);
    }

    /**
     * La estimación de ese código, o 404. No se usa route model binding
     * porque la clave pública es `codigo` y no el id: exponer el id
     * autoincremental dejaría enumerar las estimaciones del resto.
     */
    private function buscarPorCodigo(string $codigo): CotizacionPublica
    {
        return CotizacionPublica::query()->where('codigo', $codigo)->firstOrFail();
    }

    /**
     * Documento imprimible de una estimación: la misma forma que el
     * presupuesto del panel (Pages/Cotizaciones/Show.vue), para que lo que el
     * visitante guarda como PDF se vea como un documento de la empresa y no
     * como una página web impresa.
     *
     * No se genera un PDF en el servidor: se sirve una página preparada para
     * imprimir y el navegador la guarda con "Imprimir → Guardar como PDF". Es
     * lo mismo que hace el panel (`window.print()` en Cotizaciones/Show), así
     * que el sistema no gana una dependencia de PDF por una sola pantalla.
     *
     * Dos frenos, y hacen falta los dos: el rate limiter por IP
     * (`cotizador-descargar`) y el tope por estimación (`puedeDescargar()`).
     * El limiter se renueva solo, así que sin el tope por fila un código
     * válido alcanza para pedir el documento indefinidamente.
     */
    public function documento(string $codigo): View|RedirectResponse
    {
        $cotizacion = $this->buscarPorCodigo($codigo);

        if (! $cotizacion->puedeDescargar()) {
            return redirect()->route('cotizador.show', $cotizacion->codigo)
                ->with('error', 'Este documento ya se emitió el máximo de veces. Escríbenos por WhatsApp con tu código y te lo reenviamos.');
        }

        $cotizacion->registrarDescarga();

        return view('publico.cotizacion-documento', [
            'cotizacion' => $cotizacion,
            'vigente' => $cotizacion->estaVigente(),
        ]);
    }

    /**
     * Traduce un fallo del motor a algo que se le pueda decir a un visitante,
     * y manda el original al log.
     *
     * Los mensajes de `CosteoProductoService` y `FormulaCalculator` están
     * escritos para quien mantiene el catálogo ("el producto «X» se cotiza por
     * METRO_LINEAL pero falta ancho", "la fórmula referencia `profundo`"): a
     * un visitante no le dicen nada y además cuentan cómo está armado el
     * sistema por dentro. Que ocurra significa que alguien marcó para la web
     * un producto cuya receta necesita datos que el cotizador no pide (por
     * ejemplo profundidad) — es un problema nuestro, y el log es donde tiene
     * que verse.
     */
    private function mensajePublico(Throwable $e): string
    {
        report($e);

        return 'No pudimos calcular ese trabajo en línea. Escríbenos y te lo cotizamos a la medida.';
    }

    /**
     * Código con el que el visitante retoma la conversación. Mismo formato
     * que `Cotizacion::codigo_verificacion` pero con prefijo WEB, para que en
     * un WhatsApp se distinga de un presupuesto formal a simple vista.
     */
    private function generarCodigo(): string
    {
        do {
            $codigo = 'WEB-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (CotizacionPublica::where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * JSON-LD del cotizador: se declara como una acción que se puede hacer en
     * el sitio, que es lo que puede aparecer como enlace directo en los
     * resultados de búsqueda.
     *
     * @return array<string, mixed>
     */
    private function cotizadorJsonLd(): array
    {
        $empresa = config('sitio.empresa');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => route('inicio')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Cotizador', 'item' => route('cotizador')],
                    ],
                ],
                [
                    '@type' => 'WebApplication',
                    'name' => 'Cotizador en línea de '.$empresa['nombre'],
                    'applicationCategory' => 'BusinessApplication',
                    'url' => route('cotizador'),
                    'operatingSystem' => 'Web',
                    'inLanguage' => 'es-BO',
                    'description' => 'Calcula en línea el precio aproximado de exhibidores, banners, '
                        .'letreros y rotulado, y recibe un código para continuar con un asesor.',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'BOB',
                    ],
                    'provider' => ['@id' => route('inicio').'#empresa'],
                ],
            ],
        ];
    }
}
