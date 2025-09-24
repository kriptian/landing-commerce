<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'store_id', // Es bueno tenerlo aquí también
        'parent_id',
    ];

    /**
     * Relación para obtener la categoría padre.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relación para obtener las categorías hijas (subcategorías).
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Relación para obtener los productos de una categoría.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Relación para saber a qué tienda pertenece la categoría.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}