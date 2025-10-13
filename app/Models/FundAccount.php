<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundAccount extends Model
{
    protected $fillable = ['class_id', 'bank_code', 'account_number', 'account_name'];

}
