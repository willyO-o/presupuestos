<?php

namespace App\Services\Reporte;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Rango de fechas de un reporte de inteligencia de negocios (el filtro
 * "desde / hasta" de la pantalla) y los meses que abarca.
 *
 * Los pronósticos trabajan por MES: la serie de cada gráfico es la lista de
 * meses del periodo, con cero (o hueco) donde no hubo movimiento. Sin esto,
 * un mes vacío desaparecería del eje y la recta de tendencia se calcularía
 * sobre meses que no son consecutivos.
 */
final readonly class PeriodoReporte
{
    public function __construct(
        public Carbon $desde,
        public Carbon $hasta,
    ) {}

    /**
     * Arma el periodo desde la query string. Sin fechas, los últimos
     * `$mesesPorDefecto` meses calendario hasta hoy (incluido el actual).
     *
     * Una fecha que no se puede leer se ignora en vez de reventar la pantalla:
     * llega escrita a mano en la URL, no desde el selector.
     */
    public static function desdeFiltros(?string $desde, ?string $hasta, int $mesesPorDefecto = 12): self
    {
        $hastaFecha = self::leer($hasta)?->endOfDay() ?? Carbon::now()->endOfDay();
        $desdeFecha = self::leer($desde)?->startOfDay()
            ?? $hastaFecha->copy()->startOfMonth()->subMonths($mesesPorDefecto - 1);

        if ($desdeFecha->greaterThan($hastaFecha)) {
            [$desdeFecha, $hastaFecha] = [$hastaFecha->copy()->startOfDay(), $desdeFecha->copy()->endOfDay()];
        }

        return new self($desdeFecha, $hastaFecha);
    }

    /**
     * Meses calendario que toca el periodo, en orden: clave `Y-m` → etiqueta
     * corta (`ago 2026`).
     *
     * @return Collection<string, string>
     */
    public function meses(): Collection
    {
        $meses = collect();
        $mes = $this->desde->copy()->startOfMonth();

        while ($mes->lessThanOrEqualTo($this->hasta)) {
            $meses->put($mes->format('Y-m'), $mes->translatedFormat('M Y'));
            $mes->addMonth();
        }

        return $meses;
    }

    /**
     * Etiquetas de los `$cantidad` meses siguientes al final del periodo: el
     * eje de la proyección.
     *
     * @return list<string>
     */
    public function mesesSiguientes(int $cantidad): array
    {
        $ultimo = $this->hasta->copy()->startOfMonth();

        return array_map(
            fn (int $k): string => $ultimo->copy()->addMonths($k)->translatedFormat('M Y'),
            range(1, $cantidad),
        );
    }

    /**
     * @return array{desde: string, hasta: string, meses: int}
     */
    public function toArray(): array
    {
        return [
            'desde' => $this->desde->toDateString(),
            'hasta' => $this->hasta->toDateString(),
            'meses' => $this->meses()->count(),
        ];
    }

    private static function leer(?string $fecha): ?Carbon
    {
        if (blank($fecha)) {
            return null;
        }

        try {
            return Carbon::parse($fecha);
        } catch (Throwable) {
            return null;
        }
    }
}
