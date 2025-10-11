<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Store;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'purchase_price',
        'wholesale_price',
        'retail_price',
        'track_inventory',
        'quantity',
        'alert',
        'category_id',
        'short_description',
        'long_description',
        'specifications',
        'variant_attributes',
        'is_featured',
        'promo_active',
        'promo_discount_percent',
        'is_active',
        // 'main_image_url', // <-- LO QUITAMOS DE AQUÍ PORQUE NO ES UNA COLUMNA REAL
    ];

    /**
     * ESTA ES LA NUEVA FUNCIÓN QUE CREA LA URL DE LA IMAGEN
     */
    protected function mainImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Busca la primera imagen asociada a este producto
                $firstImage = $this->images()->first();

                if ($firstImage) {
                    // Devuelve la ruta de la imagen que ya tienes
                    return $firstImage->path;
                }

                // Si no hay imagen del producto, usamos el logo de la tienda si existe
                if ($this->store && !empty($this->store->logo_url)) {
                    return $this->store->logo_url;
                }

                // Fallback local para evitar dependencias externas
                return asset('img/product-placeholder.svg');
            },
        );
    }

    // --- TU CÓDIGO ACTUAL QUEDA IGUAL DE AQUÍ PARA ABAJO ---

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    protected function casts(): array
    {
        return [
            'gallery_images' => 'array',
            'track_inventory' => 'boolean',
            'variant_attributes' => 'array',
        ];
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Un producto tiene muchas opciones.
     */
    public function options()
    {
        return $this->hasMany(\App\Models\ProductOption::class);
    }

    // ... adentro de la clase Product

    /**
     * Un producto tiene muchas variantes.
     */
    public function variants()
    {
        return $this->hasMany(\App\Models\ProductVariant::class);
    }
}