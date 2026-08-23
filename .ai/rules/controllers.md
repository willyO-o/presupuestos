---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Convenciones de controlador: helper inertia(), request()->user(), redirect()->route()
En este proyecto, dentro de controladores:

- **Vistas Inertia**: usar el helper global `inertia('Carpeta/Componente', [...])`, no la fachada `Inertia::render(...)`. Sin `use Inertia\Inertia;` — solo `use Inertia\Response;` para el type hint del método si aplica.
- **Usuario autenticado**: usar `$request->user()`, no `Auth::user()` ni `auth()->user()`.
- **Redirecciones**: usar el helper `redirect()->route(...)` / `redirect()->to(...)` / `redirect()->intended(...)`, no la fachada `Redirect::route(...)` / `Redirect::to(...)`.

Ya se aplicó esto a todos los controladores existentes (`ProfileController` y los de `Auth/*` generados por Breeze, que traían `Inertia::render()` y, en `ProfileController`, `Redirect::`) — cualquier controlador nuevo debe seguir el mismo patrón desde el inicio.
