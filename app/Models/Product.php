<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'features',
        'image_1',
        'image_2',
        'image_3',
        'subcategory_id',
        'show_in_home_page',
        'stock',
        'before_discount_price',
        'discount_percentage',
        'price',
        'sold_count'
    ];

    protected $casts = [
        'features' => 'array',
        'show_in_home_page' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function ($product) {
            if ($product->isDirty('before_discount_price') || $product->isDirty('discount_percentage')) {
                $product->price = $product->before_discount_price * (100 - $product->discount_percentage) / 100;
            }
        });
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
