<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    /**
     * Los atributos que se pueden asignar en masa.
     * ESTE ES EL BLOQUE QUE DEBES AGREGAR
     */
    protected $fillable = [
        'name',
        'user_id',
        'logo_url',
    ];

    // --- TUS FUNCIONES ACTUALES QUEDAN IGUAL ---

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

    /**
     * Una tienda TIENE MUCHOS usuarios.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}