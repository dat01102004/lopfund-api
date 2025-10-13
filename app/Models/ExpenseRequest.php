<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseRequest extends Model
{
    protected $fillable = ['class_id','title','reason','amount_est','requested_by','status'];

    public function classroom(){ return $this->belongsTo(Classroom::class,'class_id'); }
    public function requester(){ return $this->belongsTo(User::class,'requested_by'); }
    public function approvals(){ return $this->hasMany(ExpenseApproval::class,'request_id'); }
}
