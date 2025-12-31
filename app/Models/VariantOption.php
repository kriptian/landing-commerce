<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VariantOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'parent_id',
        'name',
        'barcode',
        'price',
        'image_path',
        'order',
        'stock',
        'alert',
        'purchase_price',
    ];

    /**
     * Los atributos que deben ser visibles en la serialización.
     */
    protected $visible = [
        'id',
        'product_id',
        'parent_id',
        'name',
        'barcode',
        'price',
        'image_path',
        'image_url',
        'order',
        'stock',
        'alert',
        'purchase_price',
    ];

    /**
     * Accesor para obtener la URL completa de la imagen.
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }
        
        // Si ya es una URL completa, retornarla
        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }
        
        // Si empieza con /storage/, ya está en el formato correcto
        if (str_starts_with($this->image_path, '/storage/')) {
            return $this->image_path;
        }
        
        // Si es una ruta relativa, convertirla a URL accesible
        return Storage::url($this->image_path);
    }

    /**
     * Relación para obtener la opción padre.
     */
    public function parent()
    {
        return $this->belongsTo(VariantOption::class, 'parent_id');
    }

    /**
     * Relación para obtener las opciones hijas.
     */
    public function children()
    {
        return $this->hasMany(VariantOption::class, 'parent_id')->orderBy('order');
    }

    /**
     * Relación para obtener el producto.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Verificar si es una variante principal (sin padre).
     */
    public function isParent()
    {
        return $this->parent_id === null;
    }

    /**
     * Verificar si es una opción hijo.
     */
    public function isChild()
    {
        return $this->parent_id !== null;
    }
}

