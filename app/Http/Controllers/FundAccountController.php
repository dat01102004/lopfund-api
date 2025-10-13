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
    public function summary(Request $r, Classroom $class): JsonResponse
    {
        ClassAccess::ensureMember($r->user(), $class);

        $feeCycleId = $r->query('fee_cycle_id'); // optional
        $from       = $r->query('from');         // YYYY-MM-DD (optional)
        $to         = $r->query('to');           // YYYY-MM-DD (optional)

        // Thu: payments (đã duyệt) thuộc các invoices của class này
        $incomeQ = DB::table('payments as p')
            ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->join('fee_cycles as fc', 'fc.id', '=', 'i.fee_cycle_id')
            ->where('fc.class_id', $class->id)
            ->where('p.status', 'verified');

        if ($from)       $incomeQ->whereDate('p.created_at', '>=', $from);
        if ($to)         $incomeQ->whereDate('p.created_at', '<=', $to);

        $income = (int) $incomeQ->sum('p.amount');

        // Chi: expenses của class
        $expenseQ = DB::table('expenses as e')
            ->where('e.class_id', $class->id);

        if ($feeCycleId) $expenseQ->where('e.fee_cycle_id', $feeCycleId);
        if ($from)       $expenseQ->whereDate('e.created_at', '>=', $from);
        if ($to)         $expenseQ->whereDate('e.created_at', '<=', $to);

        $expense = (int) $expenseQ->sum('e.amount');

        return response()->json([
            'total_income'  => $income,
            'total_expense' => $expense,
            'balance'       => $income - $expense,
            'status'        => 200,
        ], 200);
    }

    // GET /classes/{class}/ledger  (sổ tay: opening, income, expense, closing, items[])
    public function ledger(Request $r, Classroom $class): JsonResponse
{
    ClassAccess::ensureMember($r->user(), $class);

    $feeCycleId = $r->query('fee_cycle_id');   // optional
    $from       = $r->query('from');           // YYYY-MM-DD optional
    $to         = $r->query('to');             // YYYY-MM-DD optional

    // 1) Thu (payments đã verified)
    $incomeQ = DB::table('payments as p')
        ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
        ->join('fee_cycles as fc', 'fc.id', '=', 'i.fee_cycle_id')
        ->join('class_members as cm', 'cm.id', '=', 'p.payer_id')
        ->join('users as u', 'u.id', '=', 'cm.user_id')
        ->where('fc.class_id', $class->id)
        ->where('p.status', 'verified')
        ->when($feeCycleId, fn($q) => $q->where('i.fee_cycle_id', $feeCycleId))
        ->when($from,      fn($q) => $q->whereDate('p.created_at', '>=', $from))
        ->when($to,        fn($q) => $q->whereDate('p.created_at', '<=', $to))
        ->selectRaw("
            p.id as id,
            p.created_at as occurred_at,
            CONCAT('Thu kỳ ', fc.name) as note,
            u.name as subject_name,
            cm.role as subject_role,
            p.amount as amount,
            'income' as type
        ");

    // 2) Chi (expenses)
    $expenseQ = DB::table('expenses as e')
        ->leftJoin('fee_cycles as fc', 'fc.id', '=', 'e.fee_cycle_id')
        ->where('e.class_id', $class->id)
        ->when($feeCycleId, fn($q) => $q->where('e.fee_cycle_id', $feeCycleId))
        ->when($from,      fn($q) => $q->whereDate('e.created_at', '>=', $from))
        ->when($to,        fn($q) => $q->whereDate('e.created_at', '<=', $to))
        ->selectRaw("
            e.id as id,
            e.created_at as occurred_at,
            COALESCE(NULLIF(e.title,''), CONCAT('Chi kỳ ', COALESCE(fc.name,'-'))) as note,
            'Lớp' as subject_name,
            'system' as subject_role,
            e.amount as amount,
            'expense' as type
        ");

    // 3) Hợp nhất + sắp xếp thời gian (bọc subquery để orderBy chắc chắn)
    $union = $incomeQ->unionAll($expenseQ);
    $rows = DB::query()
        ->fromSub($union, 't')
        ->orderBy('occurred_at', 'asc')
        ->get();

    // 4) Bắt đầu từ 0 và chạy số dư theo từng dòng
    $opening = 0;
    $running = 0;
    $totalIncome = 0;
    $totalExpense = 0;

    $items = $rows->map(function ($x) use (&$running, &$totalIncome, &$totalExpense) {
        $amount = (int)$x->amount;
        if ($x->type === 'income') {
            $running += $amount;
            $totalIncome += $amount;
        } else {
            $running -= $amount;
            $totalExpense += $amount;
        }
        return [
            'id'            => (int)$x->id,
            'type'          => $x->type, // 'income' | 'expense'
            'occurred_at'   => (string)$x->occurred_at,
            'note'          => (string)$x->note,
            'subject_name'  => (string)$x->subject_name,
            'subject_role'  => (string)$x->subject_role,
            'amount'        => $amount,
            'balance_after' => $running, // số dư sau dòng này
        ];
    })->values();

    return response()->json([
        'opening_balance' => (int)$opening,     // luôn 0 theo yêu cầu
        'total_income'    => (int)$totalIncome,
        'total_expense'   => (int)$totalExpense,
        'closing_balance' => (int)$running,     // = sum(income) - sum(expense)
        'items'           => $items,
    ], 200);
}
}
