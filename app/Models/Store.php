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
        'address_two',
        'address_three',
        'address_four',      
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
            'gallery_type',
            'gallery_show_buy_button',
        ];

    /**
     * Se ejecuta cuando el modelo "arranca".
     */
    protected static function booted(): void
    {
        static::saving(function ($store) {
            $store->slug = Str::slug($store->name);
        });
        
        // Crear automáticamente el rol "physical-sales" cuando se crea una nueva tienda
        static::created(function ($store) {
            // Usar firstOrCreate para evitar conflictos de unicidad de Spatie Permission
            // Spatie valida name + guard_name, pero nosotros necesitamos name + guard_name + store_id
            try {
                $role = \App\Models\Role::firstOrCreate(
                    [
                        'name' => 'physical-sales',
                        'store_id' => $store->id,
                        'guard_name' => config('auth.defaults.guard', 'web'),
                    ]
                );
                
                // Limpiar caché de permisos
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            } catch (\Exception $e) {
                // Si firstOrCreate falla por validación de Spatie, usar inserción directa
                $existingRole = \Illuminate\Support\Facades\DB::table('roles')
                    ->where('name', 'physical-sales')
                    ->where('store_id', $store->id)
                    ->where('guard_name', config('auth.defaults.guard', 'web'))
                    ->first();
                
                if (!$existingRole) {
                    \Illuminate\Support\Facades\DB::table('roles')->insert([
                        'name' => 'physical-sales',
                        'store_id' => $store->id,
                        'guard_name' => config('auth.defaults.guard', 'web'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Limpiar caché de permisos
                    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
                }
            }
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
     * Una tienda tiene muchos clientes.
     */
    public function customers()
    {
        return $this->hasMany(\App\Models\Customer::class);
    }

    /**
     * Una tienda tiene muchos cupones.
     */
    public function coupons()
    {
        return $this->hasMany(\App\Models\Coupon::class);
    }

    public function galleryImages()
    {
        return $this->hasMany(\App\Models\GalleryImage::class)->where('is_active', true)->orderBy('order');
    }

    /**
     * Una tienda tiene muchos gastos.
     */
    public function expenses()
    {
        return $this->hasMany(\App\Models\Expense::class);
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