---
paths:
  - 'app/Services/Pdf/**, app/Http/Controllers/DocumentoPdfController.php'
---

# Http Controllers

## Los PDFs del panel salen inline (vista previa); previsualizar() debe conservar el nombre
Las rutas `*.pdf` del panel responden como VISTA PREVIA (`Content-Disposition: inline`) y el botón las abre con `target="_blank"`: el flujo real es revisar el documento antes de mandarlo al cliente. `?descargar=1` fuerza la descarga (mismo permiso y mismo scoping: por eso es un parámetro y no otra ruta). Ver `DocumentoPdfController::entregar()`.

TRAMPA de la librería: `PdfBuilder::inline()` SIN argumento llama a `name('')` y el archivo pasa a llamarse ".pdf", perdiendo el nombre que armó `GeneradorPdf::documento()` — que es justo el que ve el usuario si guarda desde el visor. Por eso existe `GeneradorPdf::previsualizar($pdf)`, que hace `inline($pdf->downloadName)`. Nunca llamar `->inline()` pelado.

Los métodos de `GeneradorPdf` siguen devolviendo `->download()` por defecto (comportamiento seguro si el builder termina en otro lado): el cotizador público SIGUE descargando, y hay tests que fijan ambos lados.
