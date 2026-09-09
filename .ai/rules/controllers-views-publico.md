---
paths:
  - 'app/Http/Controllers/CotizadorPublicoController.php, config/cotizador.php'
---

# Controllers Views Publico

## Documento del cotizador y topes de descarga
`GET /cotizador/{codigo}/documento` sirve la estimación como PDF, con la misma forma que el presupuesto formal pero rotulada ESTIMACIÓN.

- **Lo genera `App\Services\Pdf\GeneradorPdf::cotizacionPublica()`, como todos los documentos del sistema** (FPDF; ver `.ai/rules/css.md`). No hay vista Blade imprimible ni `window.print()`: eso existió hasta el 2026-09-09 y se quitó junto con `publico/cotizacion-documento.blade.php`, su bloque de `publico.css` y el `[data-documento-imprimible]` de `publico.js`. No reintroducirlo.
- **Al visitante se le BAJA el archivo** (`attachment`), a diferencia del panel, que lo abre como vista previa: el código es su único hilo para volver a contactarnos y un PDF abierto en una pestaña se pierde al cerrarla.
- **Dos frenos y hacen falta los dos**: el rate limiter `cotizador-descargar` (por IP) y el tope por FILA `CotizacionPublica::puedeDescargar()` contra `config('cotizador.descargas_maximas')`. El limiter se renueva solo, así que sin el tope por fila un código válido alcanza para pedir el documento indefinidamente.
- `descargado_en` guarda la PRIMERA emisión y es lo que hace desaparecer el botón "Descargar" de `/cotizador/{codigo}`; `descargas` cuenta todas y es lo que aplica el tope.
- `cotizador-guardar` lleva TRES límites simultáneos (hora/IP, día/IP y un techo global por hora). Los `by()` van prefijados: varios límites en un mismo limiter necesitan claves distintas o comparten contador. El global es el único que frena una avalancha repartida entre muchas IP.
