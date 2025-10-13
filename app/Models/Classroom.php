<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Classroom extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'name','code','owner_id','bank_account','bank_name','qr_path'
    ];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function members() { return $this->hasMany(ClassMember::class, 'class_id'); }
    public function feeCycles() { return $this->hasMany(FeeCycle::class, 'class_id'); }
    public function fundAccounts() { return $this->hasMany(FundAccount::class, 'class_id'); }
    public function expenses() { return $this->hasMany(Expense::class, 'class_id'); }
     public static function generateUniqueCode(int $len = 6): string
    {
        do {
            // Ví dụ: mã 6 ký tự chữ + số, in hoa
            $code = strtoupper(Str::random($len));
        } while (self::where('code', $code)->exists());

        return $code;
    }
    public function notifications()
{
    return $this->hasMany(Notification::class);
}

}
