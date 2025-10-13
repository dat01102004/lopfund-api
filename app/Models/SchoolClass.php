<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model {
    protected $table = 'classes';
    protected $fillable = ['class_name'];

    public function members() {
        return $this->belongsToMany(User::class, 'class_members', 'class_id','user_id')
            ->withPivot('role','joined_at')
            ->withTimestamps();
    }
}
