<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Classroom;
use App\Models\ClassMember;
use App\Models\FundAccount;
use App\Models\FeeCycle;
use App\Models\Invoice;
use App\Models\Payment;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Users (ID sẽ là 1..10 nếu DB rỗng) ---
        $u1  = User::create(['name'=>'Owner',     'email'=>'owner@example.com',     'password'=>Hash::make('123456'), 'role'=>'member']);
        $u2  = User::create(['name'=>'Treasurer', 'email'=>'treasurer@example.com', 'password'=>Hash::make('123456'), 'role'=>'member']);
        $u3  = User::create(['name'=>'Member 3',  'email'=>'member3@example.com',   'password'=>Hash::make('123456'), 'role'=>'member']);
        $u4  = User::create(['name'=>'Member 4',  'email'=>'member4@example.com',   'password'=>Hash::make('123456'), 'role'=>'member']);
        $u5  = User::create(['name'=>'Member 5',  'email'=>'member5@example.com',   'password'=>Hash::make('123456'), 'role'=>'member']); // <== user id=5
        $u6  = User::create(['name'=>'Member 6',  'email'=>'member6@example.com',   'password'=>Hash::make('123456'), 'role'=>'member']);
        $u7  = User::create(['name'=>'Member 7',  'email'=>'member7@example.com',   'password'=>Hash::make('123456'), 'role'=>'member']);
        $u8  = User::create(['name'=>'Member 8',  'email'=>'member8@example.com',   'password'=>Hash::make('123456'), 'role'=>'member']); // <== user id=8
        $u9  = User::create(['name'=>'Member 9',  'email'=>'member9@example.com',   'password'=>Hash::make('123456'), 'role'=>'member']);
        $u10 = User::create(['name'=>'Member 10', 'email'=>'member10@example.com',  'password'=>Hash::make('123456'), 'role'=>'member']);

        // --- Class id=1 ---
        $class = Classroom::create([
            'name' => 'CNTT K22',
            'code' => 'CNTT-K22',
            'owner_id' => $u1->id, // user id=1
            'bank_account' => '123456789',
            'bank_name' => 'VCB',
        ]);

        // --- Members ---
        ClassMember::create(['class_id'=>$class->id,'user_id'=>$u1->id, 'role'=>'owner',     'status'=>'active','joined_at'=>now()]);
        ClassMember::create(['class_id'=>$class->id,'user_id'=>$u2->id, 'role'=>'treasurer', 'status'=>'active','joined_at'=>now()]);
        foreach ([$u3,$u4,$u5,$u6,$u7,$u8,$u9,$u10] as $u) {
            ClassMember::create(['class_id'=>$class->id,'user_id'=>$u->id,'role'=>'member','status'=>'active','joined_at'=>now()]);
        }

        // --- Fund account (tuỳ chọn) ---
        FundAccount::create([
            'class_id'=>$class->id, 'name'=>'Tài khoản lớp',
            'bank_name'=>'VCB','account_no'=>'123456789','account_holder'=>'CNTT K22'
        ]);

        // --- Fee cycle + invoices (để có dữ liệu xem ngay) ---
        $cycle = FeeCycle::create([
            'class_id'=>$class->id, 'name'=>'Quỹ HK1/2025', 'term'=>'HK1 2025',
            'amount_per_member'=>200000, 'due_date'=>now()->addDays(14), 'status'=>'active'
        ]);

        $members = ClassMember::where('class_id',$class->id)->where('status','active')->get();
        foreach ($members as $m) {
            Invoice::create([
                'fee_cycle_id'=>$cycle->id,
                'member_id'=>$m->id,
                'amount'=>$cycle->amount_per_member,
                'status'=>'unpaid'
            ]);
        }

        // Member3 nộp & được verify sẵn (demo)
        $m3 = ClassMember::where('class_id',$class->id)->where('user_id',$u3->id)->first();
        $inv3 = Invoice::where('fee_cycle_id',$cycle->id)->where('member_id',$m3->id)->first();
        Payment::create([
            'invoice_id'=>$inv3->id, 'payer_id'=>$m3->id, 'amount'=>200000,
            'method'=>'bank', 'txn_ref'=>'CK-DEMO-001', 'status'=>'verified',
            'verified_by'=>$u2->id, 'verified_at'=>now()
        ]);
        $inv3->update(['status'=>'verified']);
    }
}
