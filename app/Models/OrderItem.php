<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'product_name',
        'variant_options',
    ];

    protected $casts = [
        'variant_options' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
     /**
     * Un item de la orden pertenece a un producto.
     */
    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    /**
     * Un item de la orden pertenece a una variante de producto.
     */
    public function variant()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'product_variant_id');
    }
}