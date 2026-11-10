<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'total',
        'quantity',
        'order_id',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function seller(){
        return $this->belongsTo(User::class);
    }
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'orderpart_id');
    }
}
