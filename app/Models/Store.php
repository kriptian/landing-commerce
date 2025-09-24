<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Store extends Model
{
    // NO ES NECESARIO USAR HasFactory si no vas a usar 'seeders' o 'factories' para este modelo.
    // Lo quité para que quede más limpio.

    protected $fillable = [
        'name',
        'user_id',
        'logo_url',
        'phone',        
        'address',      
        'facebook_url', 
        'instagram_url',
        'tiktok_url', // <-- ¡AQUÍ ESTÁ EL CAMBIO!
        'slug',
    ];

    /**
     * Se ejecuta cuando el modelo "arranca".
     */
    protected static function booted(): void
    {
        static::saving(function ($store) {
            $store->slug = Str::slug($store->name);
        });
    }

    // --- Tus otras funciones (owner, products, etc.) quedan igual ---
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Una tienda TIENE MUCHOS roles.
     */
    public function roles()
    {
        return $this->hasMany(\App\Models\Role::class);
    }

    /**
     * Una tienda tiene muchas órdenes.
     */
    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class);
    }
}