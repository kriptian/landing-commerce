<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // <-- 1. AÑADÍ ESTE IMPORT

class Store extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'logo_url',
        'phone',        
        'address',      
        'facebook_url', 
        'instagram_url',
        'slug', // Es bueno añadirlo aquí también
    ];

    /**
     * 2. AÑADÍ ESTE BLOQUE COMPLETO
     * Se ejecuta cuando el modelo "arranca".
     */
    protected static function booted(): void
    {
        // Cada vez que se vaya a guardar (creating o updating) una tienda...
        static::saving(function ($store) {
            // ...le generamos el slug a partir del nombre.
            // ej: "La Tienda de Crisdev" se vuelve "la-tienda-de-crisdev"
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
}