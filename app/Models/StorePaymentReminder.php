<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StorePaymentReminder extends Model
{
    protected $fillable = [
        'message',
        'image_path',
        'mode',
        'repeat_interval',
        'repeat_unit',
        'pause_catalog',
        'active',
        'activated_at',
    ];

    protected $casts = [
        'pause_catalog' => 'boolean',
        'active' => 'boolean',
        'repeat_interval' => 'integer',
        'activated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(function (StorePaymentReminder $reminder) {
            if ($reminder->image_path) {
                Storage::disk('local')->delete($reminder->image_path);
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
