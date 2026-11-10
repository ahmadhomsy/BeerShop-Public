<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\OrderNotification;
use App\Modules\Orders\Events\OrderCreatedEvent;
use App\Modules\Orders\Events\OrderStatusUpdatedEvent;

class SendOrderNotificationListener
{
    /**
     * Handle the OrderCreated event.
     */
    public function handleOrderCreated(OrderCreatedEvent $event): void
    {
        $order = $event->order;
        $user = $order->user;

        if ($user) {
            $user->notify(new OrderNotification(
                'Order Created',
                "Your order #{$order->id} has been placed successfully.",
                [
                    'order_id' => $order->id,
                    'status'   => $order->status,
                    'total'    => $order->total,
                ]
            ));
        }
    }

    /**
     * Handle the OrderStatusUpdated event.
     */
    public function handleStatusUpdated(OrderStatusUpdatedEvent $event): void
    {
        $order = $event->order;
        $user = $order->user;

        if ($user) {
            $user->notify(new OrderNotification(
                'Order Status Updated',
                "Your order #{$order->id} status changed to {$event->newStatus}.",
                [
                    'order_id' => $order->id,
                    'status'   => $event->newStatus,
                ]
            ));
        }
    }
}

