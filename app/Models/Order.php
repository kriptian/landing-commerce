<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence_number',
        'store_id',
        'customer_id',
        'address_id',
        'coupon_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'total_price',
        'discount_amount',
        'status',
        'notes',
        'delivery_cost',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Relación: Una orden puede pertenecer a un cliente
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relación: Una orden puede tener una dirección de envío
     */
    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Relación: Una orden puede tener un cupón aplicado
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}