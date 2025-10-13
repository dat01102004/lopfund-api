<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Member extends Model {
    protected $fillable = ['class_id','user_id','role','status','joined_at'];
    protected $table = 'class_members';
    public function user(){ return $this->belongsTo(User::class); }
    public function class(){ return $this->belongsTo(ClassRoom::class,'class_id'); }
}
