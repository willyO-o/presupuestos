<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const URL_SITEVERIFY = 'https://www.google.com/recaptcha/api/siteverify';

beforeEach(function () {
    config([
        'services.recaptcha.enabled' => true,
        'services.recaptcha.site_key' => 'clave-publica',
        'services.recaptcha.secret_key' => 'clave-secreta',
    ]);
});

test('el login entrega la clave pública del captcha cuando está activo', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('recaptchaSiteKey', 'clave-publica'));
});

test('sin alguna de las claves el captcha no se exige', function (string $claveFaltante) {
    config(["services.recaptcha.{$claveFaltante}" => null]);
    Http::preventStrayRequests();
    $user = User::factory()->create();

    $this->get('/login')->assertInertia(fn ($page) => $page->where('recaptchaSiteKey', null));

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticated();
})->with(['site_key', 'secret_key']);

test('deshabilitado no se exige captcha', function () {
    config(['services.recaptcha.enabled' => false]);
    Http::preventStrayRequests();
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticated();
});

test('sin token no se intenta el login ni se llama a Google', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['captcha' => 'Marca la casilla "No soy un robot" para continuar.']);

    $this->assertGuest();
});

test('un token que Google rechaza no inicia sesión', function () {
    Http::fake([URL_SITEVERIFY => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'captcha' => 'token-falso'])
        ->assertSessionHasErrors('captcha');

    $this->assertGuest();
});

test('un token válido inicia sesión y se verifica con la clave secreta', function () {
    Http::fake([URL_SITEVERIFY => Http::response(['success' => true])]);
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'captcha' => 'token-bueno'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    Http::assertSent(fn (Request $request) => $request->url() === URL_SITEVERIFY
        && $request['secret'] === 'clave-secreta'
        && $request['response'] === 'token-bueno');
});

test('captcha válido con contraseña incorrecta sigue sin entrar', function () {
    Http::fake([URL_SITEVERIFY => Http::response(['success' => true])]);
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta', 'captcha' => 'token-bueno'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('si Google no responde el login se rechaza', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'captcha' => 'token-bueno'])
        ->assertSessionHasErrors('captcha');

    $this->assertGuest();
});

test('un error HTTP de Google rechaza el login', function () {
    Http::fake([URL_SITEVERIFY => Http::response('', 500)]);
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'captcha' => 'token-bueno'])
        ->assertSessionHasErrors('captcha');

    $this->assertGuest();
});
