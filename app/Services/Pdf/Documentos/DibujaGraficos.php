<?php

namespace App\Services\Pdf\Documentos;

/**
 * Gráfico de líneas dibujado con FPDF, para los documentos que muestran
 * series en el tiempo (el informe de proyecciones).
 *
 * **Por qué dibujarlo y no capturar el de la pantalla**: el gráfico de
 * ECharts es un canvas en el navegador; llevarlo al PDF obligaría a tener la
 * pestaña abierta, subir imágenes en base64 y depender del tema claro u
 * oscuro con que se miró. Dibujado acá sale del MISMO arreglo de números que
 * las tablas del documento, igual en cualquier servidor, y los tests lo
 * pueden leer.
 *
 * Solo líneas y bandas, a propósito: es lo que necesita un pronóstico
 * (histórico, media móvil, proyección punteada y su rango probable), y la
 * paleta repite la de la pantalla —azul para lo real, gris para la media
 * móvil, azul oscuro punteado para lo proyectado—.
 *
 * FPDF no trae polígonos rellenos ni líneas punteadas: se escriben los
 * operadores PDF a mano con `_out()` (`m`/`l`/`f` y `d`).
 *
 * @mixin Documento
 */
trait DibujaGraficos
{
    /**
     * Dibuja un gráfico de líneas a todo el ancho útil, con su leyenda debajo.
     *
     * @param  array{
     *     labels: list<string>,
     *     series: list<array{nombre: string, tipo?: string, datos?: list<float|int|null>, inferior?: list<float|int|null>, superior?: list<float|int|null>, color: array<int, int>, punteada?: bool, grosor?: float}>,
     *     inicio_proyeccion?: int|null,
     *     umbrales?: list<array{valor: float|int, nombre: string}>,
     *     formato: callable(float): string,
     *     desde_cero?: bool,
     * }  $grafico
     */
    protected function graficoLineas(array $grafico, float $alto = 60.0): void
    {
        $this->asegurarEspacio($alto + 14);

        $anchoEje = 17.0;
        $x0 = self::MARGEN_LATERAL + $anchoEje;
        $y0 = $this->GetY() + 2;
        $ancho = $this->anchoUtil() - $anchoEje - 1;
        $altoArea = $alto - 8;
        $labels = $grafico['labels'];
        $cantidad = max(count($labels), 1);
        $paso = $ancho / $cantidad;

        $valores = $this->valoresDelGrafico($grafico);

        if ($valores === []) {
            $this->SetXY(self::MARGEN_LATERAL, $y0 + $altoArea / 2);
            $this->fuente(8, '', self::TENUE);
            $this->Cell($this->anchoUtil(), 5, $this->t('Sin datos en el periodo.'), 0, 2, 'C');
            $this->SetXY(self::MARGEN_LATERAL, $y0 + $alto);

            return;
        }

        [$minimo, $maximo, $intervalo] = $this->escala($valores, $grafico['desde_cero'] ?? true);
        $y = fn (float $v): float => $y0 + $altoArea - ($v - $minimo) / ($maximo - $minimo) * $altoArea;
        $x = fn (int $i): float => $x0 + ($i + 0.5) * $paso;

        // Zona proyectada: lo único del gráfico que todavía no pasó.
        if (($inicio = $grafico['inicio_proyeccion'] ?? null) !== null && $inicio < $cantidad) {
            $this->setRelleno(self::FONDO);
            $this->Rect($x0 + $inicio * $paso, $y0, $ancho - $inicio * $paso, $altoArea, 'F');
            $this->SetXY($x0 + $inicio * $paso, $y0 + 0.5);
            $this->fuente(6, 'B', self::TENUE);
            $this->Cell($ancho - $inicio * $paso, 3, $this->t('PROYECCIÓN'), 0, 0, 'C');
        }

        // Rejilla y eje Y.
        $this->SetLineWidth(0.15);
        for ($v = $minimo; $v <= $maximo + $intervalo / 1000; $v += $intervalo) {
            $this->setTrazo(self::BORDE);
            $this->Line($x0, $y($v), $x0 + $ancho, $y($v));
            $this->SetXY(self::MARGEN_LATERAL, $y($v) - 1.5);
            $this->fuente(6, '', self::SUAVE);
            $this->Cell($anchoEje - 1.5, 3, $this->t(($grafico['formato'])($v)), 0, 0, 'R');
        }

        // Umbrales con significado (semáforo, base 100).
        foreach ($grafico['umbrales'] ?? [] as $umbral) {
            if ($umbral['valor'] < $minimo || $umbral['valor'] > $maximo) {
                continue;
            }

            $this->setTrazo(self::AMBAR);
            $this->SetLineWidth(0.3);
            $this->punteado(true);
            $this->Line($x0, $y($umbral['valor']), $x0 + $ancho, $y($umbral['valor']));
            $this->punteado(false);
            // Rótulo a la derecha: a la izquierda lo taparía el arranque de
            // la serie, que suele empezar justo en la base.
            $this->SetXY($x0 + $ancho - 41, $y($umbral['valor']) - 3.2);
            $this->fuente(5.5, '', self::SUAVE);
            $this->Cell(40, 3, $this->t($umbral['nombre']), 0, 0, 'R');
        }

        // Bandas primero, para que las líneas queden encima.
        foreach ($grafico['series'] as $serie) {
            if (($serie['tipo'] ?? 'linea') === 'banda') {
                $this->banda($serie, $x, $y);
            }
        }

        foreach ($grafico['series'] as $serie) {
            if (($serie['tipo'] ?? 'linea') !== 'banda') {
                $this->linea($serie, $x, $y);
            }
        }

        $this->SetLineWidth(0.2);
        $this->setTrazo(self::TENUE);
        $this->Line($x0, $y0 + $altoArea, $x0 + $ancho, $y0 + $altoArea);

        $this->etiquetasEjeX($labels, $x, $paso, $y0 + $altoArea + 1);
        $this->leyenda($grafico['series'], $y0 + $alto - 2);
    }

    /**
     * Color de una serie aclarado hacia el blanco (FPDF no tiene
     * transparencia): el relleno de la banda.
     *
     * @param  array<int, int>  $rgb
     * @return array<int, int>
     */
    protected function aclarar(array $rgb, float $fraccion): array
    {
        return array_map(fn (int $c): int => (int) round($c + (255 - $c) * $fraccion), $rgb);
    }

    /**
     * @param  array<string, mixed>  $grafico
     * @return list<float>
     */
    private function valoresDelGrafico(array $grafico): array
    {
        $valores = [];

        foreach ($grafico['series'] as $serie) {
            foreach (['datos', 'inferior', 'superior'] as $clave) {
                foreach ($serie[$clave] ?? [] as $v) {
                    if ($v !== null) {
                        $valores[] = (float) $v;
                    }
                }
            }
        }

        return $valores;
    }

    /**
     * Mínimo, máximo e intervalo "redondos" del eje Y (1, 2, 2,5 o 5 × 10ⁿ),
     * con cuatro o cinco renglones de rejilla.
     *
     * @param  list<float>  $valores
     * @return array{0: float, 1: float, 2: float}
     */
    private function escala(array $valores, bool $desdeCero): array
    {
        $minimo = min($valores);
        $maximo = max($valores);

        // Los totales (montos, conteos) se leen mejor desde cero; un índice o
        // un precio que se mueve entre 98 y 112, no.
        if ($desdeCero && $minimo >= 0) {
            $minimo = 0.0;
        }

        if ($maximo - $minimo < 1e-9) {
            $relleno = abs($maximo) > 0 ? abs($maximo) * 0.1 : 1.0;
            $minimo -= $relleno;
            $maximo += $relleno;
        }

        $crudo = ($maximo - $minimo) / 4;
        $potencia = 10 ** floor(log10($crudo));
        $intervalo = $potencia * collect([1, 2, 2.5, 5, 10])->first(fn ($f) => $f * $potencia >= $crudo);

        return [floor($minimo / $intervalo) * $intervalo, ceil($maximo / $intervalo) * $intervalo, $intervalo];
    }

    /**
     * @param  array<string, mixed>  $serie
     */
    private function banda(array $serie, callable $x, callable $y): void
    {
        $tramo = [];

        $cerrar = function () use (&$tramo): void {
            if (count($tramo) >= 2) {
                $borde = [
                    ...array_map(fn ($p) => [$p[0], $p[2]], $tramo),
                    ...array_reverse(array_map(fn ($p) => [$p[0], $p[1]], $tramo)),
                ];
                $this->poligono($borde);
            }
            $tramo = [];
        };

        $this->setRelleno($this->aclarar($serie['color'], 0.78));

        foreach ($serie['inferior'] as $i => $inferior) {
            $superior = $serie['superior'][$i] ?? null;

            if ($inferior === null || $superior === null) {
                $cerrar();

                continue;
            }

            $tramo[] = [$x($i), $y((float) $inferior), $y((float) $superior)];
        }

        $cerrar();
    }

    /**
     * @param  array<string, mixed>  $serie
     */
    private function linea(array $serie, callable $x, callable $y): void
    {
        $this->setTrazo($serie['color']);
        $this->setRelleno($serie['color']);
        $this->SetLineWidth($serie['grosor'] ?? 0.45);
        $this->punteado((bool) ($serie['punteada'] ?? false));

        $datos = array_values($serie['datos']);

        foreach ($datos as $i => $valor) {
            if ($valor === null) {
                continue;
            }

            $siguiente = $datos[$i + 1] ?? null;
            $anterior = $datos[$i - 1] ?? null;

            if ($siguiente !== null) {
                $this->Line($x($i), $y((float) $valor), $x($i + 1), $y((float) $siguiente));
            } elseif ($anterior === null) {
                // Punto aislado: sin una marca no se vería.
                $this->Rect($x($i) - 0.6, $y((float) $valor) - 0.6, 1.2, 1.2, 'F');
            }
        }

        $this->punteado(false);
    }

    /**
     * Etiquetas del eje X, salteando las necesarias para que no se pisen.
     *
     * @param  list<string>  $labels
     */
    private function etiquetasEjeX(array $labels, callable $x, float $paso, float $yBase): void
    {
        $this->fuente(5.8, '', self::SUAVE);

        $mas = 0.0;
        foreach ($labels as $label) {
            $mas = max($mas, $this->GetStringWidth($this->t($label)));
        }

        $cada = max(1, (int) ceil(($mas + 1.5) / $paso));

        foreach ($labels as $i => $label) {
            if ($i % $cada !== 0) {
                continue;
            }

            $this->SetXY($x($i) - $paso * $cada / 2, $yBase);
            $this->Cell($paso * $cada, 3, $this->t($label), 0, 0, 'C');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $series
     */
    private function leyenda(array $series, float $y): void
    {
        $x = self::MARGEN_LATERAL + 17.0;
        $this->fuente(6.5, '', self::TEXTO);

        foreach ($series as $serie) {
            $texto = $this->t($serie['nombre']);
            $ancho = 7 + $this->GetStringWidth($texto) + 5;

            if (($serie['tipo'] ?? 'linea') === 'banda') {
                $this->setRelleno($this->aclarar($serie['color'], 0.78));
                $this->Rect($x, $y + 0.4, 5, 2.4, 'F');
            } else {
                $this->setTrazo($serie['color']);
                $this->SetLineWidth($serie['grosor'] ?? 0.45);
                $this->punteado((bool) ($serie['punteada'] ?? false));
                $this->Line($x, $y + 1.6, $x + 5, $y + 1.6);
                $this->punteado(false);
            }

            $this->SetXY($x + 6, $y);
            $this->Cell($ancho - 7, 3.2, $texto, 0, 0, 'L');
            $x += $ancho;
        }

        $this->SetLineWidth(0.2);
        $this->SetXY(self::MARGEN_LATERAL, $y + 5);
    }

    /**
     * Polígono relleno con el color de relleno activo.
     *
     * @param  list<array{0: float, 1: float}>  $puntos  En mm, coordenadas de la página.
     */
    private function poligono(array $puntos): void
    {
        $operadores = [];

        foreach ($puntos as $i => [$px, $py]) {
            $operadores[] = sprintf('%.2F %.2F %s', $px * $this->k, ($this->h - $py) * $this->k, $i === 0 ? 'm' : 'l');
        }

        $this->_out(implode(' ', $operadores).' h f');
    }

    /** Activa o apaga el trazo punteado para las líneas que siguen. */
    private function punteado(bool $activo): void
    {
        $this->_out($activo ? sprintf('[%.2F %.2F] 0 d', 1.6 * $this->k, 1.0 * $this->k) : '[] 0 d');
    }
}
