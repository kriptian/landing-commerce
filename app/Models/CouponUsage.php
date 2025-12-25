<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
        'discount_amount',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'discount_amount' => 'decimal:2',
    ];

    /**
     * Relación: Un uso pertenece a un cupón
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Relación: Un uso pertenece a un cliente
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relación: Un uso pertenece a una orden
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

