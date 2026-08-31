---
paths:
  - '.claude/skills/xtrapubli-design-system/references/**'
---

# References

## Cambios de esquema tras construir los módulos operativos (2026-08-31)
Al implementar Compras→Pedidos→Documentos→Pagos→Seguridad→BI→Portal (ver `.ai/rules/controllers.md` y `.ai/rules/reporte.md`) se hicieron 2 cambios de esquema respecto a `schema.json`/`database-design.md`: (1) `cotizacion.empleado_id` ahora es **nullable** (`nullOnDelete`) — el portal del cliente crea cotizaciones sin vendedor; migración `2026_08_31_142604_make_empleado_id_nullable_on_cotizacion_table`. (2) `config/acl.php` ganó `compras.editar`/`compras.eliminar`. Además: `cliente.user_id` (que ya existía en la migración) ahora está en `#[Fillable]` de `App\Models\Cliente` y se usa para el portal. Nota de drift no corregido: `database-design.md §4` dice `empleado.nombre_completo` pero el esquema real usa `nombres`/`paterno`/`materno` + accesor `nombre_completo` (así desde el 2º esquema, no tocar).
