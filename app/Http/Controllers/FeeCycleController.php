<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\FeeCycle;
use App\Models\Invoice;
use App\Models\ClassMember;
use Illuminate\Http\Request;
use App\Support\ClassAccess;
use App\Models\Notification;

class FeeCycleController extends Controller
{
    // ================= LIST cycles =================
    // GET /classes/{class}/fee-cycles
    public function index(Request $r, Classroom $class)
    {
        ClassAccess::ensureMember($r->user(), $class);

        $cycles = FeeCycle::where('class_id', $class->id)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'term',
                'amount_per_member',
                'due_date',
                'status',
                'allow_late',     // 👈 thêm
                'created_at',
            ]);

        // Flutter đang List<Map>.from(res.data) ⇒ trả mảng raw
        return response()->json($cycles);
    }

    // ================= CREATE cycle =================
    // POST /classes/{class}/fee-cycles
    public function store(Request $r, Classroom $class)
    {
        // Owner hoặc Thủ quỹ đều được
        ClassAccess::ensureTreasurerLike($r->user(), $class);

        $data = $r->validate([
            'name'               => 'required|string',
            'term'               => 'nullable|string',
            'amount_per_member'  => 'required|integer|min:0',
            'due_date'           => 'nullable|date',
            'status'             => 'sometimes|in:draft,active,closed',
            'allow_late'         => 'sometimes|boolean', // 👈 nhận allow_late
        ]);
        $data['class_id']   = $class->id;
        $data['allow_late'] = (bool)($data['allow_late'] ?? true); // mặc định cho phép

        $cycle = FeeCycle::create($data);

        // Tạo thông báo mở kỳ cho các thành viên đang active
        $members = $class->members()->where('status', 'active')->pluck('user_id');
        foreach ($members as $uid) {
            Notification::create([
                'user_id'  => $uid,
                'class_id' => $class->id,
                'type'     => 'due_reminder',
                'title'    => 'Mở kỳ thu: '.$data['name'],
                'body'     => 'Số tiền: '.$data['amount_per_member'].' - Hạn: '.($data['due_date'] ?? 'N/A'),
                'sent_at'  => now(),
            ]);
        }

        return response()->json($cycle, 201);
    }

    // =============== Generate invoices cho 1 kỳ ===============
    // POST /classes/{class}/fee-cycles/{cycle}/generate
    public function generateInvoices(Request $r, Classroom $class, FeeCycle $cycle)
    {
        $member = ClassMember::where('class_id', $class->id)
            ->where('user_id', $r->user()->id)->first();

        abort_unless($member && in_array($member->role, ['owner','treasurer']), 403);
        abort_unless($cycle->class_id === $class->id, 404);

        $amount = (int) $r->input('amount_per_member', $cycle->amount_per_member);

        $activeMembers = ClassMember::where('class_id', $class->id)
            ->where('status', 'active')->pluck('id');

        $created = 0; $skipped = 0;

        foreach ($activeMembers as $memberId) {
            $invoice = Invoice::firstOrCreate(
                [
                    'fee_cycle_id' => $cycle->id,
                    'member_id'    => $memberId,
                ],
                [
                    'title'  => $cycle->name,
                    'amount' => $amount,
                    'status' => 'unpaid',
                ]
            );

            if ($invoice->wasRecentlyCreated) $created++;
            else $skipped++;
        }

        return response()->json([
            'cycle_id'          => $cycle->id,
            'amount_per_member' => $amount,
            'created'           => $created,
            'skipped'           => $skipped,
            'total_members'     => $activeMembers->count(),
        ]);
    }

    // ================= Report 1 kỳ =================
    // GET /classes/{class}/fee-cycles/{cycle}/report
    public function report(Request $r, Classroom $class, FeeCycle $cycle)
    {
        ClassAccess::ensureMember($r->user(), $class);
        abort_unless($cycle->class_id === $class->id, 404);

        $summary = Invoice::where('fee_cycle_id', $cycle->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status='unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                COALESCE(SUM(CASE WHEN status='paid' THEN amount END),0) as paid_amount,
                COALESCE(SUM(CASE WHEN status='unpaid' THEN amount END),0) as unpaid_amount
            ")
            ->first();

        return response()->json([
            'cycle' => [
                'id'                 => $cycle->id,
                'name'               => $cycle->name,
                'term'               => $cycle->term,
                'amount_per_member'  => $cycle->amount_per_member,
                'due_date'           => $cycle->due_date,
                'status'             => $cycle->status,
                'allow_late'         => (bool)$cycle->allow_late, // 👈 trả ra
            ],
            'summary' => $summary,
        ]);
    }

    // ============== Update trạng thái (và allow_late) ==============
    // PATCH /classes/{class}/fee-cycles/{cycle}/status
    public function updateStatus(Request $r, Classroom $class, FeeCycle $cycle)
    {
        ClassAccess::ensureTreasurerLike($r->user(), $class);
        abort_unless($cycle->class_id === $class->id, 404);

        $data = $r->validate([
            'status'     => 'required|in:draft,active,closed',
            'allow_late' => 'sometimes|boolean', // 👈 cho phép cập nhật nhanh
        ]);

        $payload = ['status' => $data['status']];
        if ($r->has('allow_late')) {
            $payload['allow_late'] = (bool)$data['allow_late'];
        }

        $cycle->update($payload);

        return response()->json($cycle);
    }
}
