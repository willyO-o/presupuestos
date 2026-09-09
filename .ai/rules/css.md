---
paths:
  - 'app/Services/Pdf/**, resources/views/pdf/**, app/Http/Controllers/DocumentoPdfController.php, resources/css/pdf.css'
---

# Css

## Todos los PDFs salen de GeneradorPdf: no llamar a Pdf:: desde otro lado
`App\Services\Pdf\GeneradorPdf` es el ÚNICO lugar donde se genera un PDF (2026-09-09, `spatie/laravel-pdf` + Browsershot). Seis documentos: `cotizacion()`, `pedido()` (orden de trabajo), `notaEntrega()`, `compra()`, `ordenCompraCliente()` y `cotizacionPublica()`.

- Todos pasan por el privado `documento()`, que fija A4, márgenes, pie numerado, metadatos y nombre de archivo. **Agregar un documento = un método público + una vista en `resources/views/pdf/`**; nunca `Pdf::view(...)` desde un controlador, o el membrete deja de estar en un solo sitio. Hay un test que lo fija ("todos los documentos comparten formato, pie numerado y membrete").
- Cada método devuelve un `PdfBuilder` (ya con `->download()`), no un archivo: el que llama puede `->inline()`, `->save()` o adjuntarlo a un correo sin duplicar la construcción.
- Las vistas usan `@extends('pdf.layout')` (herencia, NO componente con slots) para que $empresa/$titulo/$estilos/$generadoEn lleguen solos.
- **El CSS (`resources/css/pdf.css`) es plano y se incrusta con file_get_contents, sin Vite**: Browsershot le pasa a Chromium el HTML como cadena, así que un `<link>` obligaría al servidor a hacerse una petición HTTP a sí mismo. Por lo mismo, las imágenes van como data URI (ver `NotaEntregaDetalle::fotoIncrustada()`), nunca por URL: saldrían en blanco.
- El pie (`pdf/pie.blade.php`) lleva estilos INLINE: Chromium no le aplica el `<style>` de la página y su font-size por defecto es 0.
- **La cotización NO imprime costo, margen, factor, IT, IUE, utilidad ni semáforo** aunque estén en el modelo: sale por correo al cliente. Test: "el PDF de la cotizacion NO filtra costos ni rentabilidad".
- Permisos: los mismos `.ver` del documento en pantalla (`DocumentoPdfController`). El PDF del pedido repite el scoping por sucursal de `PedidoController::puedeVer`.
- **Requiere Node + Chromium de Puppeteer EN EL SERVIDOR** (ver README). Los tests usan `Pdf::fake()` o `->getHtml()` (solo renderiza Blade) para no depender de eso.
- `cotizacionPublica()` la dispara un anónimo y levanta un Chromium: es la operación más cara del sitio. Sus topes (rate limiter + `descargas_maximas`) no son opcionales.
