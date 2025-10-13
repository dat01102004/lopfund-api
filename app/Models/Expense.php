<?php

// app/Models/Expense.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'class_id','fee_cycle_id','title','amount','note','created_by',
    ];
}

