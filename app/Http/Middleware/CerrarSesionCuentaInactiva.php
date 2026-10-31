<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca del sistema a una cuenta que se desactivó con la sesión abierta.
 *
 * `LoginRequest::authenticate()` ya rechaza el INGRESO de una cuenta
 * INACTIVO, pero eso no alcanza: quien ya estaba dentro seguía trabajando
 * hasta cerrar sesión, y con "Recuérdame" la cookie lo vuelve a autenticar
 * sin pasar nunca por el login. Desactivar a alguien que deja la empresa
 * tiene que cortar el acceso en su siguiente clic.
 */
class CerrarSesionCuentaInactiva
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->estaActivo()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(Response::HTTP_UNAUTHORIZED, 'Esta cuenta está desactivada.');
        }

        return redirect()->route('login')
            ->withErrors(['email' => 'Esta cuenta está desactivada. Contacta a un administrador.']);
    }
}
