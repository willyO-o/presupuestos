<?php

namespace App\Services\Reporte;

/**
 * El informe de proyecciones como CSV, para reprocesarlo en una planilla.
 *
 * Tres bloques separados por una fila vacía: (1) la serie de cada indicador,
 * mes a mes, real y proyectada, en formato largo (una fila por mes e
 * indicador, se filtra o se arma una tabla dinámica sin rehacer nada); (2)
 * la ficha del método de cada uno; (3) las alertas.
 *
 * Formato pensado para Excel en español: separador `;`, coma decimal, sin
 * separador de miles y BOM UTF-8 (sin BOM, Excel abre los acentos como
 * "Ã¡").
 */
final class InformeProyeccionesCsv
{
    /**
     * @param  array<string, mixed>  $informe  InformeProyeccionesService::armar().
     */
    public static function generar(array $informe): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");

        $fila = fn (array $celdas) => fputcsv($salida, $celdas, ';', '"', '');
        $periodo = $informe['periodo'];

        $fila(['Informe de proyecciones', "{$periodo['etiqueta_desde']} - {$periodo['etiqueta_hasta']}", $informe['alcance'], "Intervalo al {$informe['confianza']} %"]);
        $fila([]);

        $fila(['Sección', 'Indicador', 'Formato', 'Mes', 'Tipo', 'Valor', 'Media móvil 3m', 'Mínimo probable', 'Máximo probable']);

        foreach ($informe['secciones'] as $seccion) {
            foreach ($seccion['indicadores'] ?? [] as $indicador) {
                foreach (self::filasSerie($seccion['titulo'], $indicador) as $celdas) {
                    $fila($celdas);
                }
            }
        }

        $fila([]);
        $fila(['Indicador', 'Meses usados', 'Desde', 'Hasta', 'Mes excluido (incompleto)', 'Pendiente por mes', 'R²', 'Ajuste', 'Error típico', 't de Student', 'Error validación recta %', 'Error Holt %', 'Error media móvil %', 'Mejor modelo']);

        foreach ($informe['secciones'] as $seccion) {
            foreach ($seccion['indicadores'] ?? [] as $indicador) {
                $metodo = $indicador['metodo'];
                $validacion = $metodo['validacion'] ?? null;

                $fila([
                    $indicador['titulo'],
                    $metodo['n'],
                    $metodo['desde'] ?? '',
                    $metodo['hasta'] ?? '',
                    $metodo['mes_final_excluido'] ?? '',
                    self::numero($metodo['pendiente'] ?? null),
                    self::numero($metodo['r2'] ?? null),
                    $metodo['ajuste'] ?? '',
                    self::numero($metodo['error_tipico'] ?? null),
                    self::numero($metodo['t'] ?? null),
                    self::numero($validacion['recta'] ?? null),
                    self::numero($validacion['holt'] ?? null),
                    self::numero($validacion['media_movil'] ?? null),
                    $validacion['mejor'] ?? '',
                ]);
            }
        }

        $alertas = collect($informe['secciones'])->firstWhere('clave', 'alertas');
        if ($alertas) {
            $fila([]);
            $fila(['Prioridad', 'Alerta', 'Detalle']);

            foreach ($alertas['alertas'] as $alerta) {
                $fila([$alerta['severidad'], $alerta['titulo'], $alerta['detalle']]);
            }
        }

        rewind($salida);
        $contenido = stream_get_contents($salida);
        fclose($salida);

        return $contenido;
    }

    /**
     * @param  array<string, mixed>  $indicador
     * @return list<list<string|int|float>>
     */
    private static function filasSerie(string $seccion, array $indicador): array
    {
        $metodo = $indicador['metodo'];
        $historico = array_values($indicador['historico']);
        $mediaMovil = $indicador['media_movil'] === null ? [] : array_values($indicador['media_movil']);
        $ultimo = count($historico) - 1;
        $base = [$seccion, $indicador['titulo'], $indicador['formato']];
        $filas = [];

        foreach ($historico as $i => $valor) {
            $incompleto = $i === $ultimo && ! empty($metodo['mes_final_excluido']);

            $filas[] = [...$base, $indicador['labels'][$i], $incompleto ? 'Real (mes incompleto)' : 'Real', self::numero($valor), self::numero($mediaMovil[$i] ?? null), '', ''];
        }

        if (! empty($metodo['cierre_estimado']) && ! empty($metodo['mes_final_excluido'])) {
            $cierre = $metodo['cierre_estimado'][0];
            $filas[] = [...$base, $metodo['mes_final_excluido'], 'Cierre estimado', self::numero($cierre['valor']), '', self::numero($cierre['inferior']), self::numero($cierre['superior'])];
        }

        foreach ($indicador['proyeccion'] as $i => $punto) {
            $filas[] = [...$base, $indicador['meses_proyeccion'][$i], 'Proyección', self::numero($punto['valor']), '', self::numero($punto['inferior']), self::numero($punto['superior'])];
        }

        return $filas;
    }

    /** Coma decimal y sin separador de miles: lo que Excel en español lee como número. */
    private static function numero(float|int|null $valor): string
    {
        return $valor === null ? '' : rtrim(rtrim(number_format((float) $valor, 4, ',', ''), '0'), ',');
    }
}
