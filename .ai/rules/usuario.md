---
paths:
  - 'app/Http/Requests/Usuario/**'
---

# Usuario

## Nadie reparte más alcance de sucursal del que tiene
Store/UpdateUserRequest impiden la escalada de privilegios por la pantalla de Usuarios:

- `alcance_sucursal` no puede ser `TODAS` si el que edita no ve todas (`soloReparteLoQueAdministra()`).
- Cada `sucursales.*` pasa por `sucursalAdministrada()`: solo se asignan sucursales que uno administra.
- `UsuarioController::index` filtra además el catálogo con `sucursalesDisponibles(soloActivas: true)` — validar sin filtrar el desplegable sería ofrecer algo que después se rechaza.

Hoy NO es explotable: solo `administrador` (rol global) tiene `usuarios.*`. Pero los roles se editan desde la propia UI, así que en cuanto alguien arme un "supervisor" con `usuarios.editar` el agujero estaría abierto — sin esto se daría acceso a toda la empresa creando una cuenta con TODAS. No quitar las reglas por "no hace falta ahora".

`userWithUsuario()` en UsuarioControllerTest da alcance TODAS justamente por esto: sin alcance no podría asignar nada. El caso acotado se prueba con `jefeDeSucursal()`.

Tests: bloque "Escalada de privilegios" en UsuarioControllerTest (4).
