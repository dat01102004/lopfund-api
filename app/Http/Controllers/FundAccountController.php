<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\FundAccount;
use App\Support\ClassAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

class FundAccountController extends Controller
{
    // GET /classes/{class}/fund-account
    public function show(Request $r, Classroom $class): JsonResponse
    {
        ClassAccess::ensureMember($r->user(), $class);

        $row = FundAccount::where('class_id', $class->id)->first();

        return response()->json([
            'fund_account' => $row ? [
                'bank_code'      => $row->bank_code,
                'account_number' => $row->account_number,
                'account_name'   => $row->account_name,
            ] : null,
        ], 200);
    }

    // PUT /classes/{class}/fund-account
    public function upsert(Request $r, Classroom $class): JsonResponse
    {
        ClassAccess::ensureTreasurerLike($r->user(), $class);

        $data = $r->validate([
            'bank_code'      => 'required|string|max:20',
            'account_number' => 'required|string|max:50',
            'account_name'   => 'required|string|max:120',
        ]);

        $row = FundAccount::updateOrCreate(
            ['class_id' => $class->id],
            [
                'bank_code'      => strtoupper($data['bank_code']),
                'account_number' => $data['account_number'],
                'account_name'   => mb_strtoupper($data['account_name']),
            ],
        );

        return response()->json([
            'fund_account' => [
                'bank_code'      => $row->bank_code,
                'account_number' => $row->account_number,
                'account_name'   => $row->account_name,
            ],
            'updated' => true,
        ], 200);
    }

    // GET /classes/{class}/fund-account/summary
    // GET /classes/{class}/fund-account/summary
// GET /classes/{class}/fund-account/summary
public function summary(Request $r, Classroom $class): JsonResponse
{
    ClassAccess::ensureMember($r->user(), $class);

    $feeCycleId = $r->query('fee_cycle_id'); // optional
    $from       = $r->query('from');         // YYYY-MM-DD (optional)
    $to         = $r->query('to');           // YYYY-MM-DD (optional)

    // ===== Thu: mọi payment đã từng được duyệt (verified_at != null)
    $incomeQ = DB::table('payments as p')
        ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
        ->join('fee_cycles as fc', 'fc.id', '=', 'i.fee_cycle_id')
        ->where('fc.class_id', $class->id)
        ->when($feeCycleId, fn($q) => $q->where('i.fee_cycle_id', $feeCycleId))
        ->whereNotNull('p.verified_at');

    if ($from) $incomeQ->whereDate('p.verified_at', '>=', $from);
    if ($to)   $incomeQ->whereDate('p.verified_at', '<=', $to);

    $income = (int) $incomeQ->sum('p.amount');

    // ===== Chi: expenses của class (giữ nguyên)
    $expenseQ = DB::table('expenses as e')
        ->where('e.class_id', $class->id)
        ->when($feeCycleId, fn($q) => $q->where('e.fee_cycle_id', $feeCycleId));

    if ($from) $expenseQ->whereDate('e.created_at', '>=', $from);
    if ($to)   $expenseQ->whereDate('e.created_at', '<=', $to);

    $expense = (int) $expenseQ->sum('e.amount');

    // ===== Khoản đảo do KHÔNG HỢP LỆ: tính riêng (chi)
    $invalidQ = DB::table('payments as p')
        ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
        ->join('fee_cycles as fc', 'fc.id', '=', 'i.fee_cycle_id')
        ->where('fc.class_id', $class->id)
        ->where('p.status', 'invalid')
        ->when($feeCycleId, fn($q) => $q->where('i.fee_cycle_id', $feeCycleId));

    if ($from) $invalidQ->whereDate('p.invalidated_at', '>=', $from);
    if ($to)   $invalidQ->whereDate('p.invalidated_at', '<=', $to);

    $invalidTotal = (int) $invalidQ->sum('p.amount');

    return response()->json([
        'total_income'   => $income,
        'total_expense'  => $expense + $invalidTotal,  // chi thường + chi đảo
        'invalid_total'  => $invalidTotal,              // để FE hiển thị riêng
        'balance'        => $income - ($expense + $invalidTotal),
        'status'         => 200,
    ], 200);
}

// GET /classes/{class}/ledger  (sổ tay)
public function ledger(Request $r, Classroom $class): JsonResponse
{
    ClassAccess::ensureMember($r->user(), $class);

    $feeCycleId = $r->query('fee_cycle_id');   // optional
    $from       = $r->query('from');           // YYYY-MM-DD optional
    $to         = $r->query('to');             // YYYY-MM-DD optional

    // 1) DÒNG THU: mọi payment đã duyệt (giữ dòng thu kể cả sau này bị invalid)
    $payIncomes = DB::table('payments as p')
        ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
        ->join('fee_cycles as fc', 'fc.id', '=', 'i.fee_cycle_id')
        ->join('class_members as cm', 'cm.id', '=', 'p.payer_id')
        ->join('users as u', 'u.id', '=', 'cm.user_id')
        ->where('fc.class_id', $class->id)
        ->when($feeCycleId, fn($q) => $q->where('i.fee_cycle_id', $feeCycleId))
        ->whereNotNull('p.verified_at')
        ->when($from, fn($q) => $q->whereDate('p.verified_at', '>=', $from))
        ->when($to,   fn($q) => $q->whereDate('p.verified_at', '<=', $to))
        ->selectRaw("
            p.id,
            'payment' as type,
            p.amount,
            p.verified_at as occurred_at,
            CONCAT('Phiếu nộp #', p.id) as note,
            u.name as subject_name,
            cm.role as subject_role
        ");

    // 2) DÒNG ĐẢO: payment KHÔNG HỢP LỆ (trừ quỹ)
    $payInvalids = DB::table('payments as p')
        ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
        ->join('fee_cycles as fc', 'fc.id', '=', 'i.fee_cycle_id')
        ->join('class_members as cm', 'cm.id', '=', 'p.payer_id')
        ->join('users as u', 'u.id', '=', 'cm.user_id')
        ->leftJoin('users as invu', 'invu.id', '=', 'p.invalidated_by')
        ->where('fc.class_id', $class->id)
        ->when($feeCycleId, fn($q) => $q->where('i.fee_cycle_id', $feeCycleId))
        ->where('p.status', 'invalid')
        ->when($from, fn($q) => $q->whereDate('p.invalidated_at', '>=', $from))
        ->when($to,   fn($q) => $q->whereDate('p.invalidated_at', '<=', $to))
        ->selectRaw("
            p.id,
            'invalid_payment' as type,
            p.amount,
            p.invalidated_at as occurred_at,
            CONCAT(
              'Huỷ phiếu nộp #', p.id, ' (không hợp lệ)',
              CASE WHEN COALESCE(p.invalid_reason,'') <> '' THEN CONCAT(': ', p.invalid_reason) ELSE '' END
            ) as note,
            COALESCE(invu.name, u.name) as subject_name,
            cm.role as subject_role
        ");

    // 3) DÒNG CHI: expenses
    $expenses = DB::table('expenses as e')
        ->leftJoin('fee_cycles as fc', 'fc.id', '=', 'e.fee_cycle_id')
        ->leftJoin('users as u', 'u.id', '=', 'e.created_by')
        ->where('e.class_id', $class->id)
        ->when($feeCycleId, fn($q) => $q->where('e.fee_cycle_id', $feeCycleId))
        ->when($from, fn($q) => $q->whereDate('e.created_at', '>=', $from))
        ->when($to,   fn($q) => $q->whereDate('e.created_at', '<=', $to))
        ->selectRaw("
            e.id,
            'expense' as type,
            e.amount,
            COALESCE(e.spent_at, e.created_at) as occurred_at,
            COALESCE(NULLIF(e.title,''), CONCAT('Chi kỳ ', COALESCE(fc.name,'-'))) as note,
            COALESCE(u.name,'') as subject_name,
            'system' as subject_role
        ");

    // 4) union + sort
    $union = $payIncomes->unionAll($payInvalids)->unionAll($expenses);
    $rows = DB::query()
        ->fromSub($union, 't')
        ->orderBy('occurred_at', 'asc')
        ->orderBy('type', 'asc') // payment trước invalid_payment cùng ngày
        ->orderBy('id', 'asc')
        ->get();

    // 5) Chạy số dư
    $opening = 0;
    $running = 0;
    $totalIncome = 0;
    $totalExpense = 0;
    $invalidTotal = 0;

    $items = [];
    foreach ($rows as $x) {
        $amount = (int)$x->amount;
        $type   = (string)$x->type;

        $isIncome  = in_array($type, ['income','payment'], true);
        $isInvalid = $type === 'invalid_payment';

        if ($isIncome) {
            $running += $amount;
            $totalIncome += $amount;
        } else {
            $running -= $amount;
            $totalExpense += $amount;
            if ($isInvalid) $invalidTotal += $amount;
        }

        $items[] = [
            'id'            => (int)$x->id,
            'type'          => $type, // 'payment' | 'invalid_payment' | 'expense'
            'occurred_at'   => (string)$x->occurred_at,
            'note'          => (string)$x->note,
            'subject_name'  => (string)$x->subject_name,
            'subject_role'  => (string)$x->subject_role,
            'amount'        => $amount,
            'is_income'     => $isIncome ? 1 : 0,
            'balance_after' => $running,
        ];
    }

    return response()->json([
        'opening_balance' => (int)$opening,
        'total_income'    => (int)$totalIncome,
        'total_expense'   => (int)$totalExpense, // gồm cả invalid_payment
        'invalid_total'   => (int)$invalidTotal, // phần chi do không hợp lệ
        'closing_balance' => (int)$running,
        'items'           => $items,
    ], 200);
}


}
