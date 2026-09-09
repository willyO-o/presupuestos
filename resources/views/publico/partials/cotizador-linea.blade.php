{{--
    Una línea del detalle del cotizador público.

    Se incluye dos veces desde `publico/cotizador.blade.php`: una como primera
    línea real (`$indice = 0`) y otra dentro de un <template>, con el índice
    literal `__INDICE__` que el JS reemplaza al clonarla. Por eso el marcado
    vive acá y no duplicado en una cadena de JavaScript: hay una sola versión
    del campo, y la que se ve sin JS es exactamente la misma que la que se
    agrega con JS.

    Variables: $indice, $agrupados (productos por categoría), $limites.
--}}
<div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-12" data-linea>
    <label class="block sm:col-span-12">
        <span class="mb-1 block text-xs font-semibold text-slate-700">Trabajo</span>
        <select
            name="lineas[{{ $indice }}][producto_id]"
            required
            data-campo="producto_id"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
        >
            <option value="">Selecciona un trabajo…</option>
            @foreach ($agrupados as $categoria => $productosCategoria)
                <optgroup label="{{ $categoria }}">
                    @foreach ($productosCategoria as $producto)
                        <option value="{{ $producto->id }}">{{ $producto->nombre }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </label>

    {{-- El JS oculta este bloque cuando el producto elegido no lleva medidas;
         sin JS queda visible con la ayuda de abajo, que es el comportamiento
         seguro (mejor pedir un dato de más que romper el envío). --}}
    <div class="grid gap-3 sm:col-span-8 sm:grid-cols-2" data-medidas>
        <label class="block">
            <span class="mb-1 block text-xs font-semibold text-slate-700">Ancho (m)</span>
            <input
                type="number" step="0.01" min="0.01" max="{{ $limites['dimension'] }}"
                name="lineas[{{ $indice }}][ancho]"
                data-campo="ancho"
                inputmode="decimal" placeholder="2.50"
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
            >
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-semibold text-slate-700">Alto (m)</span>
            <input
                type="number" step="0.01" min="0.01" max="{{ $limites['dimension'] }}"
                name="lineas[{{ $indice }}][alto]"
                data-campo="alto"
                inputmode="decimal" placeholder="1.20"
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
            >
        </label>
    </div>

    <label class="block sm:col-span-3">
        <span class="mb-1 block text-xs font-semibold text-slate-700">Cantidad</span>
        <input
            type="number" step="1" min="1" max="{{ $limites['cantidad'] }}" value="1" required
            name="lineas[{{ $indice }}][cantidad]"
            data-campo="cantidad"
            inputmode="numeric"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-marca-azul focus:outline-none focus:ring-1 focus:ring-marca-azul"
        >
    </label>

    {{-- Solo aparece cuando hay más de una línea: quitar la única que existe
         dejaría el formulario sin nada que enviar. Lo muestra el JS. --}}
    <div class="flex items-end sm:col-span-1">
        <button
            type="button"
            data-quitar-linea
            hidden
            class="flex h-11 w-full items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-400 transition-colors hover:border-red-300 hover:text-red-500"
            aria-label="Quitar este trabajo"
            title="Quitar este trabajo"
        >
            <x-publico.icono nombre="quitar" class="h-4 w-4" />
        </button>
    </div>

    <p class="text-[11px] leading-relaxed text-slate-400 sm:col-span-12" data-ayuda-medidas>
        Las medidas van en metros. Si el trabajo se vende por unidad, déjalas vacías.
    </p>
</div>
