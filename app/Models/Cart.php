<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'address_id',
        'discount_id',
        'payment_method',
        'before_discount_amount',
        'total_amount',
        'order_submitted_at'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function discount_admin(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class)->whereNull('deleted_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function recalculateTotals(): void
    {
        $this->before_discount_amount = $this->items->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        if ($this->discount_id) {
            $discount = Discount::query()->find($this->discount_id);
            if ($discount && is_null($discount->deleted_at)) {
                if ($discount->max_amount && $this->before_discount_amount > $discount->max_amount) {
                    $this->total_amount = $this->before_discount_amount - $discount->max_amount;
                } else {
                    $this->total_amount = $this->before_discount_amount * (100 - $discount->discount_percentage) / 100;
                }
            } else {
                $this->discount_id = null;
                $this->total_amount = $this->before_discount_amount;
            }
        } else {
            $this->total_amount = $this->before_discount_amount;
        }

        $this->save();
    }
}
