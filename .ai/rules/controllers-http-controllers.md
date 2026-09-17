---
paths:
  - 'app/Services/Imagen/**, app/Http/Controllers/ProductoController.php, app/Http/Controllers/CotizacionController.php, app/Http/Controllers/NotaEntregaController.php'
---

# Controllers Http Controllers

## Imágenes referenciales/evidencia: siempre convertidas a JPG por ConvierteImagenAJpgService
Implementado 2026-09-16. `App\Services\Imagen\ConvierteImagenAJpgService` (GD puro, sin paquete Composer nuevo) es el ÚNICO lugar que convierte una imagen subida a JPG (calidad 82, lado mayor tope 1600px, corrige orientación EXIF, aplana transparencia PNG/GIF/WEBP sobre blanco) antes de guardarla en el disco `public`. La usan tres módulos:

- `producto.imagen` (nullable, migración `add_imagen_to_producto_table`): imagen de catálogo, subida desde `Productos/Index.vue` (modal, mismo patrón POST+`_method` que `foto` de Usuario). Accesor `imagen_url` en `Producto`.
- `cotizacion_detalle.imagen` (nullable, migración `add_imagen_to_cotizacion_detalle_table`): imagen referencial POR LÍNEA. `CotizacionController::resolverImagenLinea()` decide en orden: 1) archivo nuevo (`detalles.{i}.imagen`), 2) `usar_imagen_producto=true` → copia+re-codifica la del producto (foto histórica, como factor_complejidad/margen_aplicado — cambiar la imagen del producto después NO debe afectar presupuestos ya emitidos, ver `ConvierteImagenAJpgService::copiarDesde`), 3) `imagen_actual` (ruta que la línea ya tenía, para no perderla al reemplazar el detalle entero en cada `update`), 4) ninguna. `CotizacionController::update()`/`destroy()` limpian del disco las imágenes de línea que quedan huérfanas (diff antes/después de borrar+recrear el detalle) — sin esto cada edición acumula archivos sin referencia.
- `nota_entrega_detalle.foto_url`: ya existía; ahora pasa por el mismo servicio en vez de `->store()` directo.

Los tres Form Requests restringen `mimes` a lo que GD puede leer (`jpeg,png,jpg,gif,webp,bmp`), no el genérico `image` (que también acepta SVG, que GD no rasteriza). Para un módulo nuevo que suba imágenes, reusar este servicio — no instanciar GD a mano ni agregar Intervention/Imagick.
