---
paths:
  - 'app/Services/Pdf/**, app/Http/Controllers/DocumentoPdfController.php'
---

# Css

## Todos los PDFs salen de GeneradorPdf con FPDF: no instanciar FPDF en otro lado
`App\Services\Pdf\GeneradorPdf` es el ÚNICO lugar donde se genera un PDF (migrado el 2026-09-09 de `spatie/laravel-pdf`+Browsershot a **`setasign/fpdf` 1.9**, PHP puro, porque el despliegue es en hosting compartido: no hay Node ni se puede correr un navegador headless). Seis documentos: `cotizacion()`, `pedido()` (orden de trabajo), `notaEntrega()`, `compra()`, `ordenCompraCliente()` y `cotizacionPublica()`.

- Cada documento es una clase en `app/Services/Pdf/Documentos/` que extiende `Documento` y solo declara `identificacion()` (bloque derecho del membrete) y `cuerpo()`. **Agregar un documento = un método en `GeneradorPdf` + una clase allá**; nunca `new FPDF` desde un controlador, o el membrete deja de estar en un solo sitio. Hay un test que lo fija ("todos los documentos comparten formato, pie numerado y membrete").
- **Toda la maqueta vive en `Documentos\Documento`**: membrete (`Header()`), pie numerado (`Footer()` + `AliasNbPages`), `bloqueDatos()`, `tabla()`, `totales()`, `recuadro()`, `seccion()`, `firmas()`, `notaLegal()`. NO hay CSS ni vistas Blade — FPDF dibuja por coordenadas. Los colores son los tokens de marca de `app.css` pasados a RGB.
- **TRAMPA — encoding**: las fuentes del núcleo de FPDF son cp1252, no UTF-8. Todo string que se dibuje pasa por `Documento::t()`; sin eso la `ñ` sale como `Ã±`. FPDF 1.9 acepta UTF-8 SOLO en las propiedades del documento (`SetTitle`/`SetAuthor` con el 2º argumento en `true`) — pasarles texto ya convertido da un aviso de iconv. Test: "los acentos y simbolos del castellano se imprimen, no salen como basura". Ojo también con concatenar fuera de `t()`: un `'·'` escrito en el fuente PHP es UTF-8 y saldría `Â·`.
- **TRAMPA — tablas**: FPDF no sabe nada de tablas. `tabla()` mide cada fila ANTES de dibujarla, decide si entra en lo que queda de página y, si no, salta y repite la cabecera. No reemplazarlo por `MultiCell` suelto: una fila se partiría entre dos páginas. Test: "una cotizacion larga pagina sola y repite el membrete en cada hoja".
- **TRAMPA — recuadros**: el fondo se dibuja ANTES que el texto, así que hay que medir el contenido primero. Por eso `flujo()` tiene modo `dibujar: false` y los bloques del recuadro son declarativos (`titulo`, `nota`, `lista`, `rotulo`, `rango`).
- Las imágenes se leen del disco POR RUTA (ver `NotaEntregaDetalle::fotoRuta()`), nunca por URL ni data URI. Si el archivo falta, la celda cae a un guion y el documento se emite igual.
- **La cotización NO imprime costo, margen, factor, IT, IUE, utilidad ni semáforo** aunque estén en el modelo: sale por correo al cliente. Test: "el PDF de la cotizacion NO filtra costos ni rentabilidad".
- Permisos: los mismos `.ver` del documento en pantalla (`DocumentoPdfController`). El PDF del pedido repite el scoping por sucursal de `PedidoController::puedeVer`.
- Los tests leen el PDF DE VERDAD (`sinComprimir()` + los operadores `Td`/`Tj`, ver `textoDelPdf()` en `GeneradorPdfTest`). No hace falta fake ni mock: generar los seis documentos cuesta ~1 s.
- `cotizacionPublica()` la dispara un anónimo. Es barata comparada con levantar un navegador, pero sus topes (rate limiter + `descargas_maximas`) siguen sin ser opcionales.
