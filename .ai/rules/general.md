---
paths:
  - '**/*'
---

# General

## Registrar cada cambio en bitacora/CHANGELOG.md
Pedido explícito del usuario (2026-09-16): cada vez que se termina un cambio de cierto tamaño (una funcionalidad, un refactor, una corrección no trivial) — no un typo o un ajuste cosmético de una línea — hay que agregar una entrada nueva en `bitacora/CHANGELOG.md` ANTES de dar el trabajo por terminado.

Formato de la entrada (ver el archivo para ejemplos): encabezado `## AAAA-MM-DD — título corto`, y adentro "Qué cambió", "Por qué" y "Cómo" en 3-6 líneas cada uno — no un resumen de una frase ni un volcado del diff. Entradas nuevas van ARRIBA (orden cronológico inverso). No usar la bitácora para reemplazar los mensajes de commit de git ni para detalle línea por línea: es el resumen legible para que alguien (o el propio Claude en otra sesión) entienda rápido qué pasó y por qué, sin leer diffs.

El glob `**/*` es intencional: la regla aplica sin importar qué parte del código se haya tocado (backend, frontend, migraciones, config).
