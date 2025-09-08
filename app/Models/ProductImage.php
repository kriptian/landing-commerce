<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar en masa.
     * ¡AQUÍ ESTÁ LA MAGIA!
     */
    protected $fillable = [
        'path',
    ];
}