<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    /**
     * Los campos que podemos llenar masivamente.
     */
    protected $fillable = [
        'product_id',
        'options',
        'price',
        'purchase_price',
        'wholesale_price',
        'retail_price',
        'stock',
        'alert',
        'sku',
    ];

    /**
     * Los campos que deben ser 'casteados' (convertidos).
     * Le decimos a Laravel que 'options' es un JSON.
     */
    protected $casts = [
        'options' => 'array',
    ];

    /**
     * Una variante pertenece a un producto.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}