<?php

namespace App\Http\Requests\Concerns;

use App\Models\Cliente;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Regla del cliente vinculado a una cuenta del portal (`cliente.user_id`).
 *
 * El portal scopea todo por la ficha de cliente de la cuenta
 * (ClientePortalController::clienteId): una cuenta con rol `cliente` sin ficha
 * entra y solo ve un 403. Por eso el vínculo es obligatorio con ese rol, y una
 * ficha no puede quedar repartida entre dos cuentas —la relación es 1:1—.
 */
trait ValidaClienteDelPortal
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function reglasDeClienteDelPortal(?int $usuarioId = null): array
    {
        return [
            'cliente_id' => [
                'nullable',
                'integer',
                'required_if:rol,cliente',
                Rule::exists('cliente', 'id'),
                $this->clienteSinOtraCuenta($usuarioId),
            ],
        ];
    }

    private function clienteSinOtraCuenta(?int $usuarioId): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail) use ($usuarioId): void {
            $ocupado = Cliente::query()
                ->whereKey($valor)
                ->whereNotNull('user_id')
                ->when($usuarioId, fn ($q) => $q->where('user_id', '!=', $usuarioId))
                ->exists();

            if ($ocupado) {
                $fail('Ese cliente ya tiene otra cuenta del portal.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesDeClienteDelPortal(): array
    {
        return [
            'cliente_id.required_if' => 'Elige el cliente al que pertenece esta cuenta del portal.',
        ];
    }
}
