<?php

namespace App\Modules\Orders\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Customer can ONLY view their own order; admin can view all.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return (int) $user->id === (int) $order->user_id;
    }

    /**
     * ONLY admin can update order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->role === 'admin';
    }
}
