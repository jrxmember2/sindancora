<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()->paginate(20);

        // Moradores veem a listagem dentro do layout do portal.
        $page = $user->canAccessPanel() ? 'Notifications/Index' : 'Portal/Notifications';

        return Inertia::render($page, [
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        // Se a notificação carrega uma URL de destino, segue para lá.
        $url = $notification->data['url'] ?? null;

        return $url ? redirect()->to($url) : back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Notificações marcadas como lidas.');
    }
}
