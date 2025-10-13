<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\FeeCycle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;  // <-- import Schema facade
use App\Support\ClassAccess;

class ReportController extends Controller
{
    /**
     * GET /classes/{class}/fee-cycles/{cycle}/report
     */
    public function cycleSummary(Request $r, Classroom $class, FeeCycle $cycle): JsonResponse
    {
        ClassAccess::ensureMember($r->user(), $class);
        abort_unless((int)$cycle->class_id === (int)$class->id, 404, 'Kỳ không thuộc lớp');

        // 1) Dự kiến thu
        $activeMemberCount = DB::table('class_members')
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->count();

        $expected = (int)$activeMemberCount * (int)$cycle->amount_per_member;

        // 2) Tổng thu: payments đã verify (join invoices để lọc fee_cycle_id)
        $totalIncome = (int) DB::table('payments as p')
        ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
        ->join('fee_cycles as fc', 'fc.id', '=', 'i.fee_cycle_id')
        ->where('fc.id', $cycle->id)
        ->where('fc.class_id', $class->id)
        ->where('p.status', 'verified')   // nếu schema là boolean: ->where('p.verified', 1)
        ->sum('p.amount');

        // 3) Tổng chi: expense gắn fee_cycle_id = kỳ
        $sumExpenseByCycle = (int) DB::table('expenses')
            ->where('class_id', $class->id)
            ->where('fee_cycle_id', $cycle->id)
            ->sum('amount');

        $fallbackExpense = 0;

        // Fallback theo thời gian (chỉ nếu fee_cycles có cột start_date/end_date và expense không gắn kỳ)
        $fallbackExpense = 0;
        // Chỉ chạy fallback khi bảng fee_cycles có cột mốc thời gian
        if (Schema::hasColumn('fee_cycles', 'start_date') && Schema::hasColumn('fee_cycles', 'end_date')) {
            $fallbackExpense = (int) DB::table('expenses')
                ->where('class_id', $class->id)
                ->whereNull('fee_cycle_id')                             // các khoản chi chưa gắn kỳ
                ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
                ->sum('amount');
        }

        $totalExpense = $sumExpenseByCycle + $fallbackExpense;

        // 4) Phân rã invoices theo trạng thái (tuỳ chọn)
        $invoiceByStatus = DB::table('invoices')
            ->select('status', DB::raw('SUM(amount) as total'))
            ->where('fee_cycle_id', $cycle->id)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $sumUnpaid    = (int)($invoiceByStatus['unpaid']    ?? 0);
        $sumSubmitted = (int)($invoiceByStatus['submitted'] ?? 0);
        $sumVerified  = (int)($invoiceByStatus['verified']  ?? 0);
        $sumPaid      = (int)($invoiceByStatus['paid']      ?? 0);

        return response()->json([
            'class_id'          => (int)$class->id,
            'fee_cycle_id'      => (int)$cycle->id,

            'active_members'    => (int)$activeMemberCount,
            'amount_per_member' => (int)$cycle->amount_per_member,
            'expected_total'    => (int)$expected,

            'unpaid_total'      => $sumUnpaid,
            'submitted_total'   => $sumSubmitted,
            'verified_total'    => $sumVerified,
            'paid_total'        => $sumPaid,

            'total_income'      => $totalIncome,
            'total_expense'     => $totalExpense,
            'balance'           => $totalIncome - $totalExpense,
        ]);
    }

    /**
     * GET /classes/{class}/balance
     */
    public function classBalance(Request $r, Classroom $class): JsonResponse
    {
        ClassAccess::ensureMember($r->user(), $class);

        $income = (int) DB::table('payments')
            ->where('class_id', $class->id)
            ->where('status', 'verified') // hoặc ->where('verified', 1)
            ->sum('amount');

        $expense = (int) DB::table('expenses')
            ->where('class_id', $class->id)
            ->sum('amount');

        return response()->json([
            'income'  => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ]);
    }
}
