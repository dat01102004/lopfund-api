<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeCycle extends Model
{
    protected $fillable = ['class_id','name','term','amount_per_member','due_date','status'];

    public function classRoom() { return $this->belongsTo(Classroom::class, 'class_id'); }
    public function invoices() { return $this->hasMany(Invoice::class, 'fee_cycle_id'); }
}
