<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model {
    protected $fillable = ['fee_cycle_id','member_id','amount','status','paid_at'];
    protected $casts = ['paid_at'=>'datetime'];
    protected $appends = ['display_title'];
    public function member(){ return $this->belongsTo(Member::class); }
    public function cycle(){ return $this->belongsTo(FeeCycle::class,'fee_cycle_id'); }
    public function payments(){ return $this->hasMany(Payment::class); }

    public function feeCycle() { return $this->belongsTo(FeeCycle::class); }

    public function getDisplayTitleAttribute() {
    return $this->title ?? optional($this->feeCycle)->name ?? ('Invoice #'.$this->id);
    }
}
