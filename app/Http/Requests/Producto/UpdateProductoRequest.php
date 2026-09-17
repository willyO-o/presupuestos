<?php

namespace App\Http\Requests\Producto;

use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La ruta ya exige el permiso via middleware `can:productos.editar`; se
     * repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('productos.editar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categoria_producto_id' => ['required', 'integer', Rule::exists('categoria_producto', 'id')],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'unidad_medida' => ['required', Rule::in(Producto::UNIDADES_MEDIDA)],
            'precio_base' => ['nullable', 'numeric', 'min:0'],
            'requiere_medidas' => ['required', Rule::in(['SI', 'NO'])],
            // Lista blanca del cotizador publico (ver la migracion
            // add_cotizable_web_to_producto_table). `sometimes` y no
            // `required`: publicar un producto es una decision explicita, y
            // omitir el campo tiene que significar "no publicado", no un error
            // de validacion.
            'cotizable_web' => ['sometimes', Rule::in(['SI', 'NO'])],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
            // Se convierte a JPG antes de guardarse (ver
            // App\Services\Imagen\ConvierteImagenAJpgService), así que la
            // lista de formatos aceptados es la que esa clase sabe leer.
            'imagen' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,bmp', 'max:4096'],
        ];
    }
}
