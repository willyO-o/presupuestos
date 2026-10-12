---
paths:
  - 'app/Ai/**, config/ia.php, app/Http/Controllers/AsistenteBiController.php, app/Http/Controllers/BiAutomatizacionController.php, resources/js/Components/Layout/AsistenteBi.vue'
---

# IA del BI

## IA del BI: los números los calcula el sistema; toda llamada pasa por ServicioIa (2026-10-12)
SDK oficial `laravel/ai` (v1.2). Toda llamada a un modelo pasa por `App\Ai\ServicioIa::ejecutar()`: interruptor `IA_HABILITADA`, salta proveedores sin API key, tope mensual (`config/ia.php`, USD 20, suma de `ia_uso.costo_usd` del mes en hora La Paz), failover Gemini → Claude (cadena por funcionalidad en `config/ia.php`), registro en `ia_uso`. Nunca invocar un agente directo. Sin IA, resumen y anomalías publican su PLANTILLA; el asistente muestra el motivo (`IaNoDisponible`).
- La IA nunca calcula cifras: redacta hechos calculados en PHP o elige herramientas (`App\Ai\Tools\*`, cerradas, extienden `HerramientaBi`). El texto redactado se acepta solo si `Seguridad\VerificadorCifras` encuentra cada número en los hechos.
- Barreras de datos: `ConsultaSoloLectura` (cambia la conexión por defecto a `mariadb_lectura` con `SET SESSION TRANSACTION READ ONLY` + guardia `beforeExecuting` que solo deja SELECT/WITH/SHOW; el guardia se registra por INSTANCIA de conexión, WeakMap, no por nombre), `MinimizadorDatos` (quita password/token/email/teléfono/NIT/CI/dirección), usuario MariaDB con GRANT SELECT por tabla/columna (`php artisan ia:usuario-lectura`). El alcance por sucursal se resuelve ANTES del bloque (lee roles).
- TRAMPAS: los nombres de modelo llevan puntos → nunca `config("ia.precios.{$modelo}")` (la notación de puntos lo parte), indexar el arreglo. `IA_DB_USERNAME=` vacío devuelve '' (no null): usar `?:`.
- Permiso único delegable `reportes.asistente` (rol nuevo `gerente`, en `roles_globales`). Panel del asistente global en el layout. Tests: IaServicioTest, AsistenteBiTest, Unit/IaSeguridadTest.
