<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseApproval extends Model
{
  protected $fillable = ['request_id','voter_id','vote'];
  public function request(){ return $this->belongsTo(ExpenseRequest::class,'request_id'); }
  public function voter(){ return $this->belongsTo(User::class,'voter_id'); }
}
