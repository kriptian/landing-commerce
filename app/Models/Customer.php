<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'store_id',
        'name',
        'email',
        'password',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relación: Un cliente pertenece a una tienda
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Relación: Un cliente tiene muchas direcciones
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Relación: Dirección principal/seleccionada
     */
    public function defaultAddress()
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    /**
     * Relación: Un cliente tiene muchas órdenes
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Relación: Un cliente puede usar muchos cupones
     */
    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Relación: Un cliente tiene muchas notificaciones
     */
    public function notifications()
    {
        return $this->hasMany(CustomerNotification::class);
    }
}

