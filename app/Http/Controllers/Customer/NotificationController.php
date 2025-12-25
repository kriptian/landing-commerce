<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Marcar una notificación como leída
     */
    public function markAsRead(Request $request, $notificationId)
    {
        // Obtener el notificationId correcto desde los parámetros de la ruta
        $notificationId = $request->route('notificationId');
        
        $customer = Auth::guard('customer')->user();
        
        // Obtener la notificación y verificar que pertenece al cliente
        $notification = CustomerNotification::where('id', $notificationId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
            ]);
        }

        // Retornar el nuevo conteo de notificaciones no leídas
        $unreadCount = $customer->notifications()->where('is_read', false)->count();
        
        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
    }
}

