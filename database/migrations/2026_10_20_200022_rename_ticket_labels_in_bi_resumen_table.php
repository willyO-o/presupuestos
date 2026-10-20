<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los resúmenes semanales guardan sus textos al generarse (`hechos` con el
 * nombre de cada indicador, `contenido` con las recomendaciones de la
 * plantilla). Al cambiar "ticket" por "monto promedio por presupuesto" en
 * App\Services\Reporte\ResumenSemanalService, los ya generados seguían
 * diciendo "ticket".
 *
 * Solo se reemplazan las frases EXACTAS que escribía la plantilla: ni números
 * ni texto redactado por la IA. Es reversible.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const FRASES = [
        'Ticket promedio por presupuesto' => 'Monto promedio por presupuesto',
        'Priorizar oportunidades de mayor ticket (letreros, exhibidores, campañas completas).' => 'Priorizar oportunidades de mayor monto (letreros, exhibidores, campañas completas).',
        'El ticket promedio bajó.' => 'El monto promedio por presupuesto bajó.',
    ];

    public function up(): void
    {
        $this->reemplazar(self::FRASES);
    }

    public function down(): void
    {
        $this->reemplazar(array_flip(self::FRASES));
    }

    /**
     * @param  array<string, string>  $frases
     */
    private function reemplazar(array $frases): void
    {
        DB::table('bi_resumen')->orderBy('id')->each(function (object $resumen) use ($frases): void {
            $cambios = [];

            foreach (['hechos', 'contenido'] as $columna) {
                $original = json_decode($resumen->{$columna}, true);
                $nuevo = $this->recorrer($original, $frases);

                if ($nuevo !== $original) {
                    // Igual que el cast `array` del modelo: json_encode sin flags.
                    $cambios[$columna] = json_encode($nuevo);
                }
            }

            if ($cambios !== []) {
                DB::table('bi_resumen')->where('id', $resumen->id)->update($cambios);
            }
        });
    }

    /**
     * Reemplaza en cada texto del JSON (las claves no se tocan).
     *
     * @param  array<string, string>  $frases
     */
    private function recorrer(mixed $valor, array $frases): mixed
    {
        if (is_array($valor)) {
            return array_map(fn (mixed $item): mixed => $this->recorrer($item, $frases), $valor);
        }

        return is_string($valor) ? strtr($valor, $frases) : $valor;
    }
};
