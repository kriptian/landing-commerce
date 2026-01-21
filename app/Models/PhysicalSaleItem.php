<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhysicalSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'physical_sale_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'subtotal',
        'product_name',
        'variant_options',
        'original_price',
        'discount_percent',
        'purchase_price',
    ];

    protected $casts = [
        'variant_options' => 'array',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    public function physicalSale()
    {
        return $this->belongsTo(PhysicalSale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

