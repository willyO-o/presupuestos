<?php

use App\Models\Cotizacion;

test('the public verification page shows a real cotizacion without login', function () {
    $cotizacion = Cotizacion::factory()->create(['codigo_verificacion' => 'COT-20260101-ABCDE']);

    $this->get(route('verificar', 'COT-20260101-ABCDE'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Verificar/Show')
            ->where('cotizacion.codigo_verificacion', 'COT-20260101-ABCDE')
            ->where('cotizacion.total', (float) $cotizacion->total));
});

test('an unknown code renders a not-found state', function () {
    $this->get(route('verificar', 'COT-19990101-ZZZZZ'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Verificar/Show')->where('cotizacion', null));
});
