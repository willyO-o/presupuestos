<?php

namespace App\Services\Seguridad;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifica contra Google el token de reCAPTCHA v2 que manda el formulario
 * de login. Sin dependencias extra: es una sola llamada HTTP a `siteverify`.
 *
 * Falla cerrado: si Google no responde, el intento se rechaza (y queda en el
 * log) en vez de dejar pasar el login sin verificar.
 */
class RecaptchaService
{
    private const URL_VERIFICACION = 'https://www.google.com/recaptcha/api/siteverify';

    private const TIMEOUT_SEGUNDOS = 5;

    /**
     * El captcha se exige solo si está habilitado y tiene ambas claves: con
     * una sola el widget no podría mostrarse o el token no podría verificarse,
     * y nadie podría iniciar sesión.
     */
    public function estaActivo(): bool
    {
        return (bool) config('services.recaptcha.enabled')
            && filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    /**
     * Clave pública para el widget, o null si el captcha no se exige.
     */
    public function siteKey(): ?string
    {
        return $this->estaActivo() ? config('services.recaptcha.site_key') : null;
    }

    public function verificar(?string $token, ?string $ip = null): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $respuesta = Http::asForm()
                ->timeout(self::TIMEOUT_SEGUNDOS)
                ->post(self::URL_VERIFICACION, [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('reCAPTCHA: no se pudo contactar a Google.', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $respuesta->successful()) {
            Log::warning('reCAPTCHA: respuesta HTTP inesperada.', ['status' => $respuesta->status()]);

            return false;
        }

        return $respuesta->json('success') === true;
    }
}
