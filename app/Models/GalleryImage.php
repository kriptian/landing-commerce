<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'image_url',
        'media_type',
        'video_url',
        'title',
        'description',
        'product_id',
        'show_buy_button',
        'order',
        'is_active',
    ];

    protected $casts = [
        'show_buy_button' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

