<?php

namespace App\Services\Pedido;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoSeguimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que ve un cliente de su pedido en el sitio público (`/seguimiento`).
 *
 * Arma una vista ACOTADA a propósito: etapas, trabajos con sus medidas,
 * entregas y saldo. No sale nada de lo que el taller anota para sí mismo
 * (observaciones de `pedido_seguimiento`, áreas, empleados, consumo y costo
 * real de materiales): esas notas se escriben pensando en un compañero, no en
 * el cliente.
 *
 * La etapa que se muestra es la misma que ve el panel: `pedido.estado`, que
 * `Pedido::recalcularEstado()` deja en la etapa MENOS avanzada de sus ítems.
 * Este servicio no recalcula nada, solo lo traduce para el cliente.
 */
class SeguimientoPublicoService
{
    /**
     * Pasos de la línea de tiempo pública: la confirmación del pedido más el
     * flujo productivo de `Pedido::FLUJO`, en el mismo orden.
     *
     * @var array<string, array{titulo: string, texto: string, icono: string}>
     */
    public const PASOS = [
        'CONFIRMADO' => [
            'titulo' => 'Pedido confirmado',
            'texto' => 'Abrimos tu orden de trabajo con lo aprobado en la cotización.',
            'icono' => 'documento',
        ],
        'DISENO' => [
            'titulo' => 'Diseño',
            'texto' => 'Preparamos los artes y los planos de fabricación.',
            'icono' => 'pincel',
        ],
        'ELABORACION' => [
            'titulo' => 'Elaboración',
            'texto' => 'Tu pedido está en el taller: impresión, corte y armado.',
            'icono' => 'produccion',
        ],
        'ACABADO' => [
            'titulo' => 'Acabado',
            'texto' => 'Terminaciones y ensamblado final de cada pieza.',
            'icono' => 'brillo',
        ],
        'CONTROL_CALIDAD' => [
            'titulo' => 'Control de calidad',
            'texto' => 'Revisamos medidas, colores y terminaciones antes de entregar.',
            'icono' => 'calidad',
        ],
        'ENTREGADO' => [
            'titulo' => 'Entregado',
            'texto' => 'El trabajo llegó a tus manos.',
            'icono' => 'camion',
        ],
    ];

    /**
     * El pedido de ese número, solo si los 4 dígitos coinciden con el final
     * del teléfono o del NIT del cliente. Si no, null — sin distinguir si el
     * pedido no existe o si los dígitos no son: esa diferencia es justo lo que
     * le serviría a quien prueba números ajenos.
     *
     * Se comparan solo los dígitos (`7 123-4567` y `71234567` terminan igual)
     * y con `hash_equals`, que tarda lo mismo acierte o no. Un cliente sin
     * teléfono ni NIT con al menos 4 dígitos no se puede verificar y queda
     * afuera: fallar cerrado, como el resto del sistema.
     */
    public function buscar(string $numero, string $digitos): ?Pedido
    {
        $pedido = Pedido::query()
            ->with('cotizacion.cliente:id,razon_social,telefono,nit')
            ->where('numero_pedido', $numero)
            ->first();

        $cliente = $pedido?->cotizacion?->cliente;

        if ($cliente === null) {
            return null;
        }

        foreach ([$cliente->telefono, $cliente->nit] as $dato) {
            $soloDigitos = preg_replace('/\D+/', '', (string) $dato);

            if (strlen($soloDigitos) >= 4 && hash_equals(substr($soloDigitos, -4), $digitos)) {
                return $pedido;
            }
        }

        return null;
    }

    /**
     * Todo lo que pinta `publico/seguimiento-pedido.blade.php`.
     *
     * @return array{
     *     numero: string,
     *     cliente: string,
     *     estado: string,
     *     cancelado: bool,
     *     entregado: bool,
     *     etapa: array{clave: string, titulo: string, texto: string, icono: string},
     *     pasos: list<array{clave: string, titulo: string, texto: string, icono: string, estado: string, fecha: ?Carbon, avance: ?array{listos: int, total: int}}>,
     *     fechas: array{pedido: Carbon, estimada: ?Carbon, real: ?Carbon, dias_restantes: ?int},
     *     trabajos: list<array{descripcion: string, medidas: ?string, cantidad: string, etapa: string, indice: int}>,
     *     entregas: list<array{numero: string, fecha: Carbon, trabajos: int}>,
     *     cobranza: array{total: float, pagado: float, saldo: float, estado: string}
     * }
     */
    public function resumen(Pedido $pedido): array
    {
        $pedido->loadMissing([
            'cotizacion.cliente:id,razon_social',
            'detalles.seguimientos:id,pedido_detalle_id,etapa,fecha_inicio,fecha_fin',
            'notasEntrega' => fn ($query) => $query->withCount('detalles')->orderBy('fecha_entrega'),
        ]);

        $cancelado = $pedido->estado === 'CANCELADO';
        $entregado = $pedido->estado === 'ENTREGADO';
        $indiceActual = $this->indiceActual($pedido);
        $pasos = $this->pasos($pedido, $indiceActual);

        // La etapa que encabeza la página: la que está en curso, la que quedó
        // detenida al cancelar, o "Entregado" cuando ya terminó todo.
        $etapa = collect($pasos)->firstWhere('estado', $cancelado ? 'detenido' : 'actual')
            ?? collect($pasos)->last();

        $estimada = $pedido->fecha_entrega_estimada;

        return [
            'numero' => $pedido->numero_pedido,
            'cliente' => (string) $pedido->cotizacion?->cliente?->razon_social,
            'estado' => $pedido->estado,
            'cancelado' => $cancelado,
            'entregado' => $entregado,
            'etapa' => collect($etapa)->only(['clave', 'titulo', 'texto', 'icono'])->all(),
            'pasos' => $pasos,
            'fechas' => [
                'pedido' => $pedido->fecha_pedido,
                'estimada' => $estimada,
                'real' => $pedido->fecha_entrega_real,
                'dias_restantes' => ($estimada !== null && ! $entregado && ! $cancelado)
                    ? (int) today()->diffInDays($estimada, false)
                    : null,
            ],
            'trabajos' => $pedido->detalles->map(fn (PedidoDetalle $detalle): array => [
                'descripcion' => $detalle->descripcion,
                'medidas' => ($detalle->ancho !== null && $detalle->alto !== null)
                    ? $this->metros($detalle->ancho).' × '.$this->metros($detalle->alto).' m'
                    : null,
                'cantidad' => $this->cantidad($detalle->cantidad),
                'etapa' => self::PASOS[$detalle->estado_item]['titulo'] ?? $detalle->estado_item,
                'indice' => $this->indiceDe($detalle->estado_item),
            ])->values()->all(),
            'entregas' => $pedido->notasEntrega->map(fn ($nota): array => [
                'numero' => $nota->numero_nota,
                'fecha' => $nota->fecha_entrega,
                'trabajos' => (int) $nota->detalles_count,
            ])->values()->all(),
            'cobranza' => [
                'total' => (float) $pedido->total,
                'pagado' => $pedido->totalPagado(),
                'saldo' => $pedido->saldo(),
                'estado' => $pedido->estadoPago(),
            ],
        ];
    }

    /**
     * Posición en `Pedido::FLUJO` de la etapa en la que está el pedido. Un
     * pedido cancelado ya no tiene etapa propia (`estado` = CANCELADO): se
     * toma la del ítem menos avanzado, que es donde quedó detenido.
     */
    private function indiceActual(Pedido $pedido): int
    {
        if ($pedido->estado !== 'CANCELADO') {
            return $this->indiceDe($pedido->estado);
        }

        $indices = $pedido->detalles->map(fn (PedidoDetalle $detalle): int => $this->indiceDe($detalle->estado_item));

        return $indices->isEmpty() ? 0 : $indices->min();
    }

    private function indiceDe(string $etapa): int
    {
        $indice = array_search($etapa, Pedido::FLUJO, true);

        return $indice === false ? 0 : $indice;
    }

    /**
     * Cada paso con su estado (`completo`, `actual`, `detenido` o
     * `pendiente`) y la fecha que corresponde mostrar: cuándo terminó un paso
     * completo, desde cuándo corre el actual. Las fechas salen de
     * `pedido_seguimiento`; si el taller no registró la etapa, el paso va sin
     * fecha antes que con una inventada.
     *
     * @return list<array{clave: string, titulo: string, texto: string, icono: string, estado: string, fecha: ?Carbon, avance: ?array{listos: int, total: int}}>
     */
    private function pasos(Pedido $pedido, int $indiceActual): array
    {
        $entregado = $pedido->estado === 'ENTREGADO';
        $seguimientos = $pedido->detalles->flatMap(fn (PedidoDetalle $detalle): Collection => $detalle->seguimientos);
        $totalTrabajos = $pedido->detalles->count();

        $pasos = [[
            'clave' => 'CONFIRMADO',
            ...self::PASOS['CONFIRMADO'],
            'estado' => 'completo',
            'fecha' => $pedido->fecha_pedido,
            'avance' => null,
        ]];

        foreach (Pedido::FLUJO as $indice => $clave) {
            $estado = match (true) {
                $entregado || $indice < $indiceActual => 'completo',
                $indice === $indiceActual => $pedido->estado === 'CANCELADO' ? 'detenido' : 'actual',
                default => 'pendiente',
            };

            // `pedido_seguimiento` llama ENTREGA a la etapa que en el ítem es
            // ENTREGADO (ver PedidoSeguimiento::ETAPAS).
            $deEstaEtapa = $seguimientos->where('etapa', $clave === 'ENTREGADO' ? 'ENTREGA' : $clave);

            $fecha = match ($estado) {
                'completo' => $clave === 'ENTREGADO'
                    ? $pedido->fecha_entrega_real
                    : $deEstaEtapa->max(fn (PedidoSeguimiento $s) => $s->fecha_fin),
                'actual' => $deEstaEtapa->min(fn (PedidoSeguimiento $s) => $s->fecha_inicio),
                default => null,
            };

            // Con varios trabajos, el pedido avanza al ritmo del más lento:
            // contar cuántos ya pasaron la etapa explica por qué sigue ahí.
            $listos = $pedido->detalles->filter(fn (PedidoDetalle $d): bool => $this->indiceDe($d->estado_item) > $indice)->count();

            $pasos[] = [
                'clave' => $clave,
                ...self::PASOS[$clave],
                'estado' => $estado,
                'fecha' => $fecha,
                'avance' => ($estado === 'actual' && $totalTrabajos > 1 && $listos > 0)
                    ? ['listos' => $listos, 'total' => $totalTrabajos]
                    : null,
            ];
        }

        return $pasos;
    }

    private function metros(float|string $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    /**
     * `cantidad` es decimal(…,2): 3.00 se lee "3", 2.50 se lee "2,5".
     */
    private function cantidad(float|string $valor): string
    {
        $numero = (float) $valor;

        return floor($numero) === $numero
            ? number_format($numero, 0, ',', '.')
            : rtrim(rtrim(number_format($numero, 2, ',', '.'), '0'), ',');
    }
}
