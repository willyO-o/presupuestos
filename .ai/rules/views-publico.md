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
