<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'code',
        'type', // 'percentage' o 'fixed'
        'value', // Porcentaje o monto fijo
        'min_purchase', // Monto mínimo de compra
        'max_discount', // Descuento máximo (para porcentajes)
        'valid_from',
        'valid_until',
        'usage_limit', // Límite total de usos (null = ilimitado)
        'usage_limit_per_customer', // Límite por cliente (null = ilimitado)
        'is_active',
        'description',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
    ];

    /**
     * Relación: Un cupón pertenece a una tienda
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Relación: Un cupón puede aplicarse a productos específicos
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_products');
    }

    /**
     * Relación: Un cupón tiene muchos usos
     */
    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Verificar si el cupón es válido
     */
    public function isValid($customerId = null, $cartTotal = 0)
    {
        // Verificar si está activo
        if (!$this->is_active) {
            return false;
        }

        // Verificar fechas
        $now = Carbon::now();
        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        // Verificar monto mínimo
        if ($this->min_purchase && $cartTotal < $this->min_purchase) {
            return false;
        }

        // Verificar límite total de usos
        if ($this->usage_limit !== null) {
            $totalUsages = $this->usages()->count();
            if ($totalUsages >= $this->usage_limit) {
                return false;
            }
        }

        // Verificar límite por cliente
        if ($customerId && $this->usage_limit_per_customer !== null) {
            $customerUsages = $this->usages()->where('customer_id', $customerId)->count();
            if ($customerUsages >= $this->usage_limit_per_customer) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcular descuento aplicado
     */
    public function calculateDiscount($cartTotal)
    {
        if ($this->type === 'percentage') {
            $discount = ($cartTotal * $this->value) / 100;
            // Aplicar descuento máximo si existe
            if ($this->max_discount && $discount > $this->max_discount) {
                $discount = $this->max_discount;
            }
            return round($discount, 2);
        } else {
            // Descuento fijo
            return min($this->value, $cartTotal);
        }
    }
}

