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
        'quantity',
        'minimum_stock',
        'alert',
        'category_id',
        'short_description',
        'long_description',
        'specifications',
        'is_featured',
        'promo_active',
        'promo_discount_percent',
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

                // Si no hay imagen, devuelve una imagen genérica de relleno
                return 'https://via.placeholder.com/400x300.png?text=Sin+Imagen';
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