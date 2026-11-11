<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReconciliation extends Model
{
    use HasFactory;

    public const STATE_PENDING_RESERVATION  = 'pending_reservation';
    public const STATE_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATE_CONFIRMED            = 'confirmed';
    public const STATE_PENDING_RELEASE      = 'pending_release';
    public const STATE_RELEASED             = 'released';
    public const STATE_FAILED               = 'failed';

    protected $table = 'inventory_reconciliations';

    protected $fillable = [
        'user_id',
        'order_id',
        'order_ref',
        'idempotency_key',
        'payload_hash',
        'reservation_id',
        'workflow_state',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'locked_until',
        'locked_by',
        'last_error',
        'items_payload',
    ];

    protected $casts = [
        'items_payload' => 'array',
        'next_retry_at' => 'datetime',
        'locked_until'  => 'datetime',
        'retry_count'   => 'integer',
        'max_retries'   => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isPendingReservation(): bool
    {
        return $this->workflow_state === self::STATE_PENDING_RESERVATION;
    }

    public function isPendingConfirmation(): bool
    {
        return $this->workflow_state === self::STATE_PENDING_CONFIRMATION;
    }

    public function isConfirmed(): bool
    {
        return $this->workflow_state === self::STATE_CONFIRMED;
    }

    public function isPendingRelease(): bool
    {
        return $this->workflow_state === self::STATE_PENDING_RELEASE;
    }

    public function isReleased(): bool
    {
        return $this->workflow_state === self::STATE_RELEASED;
    }
}
