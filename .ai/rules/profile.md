---
paths:
  - 'resources/js/Pages/Profile/**'
---

# Profile

## Perfil de usuario: portada + tabs + foto instantánea + ficha de empleado de solo lectura
Profile/Edit.vue ya no usa AuthenticatedLayout/Breeze — usa MainDashboardLayout (defineOptions) con un layout tipo Velzon: `.profile-cover` + tarjeta `.profile-header-card` (avatar superpuesto, editable con el botón cámara) a la izquierda, y una tarjeta con `.nav-tabs` (Datos personales / Cambiar contraseña / Cuenta) a la derecha. Clases nuevas en app.css sección "21. PERFIL DE USUARIO".

La foto (`users.foto`, disco `public`, `php artisan storage:link` ya corrido) se sube en un `useForm` propio (`fotoForm` en Edit.vue), separado del form de nombre/email — así se guarda al instante al elegir el archivo, sin esperar al botón "Guardar cambios". `User::foto_url` es un accessor appended (`$appends`) que arma la URL con `Storage::disk('public')->url()`; el frontend nunca arma la ruta a mano.

Los datos de RR.HH. (cargo, sucursal, área, CI, teléfono, fecha de ingreso) vienen de `Empleado` vía `User::empleado()` (hasOne, `empleado.user_id`) y se muestran de SOLO LECTURA en la pestaña "Datos personales" — se editan únicamente desde el módulo Empleados, nunca desde el perfil. `Empleado::user_id` ya es fillable y el form de Empleados tiene un select "Cuenta de usuario (opcional)"; la unicidad (un usuario ↔ un empleado) se valida con `Rule::unique('empleado','user_id')`, no se filtra en el listado de usuarios del select.

`Empleado` ahora usa `nombres`/`paterno`/`materno` (ya no `nombre_completo`) con un accessor appended `nombre_completo` que concatena los tres — úsalo en vez de reconstruir la concatenación en Vue/PHP.

## Perfil sin autoservicio de eliminar cuenta
Se quitó la capacidad de borrar la propia cuenta desde el perfil: `DeleteUserForm.vue` se eliminó, el tab "Cuenta" ya no existe en `Edit.vue` (solo quedan "Datos personales" y "Cambiar contraseña"), `ProfileController::destroy()` se eliminó y la ruta `DELETE /profile` (`profile.destroy`) ya no existe (queda 405, no 404, porque `/profile` sigue sirviendo GET/PATCH — ver test en ProfileTest.php). Motivo: las cuentas se gestionan desde administración (módulo Usuarios), no de forma autoservicio. No reintroducir un flujo de "eliminar cuenta" en el perfil sin que el usuario lo pida explícitamente.
