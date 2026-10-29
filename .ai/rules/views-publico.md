---
paths:
  - 'resources/views/errors/**, resources/views/components/publico/**, resources/views/publico/**'
---

# Views Publico

## Páginas de error y layout público: props indexable/conJs
Todos los errores HTTP usan el layout del sitio público: `resources/views/errors/{403,404,419,429,500,503,4xx,5xx}.blade.php` son de una línea y delegan en `<x-publico.error :codigo="..." />`, que tiene el texto de cada código. Antes de esto un `abort(404)` (p.ej. `/proyectos?categoria=inventada`) devolvía la pantalla gris de Laravel, sin salida al sitio. Los fallbacks `4xx`/`5xx` NO cubren 404/500/503: Laravel les da trato dedicado y por eso tienen archivo propio.

`x-publico.layout` tiene dos props que hay que usar bien:
- `indexable` (default true) → false en cualquier página que no sea contenido público: errores y la estimación de `/cotizador/{codigo}`, que lleva nombre y teléfono de una persona.
- `conJs` (default false) → true SOLO en el cotizador. Es una única llamada a `@vite` con las dos entradas; dos llamadas separadas inyectan el cliente de HMR dos veces en desarrollo. La portada y la galería no cargan ni un byte de JavaScript, y hay un test que lo fija.

Al agregar una vista pública nueva, sumá su ruta al glob `content` de `tailwind.publico.config.js` o Tailwind purga sus clases.

## El sitio público replica xtrapubli.com (2026-10-29)
La portada, la galería, la cabecera, el bloque de contacto (`cta-contacto`) y el pie son copia de xtrapubli.com, el sitio en GoHighLevel de la empresa (pedido del usuario: "100 % igual"). Antes de cambiar algo visual, compararlo con el original.

- **Imágenes**: todas descargadas del original. Las del diseño están en `public/img/publico/sitio/` y las de la galería en `public/img/publico/proyectos/{slug}.jpg`, las 40 fotos reales en el orden del original. No volver a poner fotos de ejemplo o generadas: las 20 anteriores lo eran y se reemplazaron.
- **Colores**: `sitio.*` en `tailwind.publico.config.js` son los valores exactos del CSS del original (#083791, #006db1, #37ca37, #f6ad55, #cbd5e0, #ff5e14, sombra #707070). `marca.*` queda para cotizador, seguimiento y errores. Las fuentes son Montserrat en los títulos y Barlow en el texto.
- **Clases** `.sitio-*` en `publico.css`:
  - la tarjeta con sombra dura `2px 2px 2px`;
  - el botón verde, con "elevate" al pasar el mouse;
  - el botón naranja, que se mece;
  - el banner, con `<img>` fija recortada por `clip-path` (es el `background-attachment: fixed` del original, sin style en línea);
  - la galería, en columnas.
- **Diferencias deliberadas** (no "corregirlas" hacia el original):
  - el menú suma Cotizador, Mi pedido y Acceder;
  - el pie suma el NIT, que el usuario pidió publicar el 2026-10-24;
  - un solo `<h1>` por página;
  - la ortografía va corregida;
  - el ícono de reproducir del banner no enlaza a nada, igual que en el original;
  - no hay botón flotante de WhatsApp, porque el original no lo tiene.
- La cabecera ya **no es fija**. Si algo se pega arriba con `sticky`, usar `top-6`, no la altura de la cabecera.
