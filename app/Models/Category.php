<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    /**
     * Los campos que se pueden llenar masivamente.
     */
    protected $fillable = [
        'name',
        'parent_id', // <-- AÑADIMOS ESTO A LA LISTA
    ];

    /**
     * Relación para obtener la categoría padre.
     * Una categoría PERTENECE A (belongsTo) un padre.
     */
    public function parent()
    {
        // Apunta al mismo modelo Category
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relación para obtener las categorías hijas (subcategorías).
     * Una categoría TIENE MUCHOS (hasMany) hijos.
     */
    public function children()
    {
        // Apunta al mismo modelo Category
        return $this->hasMany(Category::class, 'parent_id');
    }
}