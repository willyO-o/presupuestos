<?php

namespace App\Ai;

use RuntimeException;

/**
 * El usuario pasó el límite de preguntas por hora del asistente
 * (`config('ia.asistente.preguntas_por_hora')`).
 */
class LimiteDePreguntas extends RuntimeException
{
    public function __construct(public readonly int $segundos)
    {
        parent::__construct(sprintf(
            'Llegaste al límite de preguntas por hora. Podrás preguntar de nuevo en %d minutos.',
            max(1, (int) ceil($segundos / 60)),
        ));
    }
}
