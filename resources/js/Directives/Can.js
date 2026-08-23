/**
 * Controla la visibilidad de un elemento según los permisos compartidos por Inertia.
 *
 * Uso:
 *   v-can="'sucursales.crear'"                              // Requiere un permiso.
 *   v-can="['sucursales.crear', 'sucursales.editar']"       // Requiere cualquiera (OR).
 *   v-can.all="['sucursales.crear', 'sucursales.editar']"   // Requiere todos (AND).
 *   Los usuarios con el rol "super-admin" siempre tienen acceso.
 *
 * La directiva solo mejora la interfaz. Las rutas y acciones del backend deben
 * seguir protegidas mediante middleware o Policies de Laravel.
 */
const can = {
    mounted(element, binding) {
        updateVisibility(element, binding);
    },

    updated(element, binding) {
        updateVisibility(element, binding);
    },
};

const page = usePage();

function updateVisibility(element, binding) {
    const requiredPermissions = normalizePermissions(binding.value);
    const auth = page.props?.auth ?? {};
    const isSuperAdmin = auth.is_super_admin === true
        || (Array.isArray(auth.roles) && auth.roles.includes('super-admin'));

    // El super-admin no requiere permisos individuales, incluso cuando el
    // elemento de navegación todavía no declara una propiedad permission.
    if (isSuperAdmin) {
        element.hidden = false;
        return;
    }

    const currentPermissions = getCurrentPermissions();
    const requiresAll = binding.modifiers.all === true;

    const isAuthorized = isSuperAdmin || (requiresAll
        ? requiredPermissions.every((permission) => currentPermissions.includes(permission))
        : requiredPermissions.some((permission) => currentPermissions.includes(permission)));

    // Un valor vacío o inválido nunca concede acceso por accidente.
    element.hidden = requiredPermissions.length === 0 || !isAuthorized;
}

function normalizePermissions(value) {
    const permissions = Array.isArray(value) ? value : [value];

    return permissions.filter(
        (permission) => typeof permission === 'string' && permission.trim().length > 0,
    );
}

function getCurrentPermissions() {
    const permissions = page?.props?.auth?.permissions;

    return Array.isArray(permissions) ? permissions : [];
}

export default can;
import { usePage } from '@inertiajs/vue3';
