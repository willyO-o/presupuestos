<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'categoria_producto_id',
    'nombre',
    'descripcion',
    'unidad_medida',
    'precio_base',
    'horas_mano_obra',
    'costo_mano_obra',
    'requiere_medidas',
    'cotizable_web',
    'modo_precio_web',
    'precio_web',
    'modo_cotizacion',
    'estado',
])]

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'producto';

    /**
     * Subconjunto del vocabulario canónico (Material::UNIDADES_MEDIDA) que
     * tiene sentido para vender: un producto no se cotiza por litro.
     * `CosteoProductoService::driver()` depende de estos valores.
     */
    public const UNIDADES_MEDIDA = ['M2', 'METRO_LINEAL', 'UNIDAD'];

    /**
     * En qué cotizador aparece el producto: GENERAL (lonas, letreros,
     * impresión) o MUEBLE (plantillas del cotizador de muebles, cuya receta
     * predefine los materiales). Coincide con Cotizacion::TIPOS: una
     * cotización solo admite productos de su mismo tipo.
     */
    public const MODOS_COTIZACION = ['GENERAL', 'MUEBLE'];

    /**
     * De dónde sale el precio que ve el visitante del cotizador web:
     * MATERIALES (el costeo de ventas + margen), PRECIO_FIJO (`precio_web`
     * por unidad de medida) o RANGOS (precio por m² según el área, tabla
     * `producto_precio_web`; solo productos por m²).
     */
    public const MODOS_PRECIO_WEB = ['MATERIALES', 'PRECIO_FIJO', 'RANGOS'];

    /**
     * @var list<string>
     */
    protected $appends = ['imagen_url'];

    public function categoriaProducto(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class);
    }

    /**
     * Receta de costo (BOM): materiales que consume este producto, cada
     * uno con un factor fijo o una fórmula dinámica (ver
     * App\Models\ProductoMaterial::esDinamica()).
     */
    public function productoMateriales(): HasMany
    {
        return $this->hasMany(ProductoMaterial::class);
    }

    /**
     * Rangos de área del precio web (modo RANGOS), del más chico al más
     * grande; el rango sin tope (`area_hasta` null) va al final.
     */
    public function preciosWeb(): HasMany
    {
        return $this->hasMany(ProductoPrecioWeb::class)
            ->orderByRaw('area_hasta IS NULL')
            ->orderBy('area_hasta');
    }

    /**
     * Productos ofrecidos en el cotizador público (`/cotizador`): lista
     * blanca explícita (`cotizable_web`), activos y —lo importante— CON
     * algo de donde sacar el precio según su `modo_precio_web`: receta o
     * costo base (MATERIALES), `precio_web` (PRECIO_FIJO) o rangos (RANGOS).
     * Sin eso el costo daría 0 y el motor devolvería un precio de cero:
     * publicar eso sería peor que no publicar nada.
     *
     * Ver App\Services\Cotizador\CotizadorPublicoService.
     */
    #[Scope]
    protected function cotizableWeb(Builder $query): void
    {
        $query->where('cotizable_web', 'SI')
            ->where('estado', 'ACTIVO')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('modo_precio_web', 'MATERIALES')
                    ->where(fn (Builder $query) => $query
                        ->whereHas('productoMateriales')
                        ->orWhere('precio_base', '>', 0)))
                ->orWhere(fn (Builder $query) => $query
                    ->where('modo_precio_web', 'PRECIO_FIJO')
                    ->where('precio_web', '>', 0))
                ->orWhere(fn (Builder $query) => $query
                    ->where('modo_precio_web', 'RANGOS')
                    ->whereHas('preciosWeb')));
    }

    /**
     * Productos que se ofrecen en un cotizador (GENERAL o MUEBLE).
     */
    #[Scope]
    protected function modoCotizacion(Builder $query, string $modo): void
    {
        $query->where('modo_cotizacion', $modo);
    }

    /**
     * true si es una plantilla del cotizador de muebles.
     */
    public function esMueble(): bool
    {
        return $this->modo_cotizacion === 'MUEBLE';
    }

    /**
     * Filtra por coincidencia parcial en nombre. Sin término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where('nombre', 'like', "%{$term}%"));
    }

    /**
     * Filtra por categoría de producto exacta. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function categoria(Builder $query, ?string $categoriaProductoId): void
    {
        $query->when($categoriaProductoId, fn (Builder $query) => $query->where('categoria_producto_id', $categoriaProductoId));
    }

    /**
     * Filtra por estado exacto (ACTIVO/INACTIVO). Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function estado(Builder $query, ?string $estado): void
    {
        $query->when($estado, fn (Builder $query) => $query->where('estado', $estado));
    }

    /**
     * URL pública de la imagen de referencia (disco `public`), ya convertida
     * a JPG (ver App\Services\Imagen\ConvierteImagenAJpgService). Se puede
     * copiar como foto histórica a una línea de cotización — ver
     * CotizacionController::resolverImagenLinea.
     */
    protected function imagenUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->imagen ? Storage::disk('public')->url($this->imagen) : null,
        );
    }
}
