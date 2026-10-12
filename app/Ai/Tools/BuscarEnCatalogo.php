<?php

namespace App\Ai\Tools;

use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\FamiliaProducto;
use App\Models\Producto;
use App\Models\Sucursal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Stringable;

/**
 * Traduce lo que escribe el usuario ("letras 3D", "la sucursal del centro",
 * "Distribuidora Andina") a los ids reales de familias, categorías,
 * productos, sucursales, vendedores y clientes.
 *
 * Es el primer paso de casi toda pregunta: las demás herramientas filtran
 * por id. Si hay varias coincidencias el modelo debe preguntar cuál.
 */
class BuscarEnCatalogo extends HerramientaBi
{
    private const LIMITE = 6;

    public function description(): Stringable|string
    {
        return 'Busca por texto en el catálogo (familias de producto, categorías, productos, sucursales, vendedores y clientes) y devuelve sus ids. '
            .'Úsala ANTES de filtrar por cualquiera de ellos. Si hay varias coincidencias razonables, pregunta al usuario cuál quiere.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'texto' => $schema->string()->description('Lo que el usuario nombró, por ejemplo "letras 3D" o "Santa Cruz".')->required(),
        ];
    }

    protected function reglas(): array
    {
        return ['texto' => ['required', 'string', 'min:2', 'max:80']];
    }

    protected function ejecutar(array $datos): array
    {
        $terminos = collect(preg_split('/\s+/', trim($datos['texto'])))->filter(fn ($t) => mb_strlen($t) >= 2)->values();

        $buscar = function ($query, string $columna) use ($terminos) {
            foreach ($terminos as $termino) {
                $query->where($columna, 'like', '%'.$termino.'%');
            }

            return $query;
        };

        $sucursalesVisibles = $this->usuario->sucursalesVisibles();

        $coincidencias = [
            'familias' => $buscar(FamiliaProducto::query(), 'nombre')->limit(self::LIMITE)->get(['id', 'nombre'])
                ->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre]),
            'categorias' => $buscar(CategoriaProducto::query(), 'nombre')->with('familia:id,nombre')->limit(self::LIMITE)->get(['id', 'nombre', 'familia_producto_id'])
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'familia' => $c->familia?->nombre]),
            'productos' => $buscar(Producto::query(), 'nombre')->where('estado', 'ACTIVO')->with('categoriaProducto:id,nombre')->limit(self::LIMITE)->get(['id', 'nombre', 'categoria_producto_id'])
                ->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'categoria' => $p->categoriaProducto?->nombre]),
            'sucursales' => $buscar(Sucursal::query(), 'nombre')
                ->when($sucursalesVisibles !== null, fn ($q) => $q->whereIn('id', $sucursalesVisibles))
                ->limit(self::LIMITE)->get(['id', 'nombre', 'ciudad'])
                ->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'ciudad' => $s->ciudad]),
            'vendedores' => $this->vendedores($terminos),
            'clientes' => $buscar(Cliente::query(), 'razon_social')->limit(self::LIMITE)->get(['id', 'razon_social'])
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->razon_social]),
        ];

        $coincidencias = collect($coincidencias)->map(fn ($lista) => collect($lista)->values()->all())->filter()->all();

        $filas = collect($coincidencias)->flatMap(fn ($lista, $tipo) => collect($lista)->map(fn ($item) => [
            'tipo' => $tipo,
            'id' => $item['id'],
            'nombre' => $item['nombre'],
        ]))->values()->all();

        return $this->resultado(
            titulo: 'Coincidencias para "'.$datos['texto'].'"',
            calculo: 'Búsqueda por nombre en familias, categorías, productos activos, sucursales visibles para ti, vendedores y clientes.',
            resumen: ['coincidencias' => $coincidencias, 'total' => count($filas)],
            columnas: [
                ['clave' => 'tipo', 'etiqueta' => 'Tipo'],
                ['clave' => 'nombre', 'etiqueta' => 'Nombre'],
            ],
            filas: $filas,
        );
    }

    /**
     * Busca por nombre o apellido; vendedores visibles según la sucursal.
     *
     * @param  Collection<int, string>  $terminos
     * @return Collection<int, array{id: int, nombre: string}>
     */
    private function vendedores(Collection $terminos): Collection
    {
        return Empleado::query()
            ->visiblePara($this->usuario)
            ->where('cargo', 'Vendedor')
            ->where(function ($q) use ($terminos) {
                foreach ($terminos as $termino) {
                    $q->orWhere('nombres', 'like', "%{$termino}%")->orWhere('paterno', 'like', "%{$termino}%");
                }
            })
            ->limit(self::LIMITE)
            ->get(['id', 'nombres', 'paterno'])
            ->map(fn ($e) => ['id' => $e->id, 'nombre' => trim($e->nombres.' '.$e->paterno)]);
    }
}
