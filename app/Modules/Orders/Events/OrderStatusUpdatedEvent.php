<?php

namespace App\Modules\Orders\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $newStatus;

    public function __construct(Order $order, string $newStatus)
    {
        $this->order = $order;
        $this->newStatus = $newStatus;
    }
}

