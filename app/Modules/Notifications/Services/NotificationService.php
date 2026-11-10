<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * Get user notifications paginated.
     */
    public function listForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate($perPage);
    }

    /**
     * Mark single notification as read.
     */
    public function markAsRead(User $user, string $id): bool
    {
        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all user notifications as read.
     */
    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
