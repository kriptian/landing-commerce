<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Store extends Model
{
    // NO ES NECESARIO USAR HasFactory si no vas a usar 'seeders' o 'factories' para este modelo.
    // Lo quité para que quede más limpio.

    protected $fillable = [
        'name',
        'user_id',
        'max_users',
        'logo_url',
        'phone',        
        'address',      
        'facebook_url', 
        'instagram_url',
        'tiktok_url', // <-- ¡AQUÍ ESTÁ EL CAMBIO!
        'slug',
        'custom_domain',
        'promo_active',
        'promo_discount_percent',
        'plan',
        'plan_cycle',
        'plan_started_at',
        'plan_renews_at',
        'catalog_use_default',
        'catalog_button_color',
        'catalog_promo_banner_color',
        'catalog_variant_button_color',
        'catalog_purchase_button_color',
        'catalog_cart_bubble_color',
        'catalog_social_button_color',
        'catalog_logo_position',
        'catalog_menu_type',
        'catalog_product_template',
        'catalog_show_buy_button',
        'catalog_header_style',
        'catalog_header_bg_color',
        'catalog_header_text_color',
        'catalog_button_bg_color',
        'catalog_button_text_color',
        'catalog_body_bg_color',
        'catalog_body_text_color',
            'catalog_input_bg_color',
            'catalog_input_text_color',
            'catalog_promo_banner_text_color',
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

    /**
     * Una tienda tiene muchas ventas físicas.
     */
    public function physicalSales()
    {
        return $this->hasMany(\App\Models\PhysicalSale::class);
    }

    /**
     * Devuelve el logo de la tienda o, si no existe, el logo principal de la app.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return $value ?: '/images/New_Logo_ondgtl.png?v=5';
            }
        );
    }
}