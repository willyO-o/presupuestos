---
paths:
  - 'app/Services/Cotizador/**, app/Http/Controllers/CotizadorPublicoController.php, app/Http/Requests/Cotizador/**, resources/views/publico/cotizador.blade.php, config/cotizador.php'
---

# Publico

## Cotizador público (/cotizador): ya construido (2026-09-09)
Tercera página Blade del sitio público: el visitante arma una lista y recibe un precio APROXIMADO más un código `WEB-Ymd-XXXXX` con vigencia. No reimplementar.

- **No es un motor nuevo**: `CotizadorPublicoService::estimar()` recorre la misma cadena que ventas — receta/BOM → `CosteoProductoService` → `MotorMargenService::calcularCon()` → IVA vía `evaluar()`. No calcular precios en otro lado (ni en `resources/js/publico.js`, que solo pinta lo que responde el servidor).
- **Lista blanca**: scope `Producto::cotizableWeb()` (columna `cotizable_web` + ACTIVO + `whereHas('productoMateriales')`). Sin BOM el costo daría 0 y el motor un precio de 0 en VERDE. Se administra desde Productos → columna "Web". Un producto cuya fórmula usa `profundo` NO se puede publicar: el formulario solo pide ancho y alto.
- **Nada interno sale por el endpoint público**: ni costo, margen, factor, IT, IUE, utilidad ni semáforo. Hay un test que falla si se filtran (`CotizadorPublicoTest`, "no filtra costos ni rentabilidad").
- **Tabla aparte** `cotizacion_publica`, no una fila de `cotizacion`: `cotizacion.cliente_id` es NOT NULL y un visitante anónimo no es un cliente; además un endpoint público recibe bots y eso no puede contaminar el BI. Al emitir el presupuesto formal se enlaza con `cotizacion_id` y queda CONVERTIDA.
- **Vigencia y holgura**: `config/cotizador.php` (`vigencia_dias`, `holgura`), pero cada fila guarda su propia `vigencia_dias`/`fecha_vencimiento`/`holgura` — cambiar el parámetro no puede alterar una estimación ya entregada.
- **Defensas** (todas con test): rate limiters `cotizador-{calcular,guardar,consultar}` en AppServiceProvider, honeypot `sitio_web`, tiempo mínimo con marca en SESIÓN (no en input oculto), topes de líneas/medidas/cantidad, y el precio que manda el navegador se ignora siempre.
- Bandeja para ventas: `/solicitudes-web` (`SolicitudWebController`, permisos `solicitudes-web.ver|gestionar`). CONVERTIDA no se pone a mano.
- Advertencia operativa: el precio publicado depende de `material.precio_unitario`, que debe estar POR UNIDAD DE MEDIDA (Bs/m²), no por presentación/rollo.
