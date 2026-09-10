<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Reglas de validación del alcance por sucursal para los Form Requests.
 *
 * Acotar solo la LECTURA deja la mitad del agujero abierto: el desplegable de
 * sucursales de un vendedor puede listar únicamente la suya y el navegador
 * mandar igual el `sucursal_id` de otra a mano. Un `Rule::exists` no alcanza —
 * comprueba que la sucursal exista, no que sea suya.
 *
 * Las dos reglas preguntan al MISMO sitio que el listado
 * (`App\Models\Concerns\TieneAlcanceSucursal` / `AcotaPorSucursal`), no a una
 * comparación aparte: si la validación y el scope pudieran discrepar,
 * aparecería el clásico "puedo crearlo pero después no puedo abrirlo".
 *
 * Se devuelven closures y no una clase de regla porque es lo que ya hace el
 * proyecto (ver la regla de `expresion` en StoreFormulaRequest).
 */
trait ValidaAlcanceSucursal
{
    /**
     * El `sucursal_id` enviado tiene que ser una de las que el usuario
     * administra. Va DESPUÉS de `Rule::exists`, para no contradecir el mensaje
     * de "no existe" con uno de permisos.
     */
    protected function sucursalAdministrada(?string $modulo = null): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail) use ($modulo): void {
            if (! $this->user()->puedeVerSucursal((int) $valor, $modulo)) {
                $fail('No administras esa sucursal.');
            }
        };
    }

    /**
     * La clave foránea apunta a un registro que el usuario puede ver.
     *
     * Para los documentos que cuelgan de un pedido o una cotización: sin esto
     * se podría registrar un pago, emitir una nota de entrega o convertir en
     * pedido algo de otra sucursal mandando su id por POST.
     *
     * @param  class-string<Model>  $modelo
     */
    protected function registroVisible(string $modelo, string $mensaje): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail) use ($modelo, $mensaje): void {
            $registro = $modelo::find($valor);

            // Si no existe, que lo diga la regla `exists`: acá solo interesa el
            // alcance. Un `find` nulo no es asunto de esta regla.
            if ($registro !== null && ! $registro->esVisiblePara($this->user())) {
                $fail($mensaje);
            }
        };
    }
}
