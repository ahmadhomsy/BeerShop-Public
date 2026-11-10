<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $perPage = (int) $request->query('per_page', 15);
        $notifications = $this->notificationService->listForUser($user, $perPage);

        return $this->successResponse([
            'items' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
                'last_page'    => $notifications->lastPage(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ], 'Notifications retrieved.');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $success = $this->notificationService->markAsRead($user, $id);

        if (!$success) {
            return $this->errorResponse('Notification not found.', 404);
        }

        return $this->successResponse(null, 'Notification marked as read.');
    }

    /**
     * Mark all as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth('api')->user();
        $this->notificationService->markAllAsRead($user);

        return $this->successResponse(null, 'All notifications marked as read.');
    }
}
