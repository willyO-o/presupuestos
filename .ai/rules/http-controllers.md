---
paths:
  - 'app/Services/Pdf/**, app/Http/Controllers/DocumentoPdfController.php'
---

# Http Controllers

## Los PDFs del panel salen inline (vista previa); el cotizador público sigue descargando
Las rutas `*.pdf` del panel responden como VISTA PREVIA (`Content-Disposition: inline`) y el botón las abre con `target="_blank"`: el flujo real es revisar el documento antes de mandarlo al cliente. `?descargar=1` fuerza la descarga (mismo permiso y mismo scoping: por eso es un parámetro y no otra ruta). Ver `DocumentoPdfController::entregar()`.

`GeneradorPdf` devuelve una `RespuestaPdf` (implementa `Responsable`, se puede devolver tal cual desde un controlador) ya en modo `descargar()`, que es el comportamiento seguro si la respuesta termina en otro lado. `GeneradorPdf::previsualizar($pdf)` la pasa a inline. El cotizador público SIGUE descargando a propósito —el código es el único hilo del visitante para volver a contactarnos, y un PDF abierto en una pestaña se pierde al cerrarla—; hay tests que fijan los dos lados.

Otras dos cosas de `RespuestaPdf`:
- El PDF se dibuja UNA sola vez y queda memorizado: FPDF acumula páginas en su búfer y dibujar dos veces daría un documento con las páginas repetidas.
- `sinComprimir()` (para inspeccionar el contenido, lo usan los tests) hay que pedirlo ANTES de leer `contenido()`; si el documento ya se generó comprimido revienta con `LogicException` en vez de devolver un binario opaco.
