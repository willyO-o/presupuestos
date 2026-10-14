<?php

namespace App\Services\Reporte;

/**
 * Texto automático de los pronósticos: la ficha "Cómo se calcula" de cada
 * gráfico y la lectura en prosa del informe de proyecciones.
 *
 * Plantillas, no IA: cada frase sale de los números que calculó
 * `Pronostico`, así que no puede afirmar nada que no esté en ellos. Vive en
 * un solo lugar para que la pantalla y el PDF expliquen el método con las
 * mismas palabras.
 */
final class RedaccionPronostico
{
    /** Nombre legible de cada modelo de la validación. */
    public const MODELOS = [
        'recta' => 'la recta (regresión lineal)',
        'holt' => 'Holt (suavizado exponencial doble)',
        'media_movil' => 'la media móvil de 3 meses',
    ];

    /**
     * Ficha del método, renglón por renglón: qué técnica, con cuántos meses,
     * qué tan bien ajusta, qué significa la banda y cuánto se equivocó al
     * validarla.
     *
     * @param  array<string, mixed>  $metodo  `metodo` de Pronostico::analizar().
     * @param  array{mes: string, dias: int, de: int}|null  $mesIncompleto
     * @return list<array{rotulo: string, texto: string}>
     */
    public static function ficha(array $metodo, ?array $mesIncompleto = null): array
    {
        $n = (int) ($metodo['n'] ?? 0);
        $tramo = isset($metodo['desde'], $metodo['hasta'])
            ? sprintf(' (%s – %s)', $metodo['desde'], $metodo['hasta'])
            : '';

        $historia = sprintf('%d %s con datos%s.', $n, $n === 1 ? 'mes' : 'meses', $tramo);

        if (! empty($metodo['mes_final_excluido'])) {
            $historia .= sprintf(
                ' %s no entra en la recta porque está incompleto%s: se muestra en el gráfico y la línea punteada estima cómo cerraría.',
                self::mayuscula($metodo['mes_final_excluido']),
                $mesIncompleto ? sprintf(' (%d de %d días)', $mesIncompleto['dias'], $mesIncompleto['de']) : '',
            );
        }

        if (! empty($metodo['mes_inicial_excluido'])) {
            $historia .= sprintf(' %s tampoco: el periodo empieza a mitad de ese mes.', self::mayuscula($metodo['mes_inicial_excluido']));
        }

        if (! ($metodo['suficiente'] ?? false)) {
            return [
                ['rotulo' => 'Historia usada', 'texto' => $historia],
                ['rotulo' => 'Proyección', 'texto' => sprintf(
                    'Se necesitan al menos %d meses con datos para trazar una tendencia. No se proyecta para no inventar una.',
                    $metodo['minimo'] ?? Pronostico::MINIMO_PUNTOS,
                )],
            ];
        }

        $renglones = [
            ['rotulo' => 'Método', 'texto' => 'Regresión lineal por mínimos cuadrados: una recta y = a + b·x con un punto por mes, prolongada '
                .config('bi.meses_proyeccion').' meses hacia adelante.'],
            ['rotulo' => 'Historia usada', 'texto' => $historia],
            ['rotulo' => 'Ajuste', 'texto' => self::textoAjuste($metodo)],
            ['rotulo' => 'Rango probable', 'texto' => sprintf(
                'Intervalo de predicción al %d %%: t de Student con %d grados de libertad (t = %s) × error típico (%s), ensanchado según la distancia al centro de los datos. Se abre cuanto más lejos se proyecta.',
                $metodo['confianza'],
                $metodo['grados_libertad'],
                self::numero($metodo['t'], 3),
                self::numero($metodo['error_tipico'], 2),
            )],
            ['rotulo' => 'Validación', 'texto' => self::textoValidacion($metodo['validacion'] ?? null)],
        ];

        if (! ($metodo['piso'] ?? true)) {
            $renglones[] = ['rotulo' => 'Sin piso', 'texto' => 'Esta serie puede proyectarse negativa (un margen o una utilidad pueden serlo): no se corta en cero.'];
        }

        return $renglones;
    }

    /**
     * El R² en una frase entendible.
     *
     * @param  array<string, mixed>  $metodo
     */
    public static function textoAjuste(array $metodo): string
    {
        if (($metodo['r2'] ?? null) === null) {
            return 'La serie no varía: no hay tendencia que medir (R² no aplica).';
        }

        $porcentaje = self::numero($metodo['r2'] * 100, 0);

        return match ($metodo['ajuste']) {
            'fuerte' => 'R² = '.self::numero($metodo['r2'], 2)." (ajuste fuerte): la tendencia explica el {$porcentaje} % de la variación de un mes a otro.",
            'moderado' => 'R² = '.self::numero($metodo['r2'], 2)." (ajuste moderado): la tendencia explica el {$porcentaje} % de la variación; el resto son altibajos propios de cada mes.",
            default => 'R² = '.self::numero($metodo['r2'], 2)." (ajuste débil): la tendencia explica solo el {$porcentaje} % de la variación. Tomar la proyección como referencia, no como meta.",
        };
    }

    /**
     * @param  array<string, mixed>|null  $validacion
     */
    public static function textoValidacion(?array $validacion): string
    {
        if ($validacion === null) {
            return sprintf(
                'Hacen falta al menos %d meses de entrenamiento más los meses a validar para medir el error con datos propios.',
                Pronostico::MINIMO_VALIDACION,
            );
        }

        if ($validacion['recta'] === null) {
            return 'Los meses reservados para validar no tuvieron movimiento: no se puede medir el error porcentual.';
        }

        $texto = sprintf(
            'Se ocultaron los últimos %d meses reales y se pronosticaron con los %d anteriores: la recta se equivocó en promedio un %s %% (MAPE)',
            $validacion['meses'],
            $validacion['entrenamiento'],
            self::numero($validacion['recta'], 1),
        );

        $otros = collect(['holt', 'media_movil'])
            ->filter(fn (string $m) => $validacion[$m] !== null)
            ->map(fn (string $m) => sprintf('%s %s %%', $m === 'holt' ? 'Holt' : 'media móvil', self::numero($validacion[$m], 1)))
            ->implode(', ');

        if ($otros !== '') {
            $texto .= " (contra {$otros})";
        }

        $texto .= '.';

        if (($validacion['mejor'] ?? null) && $validacion['mejor'] !== 'recta') {
            $texto .= sprintf(' En este tramo acertó más %s: conviene leer la recta con cautela.', self::MODELOS[$validacion['mejor']]);
        } else {
            $texto .= ' La recta fue el modelo que menos se equivocó.';
        }

        return $texto;
    }

    /* -----------------------------------------------------------------
     * Informe de proyecciones
     * -------------------------------------------------------------- */

    /**
     * Lectura en prosa de un indicador del informe: situación, tendencia,
     * proyección con su rango, cierre estimado del mes en curso, semáforo
     * (si aplica) y confiabilidad.
     *
     * Cada cifra sale del indicador ya calculado; acá solo se elige la
     * frase. Por eso se puede auditar: todo número del párrafo está en la
     * tabla y en el gráfico de al lado.
     *
     * @param  array<string, mixed>  $indicador  Ver InformeProyeccionesService::indicador().
     * @param  array{mes: string, dias: int, de: int}|null  $mesIncompleto
     * @return list<string>
     */
    public static function lectura(array $indicador, ?array $mesIncompleto = null): array
    {
        $metodo = $indicador['metodo'];
        $tramo = self::tramoUsado($indicador);
        $formato = fn (?float $v): string => self::conUnidad($v, $indicador);

        if ($tramo === []) {
            return ['No hubo datos de este indicador en el periodo, así que no hay tendencia que leer.'];
        }

        $valores = array_column($tramo, 'valor');
        $ultimo = $tramo[count($tramo) - 1];

        $parrafos = [sprintf(
            'Entre %s y %s (%d %s con datos) el indicador promedió %s por mes; el último mes considerado, %s, cerró en %s.',
            $tramo[0]['mes'],
            $ultimo['mes'],
            count($tramo),
            count($tramo) === 1 ? 'mes' : 'meses',
            $formato(array_sum($valores) / count($valores)),
            $ultimo['mes'],
            $formato($ultimo['valor']),
        )];

        if (! ($metodo['suficiente'] ?? false)) {
            $parrafos[] = sprintf(
                'Con menos de %d meses con datos no se traza una tendencia: el informe no proyecta este indicador para no inventar una.',
                $metodo['minimo'] ?? Pronostico::MINIMO_PUNTOS,
            );

            return $parrafos;
        }

        $parrafos[] = self::fraseTendencia($indicador);

        $proyeccion = $indicador['proyeccion'];
        $meses = $indicador['meses_proyeccion'];
        $final = $proyeccion[count($proyeccion) - 1];

        $parrafos[] = sprintf(
            'Si la tendencia se mantiene, para %s se estima %s y para %s %s, con un rango probable de %s a %s (%d %% de confianza).',
            $meses[0],
            $formato($proyeccion[0]['valor']),
            $meses[count($proyeccion) - 1],
            $formato($final['valor']),
            $formato($final['inferior']),
            $formato($final['superior']),
            $metodo['confianza'],
        );

        if (! empty($metodo['cierre_estimado']) && ! empty($metodo['mes_final_excluido'])) {
            $parrafos[] = sprintf(
                '%s está en curso%s y no entra en el cálculo; según la tendencia cerraría en torno a %s.',
                self::mayuscula($metodo['mes_final_excluido']),
                $mesIncompleto ? sprintf(' (%d de %d días)', $mesIncompleto['dias'], $mesIncompleto['de']) : '',
                $formato($metodo['cierre_estimado'][0]['valor']),
            );
        }

        if ($semaforo = $indicador['semaforo'] ?? null) {
            $parrafos[] = sprintf(
                'Contra el semáforo del motor de margen (verde por encima de %s, amarillo por encima de %s), la rentabilidad estimada para %s queda en zona %s.',
                self::formatear($semaforo['verde'], 'porcentaje'),
                self::formatear($semaforo['amarillo'], 'porcentaje'),
                $meses[count($proyeccion) - 1],
                self::zonaSemaforo($final['valor'], $semaforo),
            );
        }

        $parrafos[] = 'Confiabilidad: '.self::textoAjuste($metodo).' '.self::textoValidacion($metodo['validacion'] ?? null);

        return $parrafos;
    }

    /**
     * Fila del resumen ejecutivo de un indicador.
     *
     * @param  array<string, mixed>  $indicador
     * @return array{indicador: string, ultimo: string, tendencia: string, proyeccion: string, rango: string, confiabilidad: string}
     */
    public static function resumen(array $indicador): array
    {
        $metodo = $indicador['metodo'];
        $tramo = self::tramoUsado($indicador);
        $ultimo = $tramo === [] ? null : $tramo[count($tramo) - 1];
        $final = $indicador['proyeccion'] === [] ? null : $indicador['proyeccion'][count($indicador['proyeccion']) - 1];
        $mesFinal = $indicador['meses_proyeccion'][count($indicador['proyeccion']) - 1] ?? '';

        $confiabilidad = '—';
        if ($metodo['suficiente'] ?? false) {
            $partes = [];
            if ($metodo['r2'] !== null) {
                $partes[] = 'R² '.self::numero($metodo['r2'], 2);
            }
            if (($metodo['validacion']['recta'] ?? null) !== null) {
                $partes[] = 'error '.self::numero($metodo['validacion']['recta'], 0).' %';
            }
            $confiabilidad = implode(' · ', $partes) ?: '—';
        }

        return [
            'indicador' => $indicador['titulo'],
            'ultimo' => $ultimo ? self::conUnidad($ultimo['valor'], $indicador).' ('.$ultimo['mes'].')' : '—',
            'tendencia' => self::tendenciaCorta($indicador),
            'proyeccion' => $final ? self::conUnidad($final['valor'], $indicador).' ('.$mesFinal.')' : 'Sin proyección',
            'rango' => $final ? self::formatear($final['inferior'], $indicador['formato']).' – '.self::formatear($final['superior'], $indicador['formato']) : '—',
            'confiabilidad' => $confiabilidad,
        ];
    }

    /**
     * Conclusiones del informe: lo que un gerente debería llevarse en cinco
     * renglones, deducido de los indicadores y las alertas.
     *
     * @param  list<array<string, mixed>>  $indicadores
     * @param  list<array<string, mixed>>  $secciones
     * @return list<string>
     */
    public static function conclusiones(array $indicadores, array $secciones): array
    {
        $porClave = collect($indicadores)->keyBy('clave');
        $conclusiones = [];

        $movimiento = collect(['pedidos' => 'La demanda (pedidos)', 'monto_aprobado' => 'el monto vendido', 'monto_emitido' => 'el monto presupuestado'])
            ->filter(fn (string $texto, string $clave) => $porClave->has($clave) && $porClave[$clave]['metodo']['suficiente'])
            ->map(fn (string $texto, string $clave) => $texto.' '.self::verboTendencia($porClave[$clave]).' ('.self::tendenciaCorta($porClave[$clave]).')')
            ->values();

        if ($movimiento->isNotEmpty()) {
            $conclusiones[] = self::mayuscula($movimiento->implode('; ')).'.';
        }

        $rentabilidad = collect($secciones)->firstWhere('clave', 'rentabilidad');
        if ($rentabilidad && $rentabilidad['tendencia']['ticket'] !== null && $rentabilidad['tendencia']['costo'] !== null) {
            ['ticket' => $ticket, 'costo' => $costo] = $rentabilidad['tendencia'];
            $conclusiones[] = $costo > $ticket
                ? sprintf('El costo promedio por presupuesto crece más rápido que el ticket (%s contra %s por mes): el margen se está achicando aunque se venda más.', self::variacion($costo), self::variacion($ticket))
                : sprintf('El ticket promedio crece al menos al ritmo del costo (%s contra %s por mes): el margen se sostiene.', self::variacion($ticket), self::variacion($costo));
        }

        if ($porClave->has('indice_costo') && ($ritmo = $porClave['indice_costo']['variacion_mensual']) !== null) {
            $umbral = config('bi.alerta_tendencia_mensual') * 100;
            $conclusiones[] = $ritmo >= $umbral
                ? sprintf('El costo de los materiales sube %s por mes, por encima del umbral de alerta (%s por mes): conviene revisar márgenes o adelantar compras.', self::variacion($ritmo), self::formatear($umbral, 'porcentaje'))
                : sprintf('El costo de los materiales varía %s por mes, por debajo del umbral de alerta (%s por mes).', self::variacion($ritmo), self::formatear($umbral, 'porcentaje'));
        }

        $margen = $porClave->get('rentabilidad');
        if ($margen && $margen['proyeccion'] !== [] && $margen['semaforo']) {
            $final = $margen['proyeccion'][count($margen['proyeccion']) - 1];
            $conclusiones[] = sprintf(
                'La rentabilidad sobre costo proyectada para %s es %s: zona %s del semáforo.',
                $margen['meses_proyeccion'][count($margen['proyeccion']) - 1],
                self::formatear($final['valor'], 'porcentaje'),
                self::zonaSemaforo($final['valor'], $margen['semaforo']),
            );
        }

        $cautela = collect($indicadores)
            ->filter(fn (array $i) => ($i['metodo']['suficiente'] ?? false)
                && (($i['metodo']['ajuste'] ?? null) === 'debil'
                    || (($i['metodo']['validacion']['mejor'] ?? 'recta') !== 'recta')))
            ->pluck('titulo');

        if ($cautela->isNotEmpty()) {
            $conclusiones[] = 'Leer con cautela: '.$cautela->implode(', ').' (ajuste débil o, en la validación, otro modelo se equivocó menos que la recta).';
        }

        $alertas = collect($secciones)->firstWhere('clave', 'alertas');
        if ($alertas) {
            $resumen = $alertas['resumen_alertas'];
            $conclusiones[] = $resumen['total'] === 0
                ? 'No hay alertas de costo ni de margen en el periodo.'
                : sprintf('Hay %d %s de costo y margen, %d de prioridad alta (detalle en la sección Alertas).', $resumen['total'], $resumen['total'] === 1 ? 'alerta' : 'alertas', $resumen['alta']);
        }

        return $conclusiones;
    }

    /**
     * Valor con el formato del indicador.
     */
    public static function formatear(float|int|null $valor, string $formato): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($formato) {
            'bs' => 'Bs '.self::numero($valor, 0),
            'bs_exacto' => 'Bs '.self::numero($valor, 2),
            'porcentaje' => self::numero($valor, 1).' %',
            'indice' => self::numero($valor, 1).' pts',
            default => self::numero($valor, abs($valor) < 100 ? 1 : 0),
        };
    }

    /** Variación con signo: "+3,2 %" / "-1,5 %" (guion ASCII: el PDF es cp1252). */
    public static function variacion(float $valor, int $decimales = 1): string
    {
        $signo = $valor > 0 ? '+' : ($valor < 0 ? '-' : '');

        return $signo.self::numero(abs($valor), $decimales).' %';
    }

    /**
     * Meses que usó la recta, con su valor: los del periodo menos los
     * excluidos por incompletos y los que no tienen dato.
     *
     * @param  array<string, mixed>  $indicador
     * @return list<array{mes: string, valor: float}>
     */
    private static function tramoUsado(array $indicador): array
    {
        $metodo = $indicador['metodo'];
        $historico = array_values($indicador['historico']);
        $inicio = empty($metodo['mes_inicial_excluido']) ? 0 : 1;
        $fin = count($historico) - (empty($metodo['mes_final_excluido']) ? 0 : 1);

        $tramo = [];
        for ($i = $inicio; $i < $fin; $i++) {
            if ($historico[$i] !== null) {
                $tramo[] = ['mes' => $indicador['labels'][$i], 'valor' => (float) $historico[$i]];
            }
        }

        return $tramo;
    }

    /**
     * @param  array<string, mixed>  $indicador
     */
    private static function fraseTendencia(array $indicador): string
    {
        $metodo = $indicador['metodo'];

        if ($indicador['formato'] === 'porcentaje') {
            $puntos = $metodo['pendiente'];

            return abs($puntos) < 0.1
                ? 'La tendencia es estable: se mueve menos de una décima de punto porcentual por mes.'
                : sprintf('La tendencia es %s: %s %s puntos porcentuales por mes.', $puntos > 0 ? 'creciente' : 'decreciente', $puntos > 0 ? 'sube' : 'baja', self::numero(abs($puntos), 2));
        }

        $variacion = $indicador['variacion_mensual'];

        if ($variacion === null) {
            return 'La serie promedia cero: su ritmo de cambio no se puede expresar en porcentaje.';
        }

        if (abs($variacion) < 0.5) {
            return sprintf('La tendencia es estable: varía %s por mes.', self::variacion($variacion));
        }

        return sprintf(
            'La tendencia es %s: %s en promedio %s por mes (unos %s por mes).',
            $variacion > 0 ? 'creciente' : 'decreciente',
            $variacion > 0 ? 'sube' : 'baja',
            self::variacion(abs($variacion)),
            self::conUnidad(abs($metodo['pendiente']), $indicador),
        );
    }

    /**
     * @param  array<string, mixed>  $indicador
     */
    private static function tendenciaCorta(array $indicador): string
    {
        if (! ($indicador['metodo']['suficiente'] ?? false)) {
            return 'Sin datos suficientes';
        }

        if ($indicador['formato'] === 'porcentaje') {
            $puntos = $indicador['metodo']['pendiente'];

            return ($puntos > 0 ? '+' : ($puntos < 0 ? '-' : '')).self::numero(abs($puntos), 2).' pts / mes';
        }

        return $indicador['variacion_mensual'] === null ? '—' : self::variacion($indicador['variacion_mensual']).' / mes';
    }

    /**
     * @param  array<string, mixed>  $indicador
     */
    private static function verboTendencia(array $indicador): string
    {
        $variacion = $indicador['variacion_mensual'];

        return match (true) {
            $variacion === null || abs($variacion) < 0.5 => 'se mantiene estable',
            $variacion > 0 => 'crece',
            default => 'cae',
        };
    }

    /**
     * @param  array{verde: float, amarillo: float}  $semaforo
     */
    private static function zonaSemaforo(float $valor, array $semaforo): string
    {
        return match (true) {
            $valor > $semaforo['verde'] => 'verde (aceptar)',
            $valor > $semaforo['amarillo'] => 'amarilla (revisar precio)',
            default => 'roja (no aceptar)',
        };
    }

    /**
     * @param  array<string, mixed>  $indicador
     */
    private static function conUnidad(?float $valor, array $indicador): string
    {
        $texto = self::formatear($valor, $indicador['formato']);

        return isset($indicador['unidad']) && $valor !== null ? "{$texto} {$indicador['unidad']}" : $texto;
    }

    /** Número con formato local: punto de miles, coma decimal. */
    public static function numero(float|int|null $valor, int $decimales = 1): string
    {
        return number_format((float) $valor, $decimales, ',', '.');
    }

    private static function mayuscula(string $texto): string
    {
        return mb_strtoupper(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
    }
}
