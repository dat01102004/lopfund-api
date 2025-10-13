<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassMember extends Model
{
    protected $table = 'class_members'; 
    protected $fillable = ['class_id','user_id','role','status','joined_at'];
    public $timestamps = true;
    public function classRoom() { return $this->belongsTo(Classroom::class, 'class_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function invoices() { return $this->hasMany(Invoice::class, 'member_id'); }
    public function payments() { return $this->hasMany(Payment::class, 'payer_id'); }
}
