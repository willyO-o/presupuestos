{{--
    Página de error del sitio público.

    La usan todas las vistas de `resources/views/errors/`. Antes de que
    existiera, un `abort(404)` —por ejemplo `/proyectos?categoria=inventada` o
    un código de cotización que no existe— devolvía la pantalla gris por
    defecto de Laravel: sin logo, sin menú y sin ninguna forma de volver al
    sitio. Un visitante que llega ahí desde Google simplemente se va.

    Todos los errores llevan `noindex`: una página de error indexada es una
    página muerta compitiendo en los resultados con las que sí importan.

    Props:
    - codigo   Código HTTP. Decide el texto por defecto y el color.
    - titulo   Sobreescribe el título propuesto para ese código.
    - mensaje  Sobreescribe la explicación propuesta.
--}}
@props([
    'codigo' => 500,
    'titulo' => null,
    'mensaje' => null,
])

@php
    $codigo = (int) $codigo;

    /*
     * Un texto por código, escrito para una persona y no para un
     * desarrollador: nada de "Unauthenticated" ni "Page Expired". El del 500
     * es deliberadamente vago — el detalle del fallo va al log, no a la
     * pantalla de un visitante.
     */
    $textos = match ($codigo) {
        403 => [
            'titulo' => 'Esta página es privada',
            'mensaje' => 'No tienes acceso a esta parte del sistema. Si crees que deberías tenerlo, escríbenos o inicia sesión con otra cuenta.',
        ],
        404 => [
            'titulo' => 'No encontramos esta página',
            'mensaje' => 'El enlace puede estar mal escrito o el contenido se movió. Prueba desde la portada o mira nuestros proyectos.',
        ],
        419 => [
            'titulo' => 'La página caducó',
            'mensaje' => 'Pasó demasiado tiempo con el formulario abierto y, por seguridad, tuvimos que descartarlo. Vuelve a abrirlo y envíalo de nuevo.',
        ],
        429 => [
            'titulo' => 'Demasiados intentos seguidos',
            'mensaje' => 'Recibimos muchas peticiones desde tu conexión en poco tiempo. Espera un minuto y vuelve a intentarlo.',
        ],
        503 => [
            'titulo' => 'Estamos en mantenimiento',
            'mensaje' => 'Volvemos en unos minutos. Si es urgente, escríbenos por WhatsApp y te atendemos igual.',
        ],
        default => [
            'titulo' => 'Algo salió mal de nuestro lado',
            'mensaje' => 'Ya quedó registrado y lo estamos revisando. Vuelve a intentarlo en un momento o escríbenos y lo resolvemos contigo.',
        ],
    };

    $titulo ??= $textos['titulo'];
    $mensaje ??= $textos['mensaje'];

    // Ámbar para lo que el visitante puede resolver reintentando; rojo para
    // lo que falló de nuestro lado.
    $esNuestro = $codigo >= 500;
@endphp

<x-publico.layout
    :titulo="$titulo"
    :descripcion="$mensaje"
    :indexable="false"
>
    <section class="flex min-h-[60vh] items-center justify-center bg-slate-50 px-4 py-16">
        <div class="mx-auto w-full max-w-lg text-center">
            <span @class([
                'mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full',
                'bg-red-50 text-red-500' => $esNuestro,
                'bg-amber-50 text-marca-ambar' => ! $esNuestro,
            ])>
                <x-publico.icono nombre="alerta" class="h-8 w-8" />
            </span>

            <p class="mb-2 text-5xl font-extrabold tracking-tight text-slate-300 sm:text-6xl">
                {{ $codigo }}
            </p>

            <h1 class="mb-3 text-2xl font-extrabold tracking-tight text-slate-800 sm:text-3xl">
                {{ $titulo }}
            </h1>

            <p class="mb-8 text-sm leading-relaxed text-slate-500">
                {{ $mensaje }}
            </p>

            <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a
                    href="{{ route('inicio') }}"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-marca-azul px-6 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-marca-azul-oscuro sm:w-auto"
                >
                    Ir a la portada
                </a>

                <a
                    href="{{ route('proyectos') }}"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 transition-colors hover:border-marca-azul hover:text-marca-azul sm:w-auto"
                >
                    Ver proyectos
                    <x-publico.icono nombre="flecha" class="h-4 w-4" />
                </a>
            </div>

            <p class="mt-8 text-xs text-slate-400">
                ¿Buscabas algo puntual?
                <a href="{{ route('cotizador') }}" class="font-semibold text-marca-azul hover:underline">Cotiza en línea</a>
                o escríbenos por WhatsApp con el botón de abajo.
            </p>
        </div>
    </section>
</x-publico.layout>
