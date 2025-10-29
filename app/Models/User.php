<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Store;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_name',
        'phone',
        'area',
        'is_admin',
        'store_id', // <-- ESTA ERA LA LÍNEA QUE FALTABA
        'first_login',
        'tour_completed_at',
        'completed_tours',
        'remind_later_tours',
        'never_show_tours',
    ];

    /**
     * The attributes that should be hidden for serialization. 
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'first_login' => 'boolean',
            'tour_completed_at' => 'datetime',
            'completed_tours' => 'array',
            'remind_later_tours' => 'array',
            'never_show_tours' => 'array',
        ];
    }

    /**
     * Obtiene la tienda a la que pertenece el usuario.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function cart()
    {
        return $this->hasMany(\App\Models\Cart::class);
    }
}