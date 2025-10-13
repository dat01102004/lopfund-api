<?php //User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;       // <-- quan trọng
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

     protected $fillable = [
        'name', 'email', 'password',
        'phone', 'dob', 'avatar_path',
        'role',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'dob' => 'date:Y-m-d',
    ];
    public function notifications()
{
    return $this->hasMany(Notification::class);
}

}
