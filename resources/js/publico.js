/**
 * Sitio público — interactividad del cotizador (`/cotizador`).
 *
 * Sin framework y sin dependencias a propósito. El resto del sitio público no
 * carga nada de JavaScript (ver `resources/views/components/publico/layout.blade.php`)
 * y esta página no puede ser la excepción que arrastre Vue, Inertia y el
 * bundle del panel: son ~1 MB contra los pocos KB de este archivo, y el LCP
 * es justamente lo que Google mide para posicionar.
 *
 * Reparto de responsabilidades, igual que en el backend: acá se arma la lista
 * de lo que el visitante quiere y se pinta lo que responde el servidor. Los
 * PRECIOS NO SE CALCULAN ACÁ. Este archivo no conoce el margen, el factor de
 * complejidad ni el costo de un material, y no debe conocerlos: son datos
 * internos de la empresa y esto se descarga en el navegador de cualquiera.
 *
 * Progresivo: si este archivo no carga (error de red, JS desactivado), el
 * formulario sigue siendo un <form> normal que postea a `cotizador.store` y
 * el servidor calcula igual. Lo único que se pierde es el previsualizado y
 * poder agregar más de una línea.
 */

const formulario = document.querySelector('[data-cotizador]');

if (formulario) {
    iniciarCotizador(formulario);
}

function iniciarCotizador(formulario) {
    const contenedorLineas = formulario.querySelector('[data-lineas]');
    const plantilla = document.querySelector('[data-plantilla-linea]');
    const botonAgregar = formulario.querySelector('[data-agregar-linea]');
    const maximoLineas = Number(formulario.dataset.maxLineas || 8);

    const productos = leerProductos();

    const panel = {
        vacio: formulario.querySelector('[data-estimado-vacio]'),
        error: formulario.querySelector('[data-estimado-error]'),
        resultado: formulario.querySelector('[data-estimado]'),
        lineas: formulario.querySelector('[data-estimado-lineas]'),
        subtotal: formulario.querySelector('[data-estimado-subtotal]'),
        iva: formulario.querySelector('[data-estimado-iva]'),
        rango: formulario.querySelector('[data-estimado-rango]'),
    };

    // Los controles que solo tienen sentido con JS nacen `hidden` en el HTML
    // y se revelan acá: sin este archivo nunca aparecen botones muertos.
    if (botonAgregar && plantilla) {
        botonAgregar.hidden = false;
        botonAgregar.addEventListener('click', () => agregarLinea());
    }

    formulario.addEventListener('input', alCambiar);
    formulario.addEventListener('change', alCambiar);

    formulario.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-quitar-linea]');

        if (boton) {
            boton.closest('[data-linea]').remove();
            renumerar();
            estimarConEspera();
        }
    });

    renumerar();
    sincronizarMedidas();

    /* ------------------------------------------------------------------ */
    /* Líneas                                                              */
    /* ------------------------------------------------------------------ */

    function agregarLinea() {
        const lineas = contenedorLineas.querySelectorAll('[data-linea]');

        if (lineas.length >= maximoLineas) {
            return;
        }

        // `__INDICE__` es el marcador que dejó el partial de Blade; se
        // reemplaza en el HTML antes de parsearlo para que los `name` salgan
        // ya numerados (lineas[3][ancho]).
        const marcado = plantilla.innerHTML.replaceAll('__INDICE__', String(lineas.length));
        const contenedor = document.createElement('div');
        contenedor.innerHTML = marcado.trim();

        const nueva = contenedor.querySelector('[data-linea]');
        contenedorLineas.appendChild(nueva);

        renumerar();
        nueva.querySelector('[data-campo="producto_id"]').focus();
    }

    /**
     * Renumera los `name` de todas las líneas y ajusta los controles que
     * dependen de cuántas hay. Se llama tras agregar y tras quitar: sin esto,
     * borrar la línea 1 de 3 dejaría los índices 0 y 2, y los mensajes de
     * error del servidor apuntarían a una línea que ya no está en pantalla.
     */
    function renumerar() {
        const lineas = contenedorLineas.querySelectorAll('[data-linea]');

        lineas.forEach((linea, indice) => {
            linea.querySelectorAll('[name]').forEach((campo) => {
                campo.name = campo.name.replace(/lineas\[[^\]]*\]/, `lineas[${indice}]`);
            });

            // Quitar la única línea que existe dejaría el formulario sin nada
            // que enviar.
            const quitar = linea.querySelector('[data-quitar-linea]');

            if (quitar) {
                quitar.hidden = lineas.length < 2;
            }
        });

        if (botonAgregar) {
            botonAgregar.hidden = lineas.length >= maximoLineas;
        }
    }

    /**
     * Muestra u oculta el bloque de medidas según lo que pida el producto
     * elegido. Los campos ocultos se vacían y se marcan `disabled` para que
     * no viajen en el POST: un ancho olvidado de una selección anterior
     * llegaría al servidor y cambiaría el cálculo sin que nadie lo vea.
     */
    function sincronizarMedidas() {
        contenedorLineas.querySelectorAll('[data-linea]').forEach((linea) => {
            const seleccion = linea.querySelector('[data-campo="producto_id"]');
            const bloque = linea.querySelector('[data-medidas]');
            const ayuda = linea.querySelector('[data-ayuda-medidas]');
            const producto = productos[seleccion.value];

            // Sin producto elegido todavía se dejan visibles: es el estado
            // inicial, y esconderlos haría saltar la tarjeta al elegir.
            const requiere = !producto || producto.requiere_medidas;

            bloque.hidden = !requiere;

            if (ayuda) {
                ayuda.hidden = !requiere;
            }

            bloque.querySelectorAll('input').forEach((campo) => {
                campo.disabled = !requiere;

                if (!requiere) {
                    campo.value = '';
                }
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Estimación                                                          */
    /* ------------------------------------------------------------------ */

    function alCambiar() {
        sincronizarMedidas();
        estimarConEspera();
    }

    let temporizador = null;
    let peticionEnCurso = null;

    /**
     * Espera a que el visitante deje de tipear antes de preguntar al
     * servidor. Sin esto, escribir "2.50" en un ancho dispara cuatro
     * peticiones y agota el rate limiter en pocos segundos.
     */
    function estimarConEspera() {
        clearTimeout(temporizador);
        temporizador = setTimeout(estimar, 450);
    }

    async function estimar() {
        const lineas = recolectarLineas();

        if (lineas.length === 0) {
            mostrarVacio();

            return;
        }

        // Cancela la petición anterior: las respuestas pueden llegar
        // desordenadas y pintar un precio viejo sobre uno nuevo.
        if (peticionEnCurso) {
            peticionEnCurso.abort();
        }

        peticionEnCurso = new AbortController();

        try {
            const respuesta = await fetch(formulario.dataset.urlCalcular, {
                method: 'POST',
                signal: peticionEnCurso.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': formulario.querySelector('[name="_token"]').value,
                },
                body: JSON.stringify({ lineas }),
            });

            const datos = await respuesta.json();

            if (!respuesta.ok) {
                mostrarError(primerMensaje(datos, respuesta.status));

                return;
            }

            mostrarEstimacion(datos);
        } catch (error) {
            if (error.name !== 'AbortError') {
                mostrarError('No pudimos calcular el estimado. Revisa tu conexión e inténtalo otra vez.');
            }
        }
    }

    /**
     * Las líneas listas para el servidor: solo las que ya tienen producto
     * elegido, y solo con los campos que ese producto usa.
     */
    function recolectarLineas() {
        return Array.from(contenedorLineas.querySelectorAll('[data-linea]'))
            .map((linea) => {
                const valor = (campo) => linea.querySelector(`[data-campo="${campo}"]`)?.value.trim() ?? '';
                const productoId = valor('producto_id');

                if (!productoId) {
                    return null;
                }

                const cantidad = valor('cantidad');
                const datos = { producto_id: Number(productoId), cantidad: Number(cantidad || 1) };

                const producto = productos[productoId];

                if (producto && producto.requiere_medidas) {
                    const ancho = valor('ancho');
                    const alto = valor('alto');

                    // Sin las dos medidas el servidor devolvería un error de
                    // validación mientras la persona todavía está tipeando.
                    if (!ancho || !alto) {
                        return null;
                    }

                    datos.ancho = Number(ancho);
                    datos.alto = Number(alto);
                }

                return datos;
            })
            .filter(Boolean);
    }

    /* ------------------------------------------------------------------ */
    /* Panel                                                               */
    /* ------------------------------------------------------------------ */

    function mostrarVacio() {
        panel.vacio.hidden = false;
        panel.error.hidden = true;
        panel.resultado.hidden = true;
    }

    function mostrarError(mensaje) {
        panel.vacio.hidden = true;
        panel.resultado.hidden = true;
        panel.error.hidden = false;
        panel.error.textContent = mensaje;
    }

    function mostrarEstimacion(datos) {
        panel.vacio.hidden = true;
        panel.error.hidden = true;
        panel.resultado.hidden = false;

        panel.lineas.replaceChildren(
            ...datos.lineas.map((linea) => {
                const item = document.createElement('li');
                item.className = 'flex justify-between gap-3';

                const descripcion = document.createElement('span');
                // textContent, nunca innerHTML: la descripción sale de datos
                // que pasaron por el servidor, pero pintarla como HTML sería
                // abrir un XSS por comodidad.
                descripcion.textContent = `${linea.descripcion} × ${linea.cantidad}`;

                const monto = document.createElement('span');
                monto.className = 'shrink-0 font-medium text-slate-800';
                monto.textContent = bolivianos(linea.subtotal);

                item.append(descripcion, monto);

                return item;
            }),
        );

        panel.subtotal.textContent = bolivianos(datos.subtotal);
        panel.iva.textContent = bolivianos(datos.iva);
        panel.rango.textContent = `${bolivianos(datos.estimado_min)} – ${bolivianos(datos.estimado_max)}`;
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Catálogo mínimo que el JS necesita (qué producto lleva medidas), leído
     * de un <script type="application/json"> que el navegador no ejecuta.
     */
    function leerProductos() {
        const etiqueta = document.querySelector('[data-cotizador-productos]');

        try {
            return etiqueta ? JSON.parse(etiqueta.textContent) : {};
        } catch {
            return {};
        }
    }

    /**
     * Primer mensaje útil de una respuesta de error: el 422 del motor
     * (`{error: "..."}"`), el primero de la bolsa de validación de Laravel
     * (`{errors: {campo: [...]}}`) o el aviso del rate limiter.
     */
    function primerMensaje(datos, estado) {
        if (estado === 429) {
            return 'Demasiados cálculos seguidos. Espera un momento y vuelve a intentarlo.';
        }

        if (datos?.error) {
            return datos.error;
        }

        const errores = datos?.errors ? Object.values(datos.errors) : [];

        return errores[0]?.[0] ?? 'No pudimos calcular el estimado con esos datos.';
    }
}

/**
 * Formato boliviano: separador de miles con punto y decimales con coma, como
 * el resto de los documentos de la empresa.
 */
function bolivianos(monto) {
    return `Bs ${Number(monto).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
