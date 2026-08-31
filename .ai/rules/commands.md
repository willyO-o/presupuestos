---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## Command cotizaciones:vencer (2026-08-31)
`php artisan cotizaciones:vencer` marca `PENDIENTE` → `VENCIDA` cuando `fecha_vencimiento < today`. Programado a diario 00:15 en `routes/console.php` (`Schedule::command(...)`). Es el único proceso que setea `VENCIDA` (antes decía "un job de vencimiento" en `.ai/rules/cotizaciones.md`). Requiere un cron real en el server que llame `php artisan schedule:run` cada minuto. Test: VencerCotizacionesTest.
