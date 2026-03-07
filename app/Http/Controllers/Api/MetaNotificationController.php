<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MetaNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MetaNotificationController extends Controller
{
    /**
     * GET: Obtener notificaciones del agente
     */
    public function index(Request $request)
    {
        $agentId = Auth::id();
        $perPage = $request->get('per_page', 15);
        $type = $request->get('type'); // Filtrar por tipo
        $unreadOnly = $request->boolean('unread_only', false);

        $query = MetaNotification::forAgent($agentId);

        if ($type) {
            $query->byType($type);
        }

        if ($unreadOnly) {
            $query->unread();
        }

        $notifications = $query
            ->with(['lead', 'agent'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'pagination' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ]
        ]);
    }

    /**
     * GET: Obtener notificaciones no leídas
     */
    public function unread(Request $request)
    {
        $agentId = Auth::id();

        $notifications = MetaNotification::forAgent($agentId)
            ->unread()
            ->with(['lead', 'agent'])
            ->latest()
            ->take($request->get('limit', 5))
            ->get();

        $totalUnread = MetaNotification::forAgent($agentId)->unread()->count();

        return response()->json([
            'success' => true,
            'total_unread' => $totalUnread,
            'data' => $notifications
        ]);
    }

    /**
     * GET: Obtener detalle de una notificación
     */
    public function show($id)
    {
        $agentId = Auth::id();

        $notification = MetaNotification::forAgent($agentId)
            ->with(['lead', 'agent'])
            ->findOrFail($id);

        // Marcar como leída
        if (!$notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => $notification
        ]);
    }

    /**
     * PUT: Marcar como leída
     */
    public function markAsRead($id)
    {
        $agentId = Auth::id();

        $notification = MetaNotification::forAgent($agentId)->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
            'data' => $notification
        ]);
    }

    /**
     * PUT: Marcar todas como leídas
     */
    public function markAllAsRead()
    {
        $agentId = Auth::id();

        MetaNotification::forAgent($agentId)
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Todas las notificaciones marcadas como leídas'
        ]);
    }

    /**
     * DELETE: Eliminar notificación
     */
    public function destroy($id)
    {
        $agentId = Auth::id();

        $notification = MetaNotification::forAgent($agentId)->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificación eliminada'
        ]);
    }

    /**
     * DELETE: Limpiar notificaciones antiguas (más de 30 días)
     */
    public function cleanup()
    {
        $agentId = Auth::id();

        $deleted = MetaNotification::forAgent($agentId)
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Se eliminaron {$deleted} notificaciones antiguas"
        ]);
    }

    /**
     * GET: Estadísticas de notificaciones
     */
    public function stats()
    {
        $agentId = Auth::id();

        $stats = [
            'total_unread' => MetaNotification::forAgent($agentId)->unread()->count(),
            'total_all' => MetaNotification::forAgent($agentId)->count(),
            'by_type' => MetaNotification::forAgent($agentId)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
