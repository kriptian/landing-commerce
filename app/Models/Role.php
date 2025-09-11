<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole; // Lo renombramos para que no haya conflicto

class Role extends SpatieRole
{
    use HasFactory;

    /**
     * Los campos que se pueden llenar masivamente.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'store_id', // <-- AÑADIMOS ESTO A LA LISTA
    ];
}