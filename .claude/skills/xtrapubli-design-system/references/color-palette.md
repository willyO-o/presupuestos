# Paleta de color — XtraPubli

## De donde sale cada color

| Variable de marca | Valor | Origen |
| --- | --- | --- |
| `--brand-blue` | `#1c7fc4` | Azul de la palabra **"Publi"** del logotipo y del tono medio/oscuro del degradado del isotipo (la figura del nadador/delfín). |
| `--brand-blue-light` | `#6fcbec` | Celeste del extremo claro del mismo degradado del isotipo. |
| `--brand-teal` | `#0e8b94` | Turquesa usado como color de acento/CTA en xtrapubli.com (botones, enlaces destacados). Distinto del azul del logo a propósito: da variedad sin salirse de la familia fría de marca. |
| `--brand-dark` | `#14161a` | Negro/casi-negro de la palabra **"Xtra"** del logotipo. |

Estas 4 son la **única fuente de verdad**. Viven al principio de `:root` en
[`resources/css/app.css`](../../../../resources/css/app.css). Si XtraPubli cambia de
identidad visual, se edita solo este bloque.

## Tokens semánticos (heredan de la marca)

Todo el resto del sistema usa estos tokens, nunca los `--brand-*` directamente (excepto
`--c-primary` e `--c-info`, que son alias de marca). Cada color tiene 3 variantes:

- **base** (`--c-{nombre}`): color sólido, para fondos de botones, badges, iconos activos.
- **dark** (`--c-{nombre}-dark`): tono más oscuro, reservado para estados hover/active/pressed
  o para futuros gradientes; no todas las clases lo usan hoy (la mayoría de botones sólidos
  usan `hover:opacity-90` en vez de cambiar de color), pero está disponible.
- **soft** (`--c-{nombre}-soft`): tinte pastel del color sobre fondo claro, para fondos
  suaves (`bg-soft-*`, `badge-soft-*`, iconos de stat-cards). En modo oscuro cambia
  automáticamente a un `rgba(color, 0.15–0.24)` para que siga leyéndose bien.

| Token | Base (light) | Uso previsto |
| --- | --- | --- |
| `primary` | `#1c7fc4` (= `--brand-blue`) | Color principal de marca: CTA primario, links, iconos activos, sidebar activo. |
| `secondary` | `#64748b` | Acciones secundarias / menos prominentes, gris neutro (no compite con el azul de marca). |
| `success` | `#17a673` | Confirmaciones, estados "aprobado", cifras positivas. |
| `info` | `#0e8b94` (= `--brand-teal`) | Acento de marca alterno (turquesa del sitio web), notificaciones informativas. |
| `warning` | `#f7b84b` | Alertas medias, "atención", límites cerca de superarse. |
| `danger` | `#e5484d` | Errores, "rechazado", sobreconsumo. |
| `dark` | `#14161a` (= `--brand-dark`) | Texto/fondos fuertes que deben leer como "marca", botones dark, sidebar. |
| `light` | `#f3f3f9` | Fondos neutros, botones sutiles sobre superficies oscuras. |
| `pink` | `#ee6a9f` | Color bonus fuera de la paleta core, para variedad en gráficos/listas (no es de marca). |

## Modo oscuro

`.dark` sobreescribe `page-bg`, `sidebar-bg`, `topbar-bg`, `card-bg`, los textos y todas las
variantes `-soft` (a `rgba` semitransparente). Las variantes `base` y `-dark` de cada color
semántico se mantienen iguales en ambos temas — son ya suficientemente saturadas para
funcionar sobre fondo oscuro.

## Cómo elegir color al construir UI nueva

1. ¿Es la acción principal de la pantalla (guardar, crear, confirmar)? → `primary`.
2. ¿Es una acción secundaria junto a la principal (cancelar, volver)? → `secondary` o `light`.
3. ¿Es un estado (aprobado/rechazado/pendiente/informativo)? → `success` / `danger` / `warning` / `info`.
4. ¿Necesitas que algo se lea como "marca" de forma fuerte (footer, sidebar, un badge
   destacado)? → `dark` o `primary`, nunca gris genérico de Tailwind (`gray-800`, etc.).
5. Nunca introduzcas un quinto tono de azul o un turquesa distinto "a ojo": si `primary` e
   `info` no alcanzan, es señal de que falta un token — añádelo siguiendo el patrón
   (`--c-{nombre}`, `-dark`, `-soft`) en vez de escribir un hex suelto.
