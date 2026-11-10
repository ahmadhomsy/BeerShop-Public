<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'namear',
        'price',
        'description',
        'imgpath',
        'category_id',
        'second_category_id',
        'seller_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function secondcategory()
    {
        return $this->belongsTo(SecondCategory::class, 'second_category_id');
    }
    public function images()
    {
        return $this->hasMany(Image::class, 'product_id');
    }
}
