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
     * (pedido del usuario), con la redacción prudente del informe modelo
     * (2026-10-29): "Resultados registrados", "Tendencia: el modelo
     * calcula…", "Proyección: …no son límites garantizados", el mes en curso,
     * el semáforo (si aplica, diciendo si el rango cruza de zona) y "Cómo
     * usar la estimación". Sin R², MAPE ni nombres de métodos: eso queda en
     * el anexo (ficha()).
     *
     * Cada cifra sale del indicador ya calculado; acá solo se elige la
     * frase. Por eso se puede auditar: todo número del párrafo está en la
     * tabla y en el gráfico de al lado. El indicador declara su `sujeto`
     * ("el monto cotizado", "la demanda"), siempre en singular.
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
        $sujeto = $indicador['sujeto'] ?? 'el indicador';

        if ($tramo === []) {
            return ['No hubo datos de este indicador en el periodo.'];
        }

        $ultimo = $tramo[count($tramo) - 1];

        $parrafos = [sprintf(
            'Resultados registrados: de %s a %s, %s promedió %s al mes. En %s fue de %s%s.',
            self::mesLargo($tramo[0]['mes']),
            self::mesLargo($ultimo['mes']),
            $sujeto,
            $formato(self::promedio($tramo)),
            self::nombreDelMes($ultimo['mes']),
            $formato($ultimo['valor']),
            // El índice dicho en palabras: 115,9 pts = 15,9 % más caros.
            $indicador['formato'] === 'indice' && abs($ultimo['valor'] - 100) >= 0.5
                ? sprintf(' (es decir, %s %s que al inicio)', self::formatear(abs($ultimo['valor'] - 100), 'porcentaje'), $ultimo['valor'] > 100 ? 'más caros' : 'más baratos')
                : '',
        )];

        if (! ($metodo['suficiente'] ?? false)) {
            $parrafos[] = sprintf(
                'Proyección: todavía no hay meses suficientes para estimarlo (hacen falta al menos %d).',
                $metodo['minimo'] ?? Pronostico::MINIMO_PUNTOS,
            );

            return $parrafos;
        }

        $parrafos[] = self::fraseTendencia($indicador);

        $proyeccion = $indicador['proyeccion'];
        $meses = $indicador['meses_proyeccion'];
        $final = $proyeccion[count($proyeccion) - 1];
        $mesFinal = $meses[count($proyeccion) - 1];

        $parrafos[] = sprintf(
            'Proyección: si continúa esa tendencia, se estiman %s para %s y %s para %s. Para %s, el rango estimado es de %s a %s; no son límites garantizados.',
            $formato($proyeccion[0]['valor']),
            self::mesLargo($meses[0]),
            $formato($final['valor']),
            self::mesLargo($mesFinal),
            self::nombreDelMes($mesFinal),
            $formato($final['inferior']),
            $formato($final['superior']),
        );

        if (! empty($metodo['cierre_estimado']) && ! empty($metodo['mes_final_excluido'])) {
            $parrafos[] = sprintf(
                '%s incompleto: %s No se usa para calcular la tendencia; el cierre estimado es %s.',
                self::mayuscula(self::nombreDelMes($metodo['mes_final_excluido'])),
                $mesIncompleto ? sprintf('hay datos de %d de %d días.', $mesIncompleto['dias'], $mesIncompleto['de']) : 'todavía no termina.',
                $formato($metodo['cierre_estimado'][0]['valor']),
            );
        }

        if ($semaforo = $indicador['semaforo'] ?? null) {
            $parrafos[] = self::fraseSemaforo($final, $semaforo, $mesFinal);
        }

        $parrafos[] = self::fraseConfianza($metodo, $sujeto);

        return $parrafos;
    }

    /**
     * "Semáforo del sistema: verde por encima de 30,0 %; … El valor central
     * de enero está en verde, aunque su rango también alcanza la zona
     * amarilla." Decir solo la zona del valor central escondería que el
     * rango puede caer en otra.
     *
     * @param  array{valor: float, inferior: float, superior: float}  $final
     * @param  array{verde: float, amarillo: float}  $semaforo
     */
    private static function fraseSemaforo(array $final, array $semaforo, string $mes): string
    {
        $verde = self::formatear($semaforo['verde'], 'porcentaje');
        $amarillo = self::formatear($semaforo['amarillo'], 'porcentaje');
        $zona = fn (float $v): string => self::zonaSemaforo($v, $semaforo, corta: true);

        $central = $zona($final['valor']);
        $otras = collect([$zona($final['inferior']), $zona($final['superior'])])
            ->reject(fn (string $z) => $z === $central)
            ->unique()
            ->values();

        $adjetivo = ['verde' => 'verde', 'amarilla' => 'amarillo', 'roja' => 'rojo'];
        $lectura = $otras->isEmpty()
            ? sprintf('El valor central de %s y todo su rango están en %s.', self::nombreDelMes($mes), $adjetivo[$central])
            : sprintf(
                'El valor central de %s está en %s, aunque su rango también alcanza %s.',
                self::nombreDelMes($mes),
                $adjetivo[$central],
                $otras->count() === 1 ? "la zona {$otras[0]}" : "las zonas {$otras[0]} y {$otras[1]}",
            );

        return "Semáforo del sistema: verde por encima de {$verde}; amarillo entre {$amarillo} y {$verde}; rojo por debajo de {$amarillo}. {$lectura}";
    }

    /**
     * Precio promedio frente a costo promedio, con las pendientes en % por
     * mes ya redondeadas a un decimal (como se muestran): con menos de 0,2
     * puntos de diferencia es ruido y se dice "se mueven parecido". Las dos
     * pueden ser negativas: por eso "va por detrás" y no "crece más lento".
     *
     * Es la única regla: la usan la pestaña Presupuestos y rentabilidad y el
     * informe, así no pueden decir cosas distintas.
     *
     * @return array{comparacion: string, lectura: string}|null
     */
    public static function precioFrenteACosto(?float $precio, ?float $costo): ?array
    {
        if ($precio === null || $costo === null) {
            return null;
        }

        $diferencia = round($precio, 1) - round($costo, 1);
        $comparacion = match (true) {
            $diferencia <= -0.2 + 1e-9 => 'detras',
            $diferencia >= 0.2 - 1e-9 => 'adelante',
            default => 'parecido',
        };

        $movimiento = fn (float $v): ?array => abs(round($v, 1)) < 0.1
            ? null
            : ['sentido' => $v > 0 ? 'sube' : 'baja', 'cuanto' => self::numero(abs($v), 1).' %'];

        $p = $movimiento($precio);
        $c = $movimiento($costo);
        $delPrecio = $p ? "El precio promedio {$p['sentido']} {$p['cuanto']} por mes" : 'El precio promedio se mantiene';
        $delCosto = match (true) {
            $c === null => 'y el costo se mantiene',
            $p !== null && $p['sentido'] === $c['sentido'] => "y el costo, {$c['cuanto']}",
            default => "y el costo {$c['sentido']} {$c['cuanto']} por mes",
        };

        return [
            'comparacion' => $comparacion,
            'lectura' => "{$delPrecio} {$delCosto}. ".[
                'detras' => 'El precio va por detrás del costo: la rentabilidad tiende a achicarse.',
                'adelante' => 'El precio le gana al costo: la rentabilidad se sostiene o crece.',
                'parecido' => 'Se mueven parecido: la relación entre precio y costo se mantiene.',
            ][$comparacion],
        ];
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
            // Temas en plural: el rumbo dice "van en aumento" / "van bajando".
            'monto_aprobado' => ['Montos aprobados', 'en promedio %s por mes'],
            'pedidos' => ['Pedidos', 'en promedio %s por mes'],
            'monto_emitido' => ['Montos cotizados', 'en promedio %s por mes'],
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
        $precioCosto = $rentabilidad ? self::precioFrenteACosto($rentabilidad['tendencia']['ticket'], $rentabilidad['tendencia']['costo']) : null;
        if ($precioCosto !== null) {
            $conclusiones[] = [
                'detras' => 'Precios y costos: el precio de los trabajos va por detrás de su costo, así que la rentabilidad tiende a achicarse.',
                'adelante' => 'Precios y costos: el precio de los trabajos le gana a su costo, así que la rentabilidad se sostiene o crece.',
                'parecido' => 'Precios y costos: se mueven parecido, así que la relación entre precio y costo se mantiene.',
            ][$precioCosto['comparacion']];
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
            // La del periodo completo (resultado total ÷ costo total), la misma
            // que la pestaña; sin ella, el promedio de los meses.
            $delPeriodo = $rentabilidad['resumen']['rentabilidad'] ?? null;
            $frase = $delPeriodo !== null
                ? sprintf('Rentabilidad: por cada Bs 100 de costo, el resultado calculado fue de Bs %s en el periodo, ya descontados los impuestos', self::numero($delPeriodo, 1))
                : sprintf('Rentabilidad: por cada Bs 100 de costo, el resultado calculado fue en promedio de Bs %s por mes, ya descontados los impuestos', self::numero(self::promedio($tramo), 1));

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
     * "Tendencia: el modelo calcula un aumento de Bs 4.101 por mes (+0,5 %
     * mensual)."; en un porcentaje, en puntos porcentuales.
     *
     * @param  array<string, mixed>  $indicador
     */
    private static function fraseTendencia(array $indicador): string
    {
        $metodo = $indicador['metodo'];

        if ($indicador['formato'] === 'porcentaje') {
            $puntos = $metodo['pendiente'];

            return abs($puntos) < 0.1
                ? 'Tendencia: el modelo no muestra un cambio claro; se mantiene estable.'
                : sprintf(
                    'Tendencia: el modelo calcula %s de %s puntos porcentuales por mes.',
                    $puntos > 0 ? 'un aumento' : 'una disminución',
                    self::numero(abs($puntos), 2),
                );
        }

        $variacion = $indicador['variacion_mensual'];

        if ($variacion === null) {
            return 'Tendencia: no se puede medir el ritmo, porque el promedio del periodo es cero.';
        }

        if (abs($variacion) < 0.5) {
            return 'Tendencia: el modelo no muestra un cambio claro; se mantiene estable (menos de medio por ciento al mes).';
        }

        return sprintf(
            'Tendencia: el modelo calcula %s de %s por mes (%s mensual).',
            $variacion > 0 ? 'un aumento' : 'una disminución',
            self::cantidadPorMes(abs($metodo['pendiente']), $indicador),
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
     * "Cómo usar la estimación: solo como referencia, porque el monto
     * cotizado varía mucho de un mes a otro. Al compararla con los últimos 3
     * meses conocidos, el error promedio fue 46,7 %."
     *
     * @param  array<string, mixed>  $metodo
     */
    private static function fraseConfianza(array $metodo, string $sujeto = 'el indicador'): string
    {
        if (($metodo['r2'] ?? null) === null) {
            return "Cómo usar la estimación: {$sujeto} no cambió en el periodo, así que se espera que siga igual.";
        }

        $frase = match (self::nivelConfianza($metodo)) {
            'Alta' => 'Cómo usar la estimación: como una referencia confiable, porque los meses siguen una línea clara.',
            'Media' => 'Cómo usar la estimación: con cautela, porque los meses siguen una tendencia con algunas subidas y bajadas.',
            default => "Cómo usar la estimación: solo como referencia, porque {$sujeto} varía mucho de un mes a otro.",
        };

        $validacion = $metodo['validacion'] ?? null;

        if ($validacion !== null && ($validacion['recta'] ?? null) !== null) {
            $frase .= sprintf(
                ' Al compararla con los últimos %d meses conocidos, el error promedio fue %s.',
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
     * Zona del semáforo de un valor; con `corta`, solo el color ("amarilla").
     *
     * @param  array{verde: float, amarillo: float}  $semaforo
     */
    private static function zonaSemaforo(float $valor, array $semaforo, bool $corta = false): string
    {
        $zona = match (true) {
            $valor > $semaforo['verde'] => 'verde',
            $valor > $semaforo['amarillo'] => 'amarilla',
            default => 'roja',
        };

        return $corta ? $zona : [
            'verde' => 'verde (buena)',
            'amarilla' => 'amarilla (conviene revisar precios)',
            'roja' => 'roja (resultado muy bajo)',
        ][$zona];
    }

    /** "Nov-2025" → "noviembre de 2025". Lo que no tenga esa forma se devuelve tal cual. */
    private static function mesLargo(string $etiqueta): string
    {
        $mes = self::numeroDeMes($etiqueta);

        return $mes === null
            ? $etiqueta
            : mb_strtolower(PeriodoReporte::nombreMesCompleto($mes)).' de '.substr($etiqueta, -4);
    }

    /** "Nov-2025" → "noviembre". */
    private static function nombreDelMes(string $etiqueta): string
    {
        $mes = self::numeroDeMes($etiqueta);

        return $mes === null ? $etiqueta : mb_strtolower(PeriodoReporte::nombreMesCompleto($mes));
    }

    private static function numeroDeMes(string $etiqueta): ?int
    {
        if (! preg_match('/^(\p{L}{3})-\d{4}$/u', $etiqueta, $partes)) {
            return null;
        }

        foreach (range(1, 12) as $mes) {
            if (PeriodoReporte::nombreMes($mes) === $partes[1]) {
                return $mes;
            }
        }

        return null;
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
