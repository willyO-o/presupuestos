---
paths:
  - 'app/Http/Controllers/CotizadorPublicoController.php, resources/views/publico/cotizacion-documento.blade.php, config/cotizador.php'
---

# Controllers Views Publico

## Documento imprimible del cotizador y topes de descarga
`GET /cotizador/{codigo}/documento` sirve la estimación con la forma del presupuesto del panel (Pages/Cotizaciones/Show.vue + app.css §22): encabezado empresa/número, datos en dos columnas, tabla de detalle y escalera de totales.

- **No hay librería de PDF y no debe agregarse por esto.** Igual que el panel, se imprime con `window.print()` y el visitante elige "Guardar como PDF". Los estilos de papel están en `publico.css` §"documento imprimible"; el auto-print, en el bloque `[data-documento-imprimible]` de `publico.js`.
- La vista NO usa `x-publico.layout`: un documento no lleva menú, pie ni botón flotante. Es una página suelta con su propio `<head>`.
- **Dos frenos y hacen falta los dos**: el rate limiter `cotizador-descargar` (por IP) y el tope por FILA `CotizacionPublica::puedeDescargar()` contra `config('cotizador.descargas_maximas')`. El limiter se renueva solo, así que sin el tope por fila un código válido alcanza para pedir el documento indefinidamente.
- `descargado_en` guarda la PRIMERA emisión y es lo que hace desaparecer el botón "Descargar" de `/cotizador/{codigo}`; `descargas` cuenta todas y es lo que aplica el tope. Reimprimir desde el botón del propio documento es client-side y no gasta emisiones — es la salida para quien cancela el diálogo.
- `cotizador-guardar` lleva TRES límites simultáneos (hora/IP, día/IP y un techo global por hora). Los `by()` van prefijados: varios límites en un mismo limiter necesitan claves distintas o comparten contador. El global es el único que frena una avalancha repartida entre muchas IP.
