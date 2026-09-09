{{-- Pie de página con los datos de contacto oficiales (los mismos que alimentan el JSON-LD). --}}
@php
    $empresa = config('sitio.empresa');
@endphp

<footer class="bg-marca-azul text-white">
    <div class="mx-auto max-w-contenido px-4 py-10 sm:px-6">
        <div class="grid gap-8 md:grid-cols-4">
            <div class="md:col-span-1">
                <img
                    src="{{ asset('img/logo/logo-blanco.png') }}"
                    alt="{{ $empresa['nombre'] }}"
                    width="150"
                    height="50"
                    loading="lazy"
                    class="h-10 w-auto"
                >
                <p class="mt-3 text-xs leading-relaxed text-white/80">
                    {{ $empresa['lema'] }}
                </p>
            </div>

            <address class="grid gap-5 not-italic md:col-span-3 md:grid-cols-3">
                <div class="flex items-start gap-2.5 text-xs">
                    <x-publico.icono nombre="ubicacion" class="mt-0.5 h-5 w-5 shrink-0 text-white/90" />
                    <div>
                        <span class="mb-0.5 block font-bold">Centro de operaciones</span>
                        <span class="text-white/85">{{ $empresa['direccion'] }}<br>{{ $empresa['ciudad'] }}, {{ $empresa['departamento'] }}</span>
                    </div>
                </div>

                <div class="flex items-start gap-2.5 text-xs">
                    <x-publico.icono nombre="telefono" class="mt-0.5 h-5 w-5 shrink-0 text-white/90" />
                    <div>
                        <span class="mb-0.5 block font-bold">Teléfono</span>
                        <a href="tel:{{ $empresa['telefono'] }}" class="text-white/85 hover:underline">{{ $empresa['telefono_visible'] }}</a>
                    </div>
                </div>

                <div class="flex items-start gap-2.5 text-xs">
                    <x-publico.icono nombre="correo" class="mt-0.5 h-5 w-5 shrink-0 text-white/90" />
                    <div>
                        <span class="mb-0.5 block font-bold">Correo</span>
                        <a href="mailto:{{ $empresa['email'] }}" class="break-all text-white/85 hover:underline">{{ $empresa['email'] }}</a>
                    </div>
                </div>
            </address>
        </div>
    </div>

    <div class="border-t border-white/15">
        <div class="mx-auto flex max-w-contenido flex-col items-center justify-between gap-2 px-4 py-4 text-xs text-white/80 sm:flex-row sm:px-6">
            <p>&copy; {{ date('Y') }} {{ $empresa['nombre'] }}. Todos los derechos reservados.</p>
            <nav class="flex items-center gap-4" aria-label="Enlaces del pie">
                <a href="{{ $empresa['web'] }}" rel="noopener" class="hover:underline">{{ str_replace('https://', '', $empresa['web']) }}</a>
                <a href="{{ route('proyectos') }}" class="hover:underline">Proyectos</a>
                <a href="{{ route('login') }}" class="hover:underline">Acceso al sistema</a>
            </nav>
        </div>
    </div>
</footer>
