<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhysicalSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
        'sale_number',
        'subtotal',
        'tax',
        'discount',
        'total',
        'payment_method',
        'notes',
        'delivery_cost',
        'amount_tendered',
        'change_due',
        'idempotency_key',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_due' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PhysicalSaleItem::class);
    }
}
