<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('an inactive account cannot log in', function () {
    $user = User::factory()->create(['estado' => 'INACTIVO']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an account deactivated with an open session is logged out on its next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    $user->update(['estado' => 'INACTIVO']);

    $this->get(route('profile.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an inactive account gets 401 instead of a redirect on json requests', function () {
    $user = User::factory()->create(['estado' => 'INACTIVO']);

    $this->actingAs($user)
        ->getJson(route('profile.edit'))
        ->assertUnauthorized();

    $this->assertGuest();
});

test('logging out from the panel makes a full page visit to the public home page', function () {
    // El botón "Cerrar sesión" es una visita de Inertia. La portada es Blade:
    // con un redirect común, Inertia la mostraba dentro de su diálogo de
    // "respuesta no Inertia" sobre el panel en vez de navegar.
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout', [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('inicio'));

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
