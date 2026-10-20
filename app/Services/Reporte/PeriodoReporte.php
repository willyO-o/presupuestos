<?php

namespace App\Services\Reporte;

use Carbon\CarbonInterface;
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
     *
     * Ninguna fecha pasa de hoy: los meses futuros no tienen movimiento y
     * entrarían a la serie como ceros, tirando la recta hacia abajo.
     */
    public static function desdeFiltros(?string $desde, ?string $hasta, int $mesesPorDefecto = 12): self
    {
        $hoy = Carbon::now()->endOfDay();

        $hastaFecha = self::leer($hasta)?->endOfDay()->min($hoy) ?? $hoy->copy();
        $desdeFecha = self::leer($desde)?->startOfDay()->min($hoy->copy()->startOfDay())
            ?? $hastaFecha->copy()->startOfMonth()->subMonths($mesesPorDefecto - 1);

        if ($desdeFecha->greaterThan($hastaFecha)) {
            [$desdeFecha, $hastaFecha] = [$hastaFecha->copy()->startOfDay(), $desdeFecha->copy()->endOfDay()];
        }

        return new self($desdeFecha, $hastaFecha);
    }

    /**
     * Nombre corto de cada mes, en orden. Fijo y no `translatedFormat('M')`:
     * Carbon devuelve "ene." con punto y "sept." para septiembre, y la
     * etiqueta tiene que medir siempre lo mismo en ejes, tablas y PDFs.
     */
    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * Etiqueta mes-año de un mes: `Ene-2026`.
     *
     * Es el ÚNICO formato de mes de los reportes (pedido del usuario): en
     * gráficos, tablas, PDFs y respuestas del asistente. Su espejo en el
     * navegador es `mesAnio()` de `resources/js/Utils/FormatoReporte.js`.
     */
    public static function etiquetaMes(CarbonInterface $mes): string
    {
        return self::nombreMes($mes->month).'-'.$mes->year;
    }

    /**
     * Nombre corto de un mes sin año (`Ene`), para los ejes que juntan varios
     * años en el mismo mes calendario (estacionalidad).
     */
    public static function nombreMes(int $mes): string
    {
        return self::MESES[$mes - 1];
    }

    /**
     * Meses calendario que toca el periodo, en orden: clave `Y-m` → etiqueta
     * mes-año (`Ago-2026`).
     *
     * @return Collection<string, string>
     */
    public function meses(): Collection
    {
        $meses = collect();
        $mes = $this->desde->copy()->startOfMonth();

        while ($mes->lessThanOrEqualTo($this->hasta)) {
            $meses->put($mes->format('Y-m'), self::etiquetaMes($mes));
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
            fn (int $k): string => self::etiquetaMes($ultimo->copy()->addMonths($k)),
            range(1, $cantidad),
        );
    }

    /**
     * El periodo arranca a mitad de su primer mes (filtro elegido a mano).
     */
    public function mesInicialIncompleto(): bool
    {
        return $this->desde->day !== 1;
    }

    /**
     * El periodo corta antes de que termine su último mes: lo normal cuando
     * llega hasta hoy. Ese mes tiene menos días de movimiento que los demás.
     */
    public function mesFinalIncompleto(): bool
    {
        return ! $this->hasta->isSameDay($this->hasta->copy()->endOfMonth());
    }

    /**
     * Pronóstico de una serie mensual de ESTE periodo (un valor por mes de
     * `meses()`, `null` donde no hay dato): el puente entre el calendario y
     * la matemática pura de `Pronostico`.
     *
     * Con `$sumaDelMes` (totales: pedidos, montos, consumo) los meses
     * incompletos de los bordes NO entran a la recta: el mes en curso, a 13
     * días de 31, se leería como una caída que no existe. Se siguen mostrando
     * en el gráfico, y la proyección sigue cayendo en los mismos meses del
     * eje. Los promedios, porcentajes y precios no dependen de cuántos días
     * pasaron, así que esos usan el mes entero (`$sumaDelMes = false`).
     *
     * @param  list<float|int|null>  $serie
     * @return array{proyeccion: list<array{valor: float, inferior: float, superior: float}>, variacion_mensual: float|null, metodo: array<string, mixed>}
     */
    public function pronostico(array $serie, bool $piso = true, bool $sumaDelMes = true): array
    {
        $serie = array_values($serie);
        $etiquetas = $this->meses()->values()->all();
        $total = count($serie);

        $inicio = $sumaDelMes && $this->mesInicialIncompleto() && $total > 1 ? 1 : 0;
        $fin = $sumaDelMes && $this->mesFinalIncompleto() && $total > $inicio ? 1 : 0;

        $usados = array_slice($serie, $inicio, $total - $inicio - $fin);
        $valores = array_values(array_filter($usados, fn ($v) => $v !== null));

        $analisis = Pronostico::analizar($valores, config('bi.meses_proyeccion'), $piso, saltar: $fin);

        $analisis['metodo'] += [
            'desde' => $etiquetas[$inicio] ?? null,
            'hasta' => $etiquetas[$total - 1 - $fin] ?? null,
            'mes_inicial_excluido' => $inicio ? $etiquetas[0] ?? null : null,
            'mes_final_excluido' => $fin ? $etiquetas[$total - 1] ?? null : null,
            'meses_sin_dato' => count($usados) - count($valores),
        ];

        $analisis['metodo']['explicacion'] = RedaccionPronostico::ficha(
            $analisis['metodo'],
            $fin ? $this->toArray()['mes_incompleto'] : null,
        );

        return $analisis;
    }

    /**
     * @return array{desde: string, hasta: string, meses: int, mes_incompleto: array{mes: string, dias: int, de: int}|null}
     */
    public function toArray(): array
    {
        return [
            'desde' => $this->desde->toDateString(),
            'hasta' => $this->hasta->toDateString(),
            'meses' => $this->meses()->count(),
            'mes_incompleto' => $this->mesFinalIncompleto() ? [
                'mes' => $this->hasta->translatedFormat('F Y'),
                'dias' => $this->hasta->day,
                'de' => $this->hasta->daysInMonth,
            ] : null,
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
