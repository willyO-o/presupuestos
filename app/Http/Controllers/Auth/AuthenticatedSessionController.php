<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Seguridad\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(RecaptchaService $recaptcha): Response
    {
        return inertia('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'recaptchaSiteKey' => $recaptcha->siteKey(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // El rol `cliente` va a su portal, no al panel interno.
        if ($request->user()->hasRole('cliente')) {
            return redirect()->route('portal.cotizaciones');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    /**
     * Cierra la sesión y lleva a la portada del sitio público.
     *
     * La portada es una vista Blade, no una página de Inertia: con un
     * `redirect('/')` normal, Inertia recibía HTML en su petición XHR y lo
     * mostraba dentro de su diálogo de "respuesta no Inertia" (la web
     * superpuesta sobre el panel). `location()` responde 409 con
     * `X-Inertia-Location` y el navegador navega de verdad; ante un POST
     * común (sin Inertia) devuelve una redirección normal.
     */
    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return inertia()->location(route('inicio'));
    }
}
