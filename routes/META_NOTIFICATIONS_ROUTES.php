<?php

// Meta Notifications Routes
// Este archivo debe incluirse en routes/api.php dentro del grupo autenticado

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\MetaNotificationController;

Route::middleware('auth:sanctum')->group(function () {
    // Notificaciones de Meta
    Route::prefix('meta/notifications')->group(function () {
        Route::get('/', [MetaNotificationController::class, 'index']); // GET todas
        Route::get('/unread', [MetaNotificationController::class, 'unread']); // GET no leídas
        Route::get('/stats', [MetaNotificationController::class, 'stats']); // GET estadísticas
        Route::get('/{id}', [MetaNotificationController::class, 'show']); // GET detalle (marca como leída)
        Route::put('/{id}/read', [MetaNotificationController::class, 'markAsRead']); // PUT marcar como leída
        Route::put('/read-all', [MetaNotificationController::class, 'markAllAsRead']); // PUT marcar todas como leídas
        Route::delete('/{id}', [MetaNotificationController::class, 'destroy']); // DELETE notificación
        Route::delete('/cleanup', [MetaNotificationController::class, 'cleanup']); // DELETE limpiar antiguas
    });
});

