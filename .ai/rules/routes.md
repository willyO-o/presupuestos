---
paths:
  - routes/auth.php
---

# Routes

## Registro público deshabilitado a propósito
No hay registro público: las rutas `register` (GET/POST) se quitaron de routes/auth.php, junto con `app/Http/Controllers/Auth/RegisteredUserController.php`, `resources/js/Pages/Auth/Register.vue` y `tests/Feature/Auth/RegistrationTest.php` (eliminados, no solo deshabilitados). Las cuentas las crea un administrador desde el futuro módulo Usuarios, no un formulario de alta libre. `Welcome.vue` no necesitó tocarse: su link "Register" ya estaba condicionado a `canRegister` (`Route::has('register')`), que ahora resuelve `false` solo. Si se reconstruye un flujo de alta de usuarios, que sea dentro del panel (protegido por `usuarios.crear`), no una ruta `guest`.
