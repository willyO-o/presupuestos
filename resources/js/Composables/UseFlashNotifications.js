import { router } from '@inertiajs/vue3';
import { showToast } from '@/Utils/AlertUtil';

/**
 * Muestra automaticamente un toast (SweetAlert2, ver Utils/AlertUtil.js)
 * cuando el backend manda un mensaje flash de exito o error — sin que cada
 * pagina/formulario tenga que llamar showToast() a mano en su onSuccess.
 *
 * Se conecta UNA sola vez, en app.js (setup de la app raiz), via el evento
 * global `success` de Inertia (dispara en cada visita con respuesta
 * exitosa: redirects tras store/update/destroy, y tambien reloads
 * parciales — ahi `flash` simplemente no viaja si no esta en el `only`,
 * asi que no dispara nada).
 *
 * Requiere que `App\Http\Middleware\HandleInertiaRequests::share()` mande
 * la prop compartida `flash: { success, error }` leyendo la sesion (ver
 * ese archivo) — cada controlador solo necesita
 * `redirect(...)->with('success', 'Mensaje...')` o `->with('error', ...)`,
 * nada mas.
 */
export function useFlashNotifications() {
    router.on('success', (event) => {
        const flash = event.detail.page.props.flash;

        if (!flash) {
            return;
        }

        if (flash.success) {
            showToast(flash.success, 'success');
        }

        if (flash.error) {
            showToast(flash.error, 'error');
        }
    });
}
