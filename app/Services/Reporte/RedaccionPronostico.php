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
        'recta' => 'la tendencia lineal',
        'holt' => 'el suavizado exponencial (Holt)',
        'media_movil' => 'el promedio de los últimos 3 meses',
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
                ' %s no se usa para calcular la tendencia porque aún no termina%s: se muestra en el gráfico y la línea punteada estima cómo cerraría.',
                self::mayuscula($metodo['mes_final_excluido']),
                $mesIncompleto ? sprintf(' (%d de %d días)', $mesIncompleto['dias'], $mesIncompleto['de']) : '',
            );
        }

        if (! empty($metodo['mes_inicial_excluido'])) {
            $historia .= sprintf(' %s tampoco se usa: el periodo empieza a mitad de ese mes.', self::mayuscula($metodo['mes_inicial_excluido']));
        }

        if (! ($metodo['suficiente'] ?? false)) {
            return [
                ['rotulo' => 'Meses analizados', 'texto' => $historia],
                ['rotulo' => 'Proyección', 'texto' => sprintf(
                    'Se necesitan al menos %d meses con datos para calcular una tendencia. No se proyecta para no dar una cifra sin respaldo.',
                    $metodo['minimo'] ?? Pronostico::MINIMO_PUNTOS,
                )],
            ];
        }

        $renglones = [
            ['rotulo' => 'Método', 'texto' => 'Se traza la tendencia de los meses con datos (una línea recta que pasa lo más cerca posible de cada mes) y se prolonga '
                .config('bi.meses_proyeccion').' meses hacia adelante.'],
            ['rotulo' => 'Meses analizados', 'texto' => $historia],
            ['rotulo' => 'Confiabilidad', 'texto' => self::textoAjuste($metodo)],
            ['rotulo' => 'Rango probable', 'texto' => sprintf(
                'El resultado real debería quedar entre el mínimo y el máximo probables en %d de cada 100 casos. El rango se amplía cuanto más lejana es la proyección. (Intervalo de predicción: t = %s con %d grados de libertad, error típico %s.)',
                $metodo['confianza'],
                self::numero($metodo['t'], 3),
                $metodo['grados_libertad'],
                self::numero($metodo['error_tipico'], 2),
            )],
            ['rotulo' => 'Prueba con datos reales', 'texto' => self::textoValidacion($metodo['validacion'] ?? null)],
        ];

        if (! ($metodo['piso'] ?? true)) {
            $renglones[] = ['rotulo' => 'Valores negativos', 'texto' => 'Este indicador puede proyectarse en negativo (un margen o una utilidad pueden serlo): no se limita a cero.'];
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
            return 'El indicador no cambió en el periodo: no hay tendencia que medir.';
        }

        $porcentaje = self::numero($metodo['r2'] * 100, 0);

        return match ($metodo['ajuste']) {
            'fuerte' => "Alta: la tendencia explica el {$porcentaje} % de los cambios de un mes a otro (R² = ".self::numero($metodo['r2'], 2).').',
            'moderado' => "Media: la tendencia explica el {$porcentaje} % de los cambios; el resto son variaciones propias de cada mes (R² = ".self::numero($metodo['r2'], 2).').',
            default => "Baja: la tendencia explica solo el {$porcentaje} % de los cambios (R² = ".self::numero($metodo['r2'], 2).'). Conviene tomar la proyección como referencia, no como meta.',
        };
    }

    /**
     * @param  array<string, mixed>|null  $validacion
     */
    public static function textoValidacion(?array $validacion): string
    {
        if ($validacion === null) {
            return sprintf(
                'No hay meses suficientes para probar la proyección con datos reales: se necesitan al menos %d, además de los meses que se comparan.',
                Pronostico::MINIMO_VALIDACION,
            );
        }

        if ($validacion['recta'] === null) {
            return 'Los meses usados para la prueba no tuvieron movimiento: no se puede medir el error.';
        }

        $texto = sprintf(
            'Se calculó la proyección sin los últimos %d meses reales (usando los %d anteriores) y se comparó con lo que realmente ocurrió: el error promedio fue de %s %% (MAPE)',
            $validacion['meses'],
            $validacion['entrenamiento'],
            self::numero($validacion['recta'], 1),
        );

        $otros = collect(['holt', 'media_movil'])
            ->filter(fn (string $m) => $validacion[$m] !== null)
            ->map(fn (string $m) => sprintf('%s %s %%', $m === 'holt' ? 'Holt' : 'promedio de 3 meses', self::numero($validacion[$m], 1)))
            ->implode(', ');

        if ($otros !== '') {
            $texto .= " (otros métodos: {$otros})";
        }

        $texto .= '.';

        if (($validacion['mejor'] ?? null) && $validacion['mejor'] !== 'recta') {
            $texto .= sprintf(' En este tramo fue más preciso %s: conviene leer la proyección con cautela.', self::MODELOS[$validacion['mejor']]);
        } else {
            $texto .= ' La tendencia lineal fue el método más preciso.';
        }

        return $texto;
    }

    /* -----------------------------------------------------------------
     * Informe de proyecciones
     * -------------------------------------------------------------- */

    /**
     * Lectura en prosa de un indicador del informe, para quien NO es analista
     * (pedido del usuario): qué pasó, cómo viene, qué se espera, el mes en
     * curso, el semáforo (si aplica) y cuánta confianza merece. Sin R², MAPE
     * ni nombres de métodos: eso queda en el anexo (ficha()).
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
            return ['No hubo datos de este indicador en el periodo.'];
        }

        $ultimo = $tramo[count($tramo) - 1];

        $parrafos = [sprintf(
            'Qué pasó: entre %s y %s el promedio fue de %s por mes; en %s, el último mes analizado, fue de %s%s.',
            $tramo[0]['mes'],
            $ultimo['mes'],
            $formato(self::promedio($tramo)),
            $ultimo['mes'],
            $formato($ultimo['valor']),
            // El índice dicho en palabras: 115,9 pts = 15,9 % más caros.
            $indicador['formato'] === 'indice' && abs($ultimo['valor'] - 100) >= 0.5
                ? sprintf(' (es decir, %s %s que al inicio)', self::formatear(abs($ultimo['valor'] - 100), 'porcentaje'), $ultimo['valor'] > 100 ? 'más caros' : 'más baratos')
                : '',
        )];

        if (! ($metodo['suficiente'] ?? false)) {
            $parrafos[] = sprintf(
                'Qué se espera: todavía no hay meses suficientes para estimarlo (hacen falta al menos %d).',
                $metodo['minimo'] ?? Pronostico::MINIMO_PUNTOS,
            );

            return $parrafos;
        }

        $parrafos[] = self::fraseTendencia($indicador);

        $proyeccion = $indicador['proyeccion'];
        $meses = $indicador['meses_proyeccion'];
        $final = $proyeccion[count($proyeccion) - 1];

        $parrafos[] = sprintf(
            'Qué se espera: si todo sigue igual, para %s se esperan unos %s y para %s unos %s. Lo más probable es que el resultado real quede entre %s y %s.',
            $meses[0],
            $formato($proyeccion[0]['valor']),
            $meses[count($proyeccion) - 1],
            $formato($final['valor']),
            $formato($final['inferior']),
            $formato($final['superior']),
        );

        if (! empty($metodo['cierre_estimado']) && ! empty($metodo['mes_final_excluido'])) {
            $parrafos[] = sprintf(
                '%s todavía no termina%s: se muestra aparte y no se usa en el cálculo; debería cerrar cerca de %s.',
                self::mayuscula($metodo['mes_final_excluido']),
                $mesIncompleto ? sprintf(' (van %d de %d días)', $mesIncompleto['dias'], $mesIncompleto['de']) : '',
                $formato($metodo['cierre_estimado'][0]['valor']),
            );
        }

        if ($semaforo = $indicador['semaforo'] ?? null) {
            $parrafos[] = sprintf(
                'Semáforo: verde desde %s, amarillo entre %s y %s, rojo por debajo. Para %s se espera que quede en zona %s.',
                self::formatear($semaforo['verde'], 'porcentaje'),
                self::formatear($semaforo['amarillo'], 'porcentaje'),
                self::formatear($semaforo['verde'], 'porcentaje'),
                $meses[count($proyeccion) - 1],
                self::zonaSemaforo($final['valor'], $semaforo),
            );
        }

        $parrafos[] = self::fraseConfianza($metodo);

        return $parrafos;
    }

    /**
     * Fila de "Qué esperar en los próximos meses" (resumen de la portada).
     *
     * @param  array<string, mixed>  $indicador
     * @return array{indicador: string, promedio: string, ultimo: string, tendencia: string, mes_proyeccion: string, proyeccion: string, rango: string, confiabilidad: string}
     */
    public static function resumen(array $indicador): array
    {
        $metodo = $indicador['metodo'];
        $tramo = self::tramoUsado($indicador);
        $ultimo = $tramo === [] ? null : $tramo[count($tramo) - 1];
        $final = $indicador['proyeccion'] === [] ? null : $indicador['proyeccion'][count($indicador['proyeccion']) - 1];
        $mesFinal = $indicador['meses_proyeccion'][count($indicador['proyeccion']) - 1] ?? '';

        return [
            'indicador' => $indicador['titulo'],
            'promedio' => $tramo === [] ? '—' : self::conUnidad(self::promedio($tramo), $indicador),
            'ultimo' => $ultimo ? self::conUnidad($ultimo['valor'], $indicador).' ('.$ultimo['mes'].')' : '—',
            'tendencia' => self::tendenciaCorta($indicador),
            'mes_proyeccion' => $mesFinal,
            'proyeccion' => $final ? self::conUnidad($final['valor'], $indicador) : 'Sin estimación',
            'rango' => $final ? self::rangoCompacto($final['inferior'], $final['superior'], $indicador['formato']) : '—',
            'confiabilidad' => ($metodo['suficiente'] ?? false) ? self::nivelConfianza($metodo) : '—',
        ];
    }

    /**
     * "En pocas palabras": lo que el dueño debería llevarse de la portada, en
     * frases simples, deducido de los indicadores y las alertas. Cada frase
     * empieza con el tema ("Ventas:", "Materiales:"…) para leerse de un
     * vistazo.
     *
     * @param  list<array<string, mixed>>  $indicadores
     * @param  list<array<string, mixed>>  $secciones
     * @return list<string>
     */
    public static function conclusiones(array $indicadores, array $secciones): array
    {
        $porClave = collect($indicadores)->keyBy('clave');
        $conclusiones = [];

        foreach ([
            'monto_aprobado' => ['Ventas', 'en promedio %s vendidos por mes'],
            'pedidos' => ['Pedidos', 'en promedio %s por mes'],
            'monto_emitido' => ['Cotizaciones', 'en promedio %s cotizados por mes'],
        ] as $clave => [$tema, $frase]) {
            $indicador = $porClave->get($clave);
            $tramo = $indicador ? self::tramoUsado($indicador) : [];

            if ($tramo === []) {
                continue;
            }

            $conclusiones[] = sprintf(
                '%s: %s%s.',
                $tema,
                sprintf($frase, self::conUnidad(self::promedio($tramo), $indicador)),
                ($indicador['metodo']['suficiente'] ?? false) ? '; '.self::rumbo($indicador) : '',
            );
        }

        $rentabilidad = collect($secciones)->firstWhere('clave', 'rentabilidad');
        if ($rentabilidad && $rentabilidad['tendencia']['ticket'] !== null && $rentabilidad['tendencia']['costo'] !== null) {
            ['ticket' => $precio, 'costo' => $costo] = $rentabilidad['tendencia'];
            // "Va por detrás" y no "crece más lento": las dos pendientes
            // pueden ser negativas (precio −0,5 % y costo −0,3 % por mes).
            $conclusiones[] = $costo > $precio
                ? 'Precios y costos: el precio de los trabajos se está quedando atrás de lo que cuesta hacerlos, así que cada trabajo deja un poco menos de ganancia.'
                : 'Precios y costos: los precios acompañan a los costos, así que la ganancia de cada trabajo se mantiene.';
        }

        if ($porClave->has('indice_costo') && ($ritmo = $porClave['indice_costo']['variacion_mensual']) !== null) {
            $tramo = self::tramoUsado($porClave['indice_costo']);
            $hoy = $tramo === [] ? null : $tramo[count($tramo) - 1]['valor'] - 100;
            $umbral = config('bi.alerta_tendencia_mensual') * 100;

            $conclusiones[] = 'Materiales: '
                .($hoy === null || abs($hoy) < 0.5
                    ? 'cuestan lo mismo que al inicio del periodo'
                    : sprintf('hoy cuestan %s %s que al inicio del periodo', self::formatear(abs($hoy), 'porcentaje'), $hoy > 0 ? 'más' : 'menos'))
                .(abs($ritmo) < 0.5 ? ' y sus precios están estables' : sprintf(' y %s alrededor de %s cada mes', $ritmo > 0 ? 'suben' : 'bajan', self::formatear(abs($ritmo), 'porcentaje')))
                .($ritmo >= $umbral ? '. Suben más rápido de lo normal: conviene revisar los precios de venta o adelantar compras.' : '.');
        }

        $margen = $porClave->get('rentabilidad');
        if ($margen && ($tramo = self::tramoUsado($margen)) !== []) {
            $frase = sprintf('Ganancia: de cada Bs 100 que cuesta hacer un trabajo, quedan en promedio Bs %s de ganancia después de impuestos', self::numero(self::promedio($tramo), 1));

            if ($margen['proyeccion'] !== [] && $margen['semaforo']) {
                $final = $margen['proyeccion'][count($margen['proyeccion']) - 1];
                $frase .= sprintf('; para %s se espera que siga en zona %s', $margen['meses_proyeccion'][count($margen['proyeccion']) - 1], self::zonaSemaforo($final['valor'], $margen['semaforo']));
            }

            $conclusiones[] = $frase.'.';
        }

        $alertas = collect($secciones)->firstWhere('clave', 'alertas');
        if ($alertas) {
            $resumen = $alertas['resumen_alertas'];
            $media = $resumen['total'] - $resumen['alta'];
            $conclusiones[] = $resumen['total'] === 0
                ? 'Alertas: no hay alertas de costo ni de margen en el periodo.'
                : sprintf(
                    'Alertas: %d para atender ya y %d para revisar esta semana (detalle en la sección Alertas).',
                    $resumen['alta'],
                    $media,
                );
        }

        // Qué estimaciones son confiables, sin listar media docena de
        // nombres: si la mayoría es dudosa se dice "la mayoría" y se nombran
        // las que sí sirven.
        $conEstimacion = collect($indicadores)->filter(fn (array $i) => $i['metodo']['suficiente'] ?? false);
        $esDudosa = fn (array $i): bool => ($i['metodo']['ajuste'] ?? null) === 'debil'
            || (($i['metodo']['validacion']['mejor'] ?? 'recta') !== 'recta');
        $comillas = fn (string $titulo): string => "\"{$titulo}\"";
        $dudosas = $conEstimacion->filter($esDudosa)->pluck('titulo')->map($comillas);
        $confiables = $conEstimacion->reject($esDudosa)->pluck('titulo')->map($comillas);

        if ($dudosas->isNotEmpty()) {
            $conclusiones[] = $dudosas->count() > $conEstimacion->count() / 2
                ? 'Estimaciones: la mayoría de los indicadores sube y baja mucho de un mes a otro, así que sus estimaciones sirven solo como referencia'
                    .($confiables->isEmpty() ? '.' : sprintf('. Solo %s %s una línea clara.', self::enumerar($confiables->all()), $confiables->count() === 1 ? 'sigue' : 'siguen'))
                : sprintf(
                    'Estimaciones: %s %s mucho de un mes a otro; %s solo como referencia.',
                    self::enumerar($dudosas->all()),
                    $dudosas->count() === 1 ? 'sube y baja' : 'suben y bajan',
                    $dudosas->count() === 1 ? 'su estimación sirve' : 'sus estimaciones sirven',
                );
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
            // Pedidos y otras cantidades: "67 pedidos", no "67,3 pedidos".
            'cantidad' => self::numero($valor, 0),
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
     * "Cómo viene: va en aumento, unos 0,6 pedidos más cada mes (+1,0 % al mes)."
     *
     * @param  array<string, mixed>  $indicador
     */
    private static function fraseTendencia(array $indicador): string
    {
        $metodo = $indicador['metodo'];

        if ($indicador['formato'] === 'porcentaje') {
            $puntos = $metodo['pendiente'];

            return abs($puntos) < 0.1
                ? 'Cómo viene: se mantiene estable.'
                : sprintf('Cómo viene: va %s, unos %s puntos por mes.', $puntos > 0 ? 'en aumento' : 'bajando', self::numero(abs($puntos), 2));
        }

        $variacion = $indicador['variacion_mensual'];

        if ($variacion === null) {
            return 'Cómo viene: no se puede medir el ritmo, porque el promedio del periodo es cero.';
        }

        if (abs($variacion) < 0.5) {
            return 'Cómo viene: se mantiene estable (cambia menos de medio por ciento al mes).';
        }

        return sprintf(
            'Cómo viene: va %s, unos %s %s cada mes (%s al mes).',
            $variacion > 0 ? 'en aumento' : 'bajando',
            self::cantidadPorMes(abs($metodo['pendiente']), $indicador),
            $variacion > 0 ? 'más' : 'menos',
            self::variacion($variacion),
        );
    }

    /**
     * Cambio por mes con unidad, sin redondear a cero lo que es chico
     * ("0,6 pedidos", "Bs 10.463", "1,2 pts").
     *
     * @param  array<string, mixed>  $indicador
     */
    private static function cantidadPorMes(float $valor, array $indicador): string
    {
        if ($indicador['formato'] !== 'cantidad') {
            return self::conUnidad($valor, $indicador);
        }

        $texto = self::numero($valor, $valor < 10 ? 1 : 0);

        return isset($indicador['unidad']) ? "{$texto} {$indicador['unidad']}" : $texto;
    }

    /**
     * Confianza en la estimación, en palabras.
     *
     * @param  array<string, mixed>  $metodo
     */
    private static function fraseConfianza(array $metodo): string
    {
        if (($metodo['r2'] ?? null) === null) {
            return 'Confianza en la estimación: el indicador no cambió en el periodo, así que se espera que siga igual.';
        }

        $frase = match (self::nivelConfianza($metodo)) {
            'Alta' => 'Confianza en la estimación: alta. Los meses siguen una línea clara.',
            'Media' => 'Confianza en la estimación: media. Los meses siguen una tendencia, con algunas subidas y bajadas.',
            default => 'Confianza en la estimación: baja. Los meses suben y bajan mucho, así que la estimación es solo una referencia.',
        };

        $validacion = $metodo['validacion'] ?? null;

        if ($validacion !== null && ($validacion['recta'] ?? null) !== null) {
            $frase .= sprintf(
                ' Al probar el cálculo con los últimos %d meses ya conocidos, se equivocó en promedio un %s.',
                $validacion['meses'],
                self::formatear($validacion['recta'], 'porcentaje'),
            );
        }

        return $frase;
    }

    /**
     * Alta / Media / Baja según qué tanto siguen los meses a la tendencia.
     *
     * @param  array<string, mixed>  $metodo
     */
    public static function nivelConfianza(array $metodo): string
    {
        return match (true) {
            ($metodo['r2'] ?? null) === null => 'Alta',
            ($metodo['ajuste'] ?? null) === 'fuerte' => 'Alta',
            ($metodo['ajuste'] ?? null) === 'moderado' => 'Media',
            default => 'Baja',
        };
    }

    /**
     * "Sube 1,0 % al mes", "Estable", "Baja 0,17 pts al mes".
     *
     * @param  array<string, mixed>  $indicador
     */
    private static function tendenciaCorta(array $indicador): string
    {
        if (! ($indicador['metodo']['suficiente'] ?? false)) {
            return 'Faltan datos';
        }

        if ($indicador['formato'] === 'porcentaje') {
            $puntos = $indicador['metodo']['pendiente'];

            return abs($puntos) < 0.1
                ? 'Estable'
                : sprintf('%s %s pts al mes', $puntos > 0 ? 'Sube' : 'Baja', self::numero(abs($puntos), 2));
        }

        $variacion = $indicador['variacion_mensual'];

        return match (true) {
            $variacion === null => '—',
            abs($variacion) < 0.5 => 'Estable',
            default => sprintf('%s %s al mes', $variacion > 0 ? 'Sube' : 'Baja', self::formatear(abs($variacion), 'porcentaje')),
        };
    }

    /**
     * Rumbo en una frase corta: "van en aumento (+1,0 % al mes)".
     *
     * @param  array<string, mixed>  $indicador
     */
    private static function rumbo(array $indicador): string
    {
        $variacion = $indicador['variacion_mensual'];

        return match (true) {
            $variacion === null || abs($variacion) < 0.5 => 'se mantienen estables',
            $variacion > 0 => sprintf('van en aumento (%s al mes)', self::variacion($variacion)),
            default => sprintf('van bajando (%s al mes)', self::variacion($variacion)),
        };
    }

    /**
     * @param  array{verde: float, amarillo: float}  $semaforo
     */
    private static function zonaSemaforo(float $valor, array $semaforo): string
    {
        return match (true) {
            $valor > $semaforo['verde'] => 'verde (buena)',
            $valor > $semaforo['amarillo'] => 'amarilla (conviene revisar precios)',
            default => 'roja (ganancia muy baja)',
        };
    }

    /**
     * "Bs 532.221 – 1.226.114", "30,4 – 36,4 %", "49 – 89": la unidad una
     * sola vez, para que el rango entre en una línea de la tabla.
     */
    private static function rangoCompacto(float $inferior, float $superior, string $formato): string
    {
        $desnudo = fn (float $v): string => match ($formato) {
            'bs' => self::numero($v, 0),
            'bs_exacto' => self::numero($v, 2),
            'porcentaje', 'indice' => self::numero($v, 1),
            default => self::formatear($v, $formato),
        };

        $rango = $desnudo($inferior).' – '.$desnudo($superior);

        return match ($formato) {
            'bs', 'bs_exacto' => 'Bs '.$rango,
            'porcentaje' => $rango.' %',
            'indice' => $rango.' pts',
            default => $rango,
        };
    }

    /**
     * @param  list<array{mes: string, valor: float}>  $tramo
     */
    private static function promedio(array $tramo): float
    {
        return array_sum(array_column($tramo, 'valor')) / count($tramo);
    }

    /**
     * "a", "a y b", "a, b y c".
     *
     * @param  list<string>  $partes
     */
    private static function enumerar(array $partes): string
    {
        $ultima = array_pop($partes);

        return $partes === [] ? (string) $ultima : implode(', ', $partes).' y '.$ultima;
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
