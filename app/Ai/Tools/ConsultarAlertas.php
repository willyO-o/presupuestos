<?php

namespace App\Ai\Tools;

use App\Models\BiAnomalia;
use App\Services\Reporte\AlertasNegocioService;
use App\Services\Reporte\PeriodoReporte;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Lo que hoy necesita atención: alertas de costo y margen (las mismas de la
 * pestaña Alertas, últimos 3 meses) y anomalías detectadas sin revisar.
 */
class ConsultarAlertas extends HerramientaBi
{
    public function description(): Stringable|string
    {
        return 'Devuelve las alertas vigentes (aumentos de costo, presupuestos con margen bajo o costos desactualizados, sobrecostos de pedidos) '
            .'y las anomalías detectadas que todavía no se revisaron. Úsala para "¿qué debería revisar?" o "¿hay algún problema?".';
    }

    public function schema(JsonSchema $schema): array
    {
        // Algunos proveedores rechazan herramientas sin ningún parámetro.
        return [
            'prioridad' => $schema->string()->enum(['todas', 'alta'])->description('"alta" para ver solo lo urgente.'),
        ];
    }

    protected function reglas(): array
    {
        return ['prioridad' => ['nullable', 'in:todas,alta']];
    }

    protected function ejecutar(array $datos): array
    {
        $periodo = PeriodoReporte::desdeFiltros(null, null, 3);
        $alertas = app(AlertasNegocioService::class)->datos($this->usuario, $periodo);

        $anomalias = BiAnomalia::query()
            ->visibleAnomaliaPara($this->usuario)
            ->where('estado', 'NUEVA')
            ->latest('detectada_en')
            ->limit(10)
            ->get(['tipo', 'severidad', 'titulo', 'detalle', 'que_revisar', 'detectada_en']);

        $filas = collect($alertas['alertas'])
            ->map(fn ($a) => ['origen' => 'Alerta', 'severidad' => $a['severidad'], 'titulo' => $a['titulo'], 'detalle' => $a['detalle']])
            ->merge($anomalias->map(fn (BiAnomalia $a) => [
                'origen' => 'Anomalía',
                'severidad' => mb_strtolower($a->severidad),
                'titulo' => $a->titulo,
                'detalle' => trim($a->detalle.' '.$a->que_revisar),
            ]))
            ->when(($datos['prioridad'] ?? 'todas') === 'alta', fn ($c) => $c->where('severidad', 'alta'))
            ->sortBy(fn ($f) => $f['severidad'] === 'alta' ? 0 : 1)
            ->values()
            ->all();

        return $this->resultado(
            titulo: 'Alertas y anomalías vigentes',
            calculo: 'Alertas de costo y margen de los últimos 3 meses (umbrales de config/bi.php y del semáforo de margen) más las anomalías detectadas sin revisar.',
            resumen: [
                'alertas' => $alertas['resumen']['total'],
                'alertas_altas' => $alertas['resumen']['alta'],
                'anomalias_sin_revisar' => $anomalias->count(),
            ],
            columnas: [
                ['clave' => 'origen', 'etiqueta' => 'Origen'],
                ['clave' => 'severidad', 'etiqueta' => 'Prioridad'],
                ['clave' => 'titulo', 'etiqueta' => 'Qué pasa'],
                ['clave' => 'detalle', 'etiqueta' => 'Detalle'],
            ],
            filas: $filas,
        );
    }
}
