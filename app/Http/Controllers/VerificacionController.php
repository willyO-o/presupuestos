<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use Inertia\Response;

class VerificacionController extends Controller
{
    /**
     * Verificación pública de autenticidad de un presupuesto por su código
     * (mencionado en los "Alcances" del proyecto). Sin autenticación,
     * expone solo datos mínimos.
     */
    public function show(string $codigo): Response
    {
        $cotizacion = Cotizacion::query()
            ->where('codigo_verificacion', $codigo)
            ->with('cliente:id,razon_social')
            ->first(['id', 'codigo_verificacion', 'cliente_id', 'fecha', 'fecha_vencimiento', 'estado', 'total']);

        return inertia('Verificar/Show', [
            'codigo' => $codigo,
            'cotizacion' => $cotizacion ? [
                'codigo_verificacion' => $cotizacion->codigo_verificacion,
                'cliente' => $cotizacion->cliente?->razon_social,
                'fecha' => $cotizacion->fecha?->toDateString(),
                'fecha_vencimiento' => $cotizacion->fecha_vencimiento?->toDateString(),
                'estado' => $cotizacion->estado,
                'total' => (float) $cotizacion->total,
            ] : null,
        ]);
    }
}
