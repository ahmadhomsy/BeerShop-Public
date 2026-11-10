<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'price',
        'quantity',
        'orderpart_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function orderpart()
    {
        return $this->belongsTo(OrderPart::class, 'orderpart_id');
    }
}
