<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\{User,Classroom,ClassMember,FundAccount,FeeCycle,Invoice};

class DemoSeeder extends Seeder
{
  public function run(): void
  {
    // --- Users
    $owner = User::firstOrCreate(
      ['email'=>'owner@example.com'],
      ['name'=>'Owner','password'=>Hash::make('password'),'role'=>'owner']
    );
    $m1 = User::firstOrCreate(
      ['email'=>'sv1@example.com'],
      ['name'=>'SV1','password'=>Hash::make('password'),'role'=>'member']
    );
    $m2 = User::firstOrCreate(
      ['email'=>'sv2@example.com'],
      ['name'=>'SV2','password'=>Hash::make('password'),'role'=>'member']
    );

    // --- Classroom (chỉ set cột tồn tại)
    $classAttrs = [
      'name'     => 'CNTT K45',
      'owner_id' => $owner->id,
      'join_code'=> 'ABC123',
    ];
    if (Schema::hasColumn('classes','year')) {
      $classAttrs['year'] = '2025-2026';
    }
    $class = Classroom::firstOrCreate(['name'=>$classAttrs['name']], $classAttrs);

    // --- Members
    ClassMember::firstOrCreate(
      ['class_id'=>$class->id,'user_id'=>$owner->id],
      ['role'=>'owner','status'=>'active','joined_at'=>now()]
    );
    $cm1 = ClassMember::firstOrCreate(
      ['class_id'=>$class->id,'user_id'=>$m1->id],
      ['role'=>'member','status'=>'active','joined_at'=>now()]
    );
    $cm2 = ClassMember::firstOrCreate(
      ['class_id'=>$class->id,'user_id'=>$m2->id],
      ['role'=>'member','status'=>'active','joined_at'=>now()]
    );

    // --- FundAccount (đa dạng tên cột)
    $fa = new FundAccount(['class_id'=>$class->id]);
    // tên hiển thị
    if (Schema::hasColumn('fund_accounts','name')) $fa->name = 'TK Lớp';
    // bank code/name
    if (Schema::hasColumn('fund_accounts','bank_code'))  $fa->bank_code  = 'VCB';
    if (Schema::hasColumn('fund_accounts','bank_name'))  $fa->bank_name  = 'VCB';
    // account number/no
    if (Schema::hasColumn('fund_accounts','account_number')) $fa->account_number = '00112233';
    if (Schema::hasColumn('fund_accounts','account_no'))      $fa->account_no      = '00112233';
    // account name/holder
    if (Schema::hasColumn('fund_accounts','account_name'))   $fa->account_name   = 'CNTT K45';
    if (Schema::hasColumn('fund_accounts','account_holder')) $fa->account_holder = 'CNTT K45';
    $fa->save();

    // --- FeeCycle
    $cycleAttrs = [
      'class_id' => $class->id,
      'name'     => 'Quỹ HK1/2025',
      'status'   => 'active',
    ];
    if (Schema::hasColumn('fee_cycles','term'))              $cycleAttrs['term'] = 'HK1 2025';
    if (Schema::hasColumn('fee_cycles','amount_per_member')) $cycleAttrs['amount_per_member'] = 200000;
    $cycle = FeeCycle::firstOrCreate(
      ['class_id'=>$class->id,'name'=>$cycleAttrs['name']],
      $cycleAttrs
    );

    // --- Invoices cho SV1 & SV2
    foreach ([$cm1,$cm2] as $cm) {
      Invoice::firstOrCreate(
        ['fee_cycle_id'=>$cycle->id,'member_id'=>$cm->id],
        ['amount'=>200000,'status'=>'unpaid']
      );
    }
  }
}
