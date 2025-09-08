<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    // Con esta línea le damos permiso al campo 'name'
    protected $fillable = ['name'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}