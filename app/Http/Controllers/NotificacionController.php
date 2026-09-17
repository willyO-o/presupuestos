<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class NotificacionController extends Controller
{
    /**
     * Listado paginado de TODAS las notificaciones del usuario autenticado
     * (leídas y no leídas), más recientes primero.
     */
    public function index(Request $request): Response
    {
        $notificaciones = $request->user()->notifications()
            ->orderByDesc('created_at')
            ->paginate(15);

        return inertia('Notificaciones/Index', [
            'notificaciones' => $notificaciones,
            'noLeidas' => $request->user()->unreadNotifications()->count(),
            'pageTitle' => 'Notificaciones',
            'breadcrumbs' => ['Notificaciones'],
        ]);
    }

    /**
     * Marca la notificación como leída y redirige al recurso que la originó
     * (`data.url`) — un solo clic hace las dos cosas, sin viajes extra al
     * servidor desde el dropdown del topbar.
     */
    public function abrir(Request $request, DatabaseNotification $notificacion): RedirectResponse
    {
        abort_unless(
            $notificacion->notifiable_type === $request->user()::class
                && $notificacion->notifiable_id === $request->user()->id,
            HttpResponse::HTTP_FORBIDDEN,
        );

        if ($notificacion->unread()) {
            $notificacion->markAsRead();
        }

        return redirect()->to($notificacion->data['url'] ?? route('dashboard'));
    }

    /**
     * Marca todas las notificaciones pendientes del usuario como leídas
     * (botón "Marcar todas como leídas" del dropdown/listado).
     */
    public function marcarTodasLeidas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back();
    }
}
