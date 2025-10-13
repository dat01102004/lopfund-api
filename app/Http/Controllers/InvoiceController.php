<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\ClassMember;
use Illuminate\Http\Request;
use App\Support\ClassAccess;

class InvoiceController extends Controller
{
    // Member: xem hóa đơn của chính mình
    public function myInvoices(Request $r, Classroom $class)
    {
        ClassAccess::ensureMember($r->user(), $class);

        $member = ClassMember::where('class_id', $class->id)
            ->where('user_id', $r->user()->id)
            ->firstOrFail();

        $invoices = Invoice::where('member_id', $member->id)
            ->with(['cycle:id,name,term,due_date,status'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($invoices);
    }

    // Chi tiết hóa đơn: cho phép
    // - chủ hóa đơn (member) xem để nộp
    // - treasurer/owner xem để kiểm tra/đánh dấu
    public function show(Request $r, Classroom $class, Invoice $invoice)
    {
        // invoice phải thuộc đúng class
        $invoice->loadMissing('cycle:id,class_id,name,term,due_date,status');
        abort_unless(optional($invoice->cycle)->class_id === $class->id, 404);

        // user hiện tại có phải là chủ invoice?
        $isMine = ClassMember::where('id', $invoice->member_id)
            ->where('class_id', $class->id)
            ->where('user_id', $r->user()->id)
            ->exists();

        // treasurer/owner?
        $isTreasurerLike = ClassAccess::isTreasurerLike($r->user(), $class);

        // Nếu không phải chủ và cũng không phải treasurer/owner => chặn
        abort_unless($isMine || $isTreasurerLike, 403);

        // tổng đã xác nhận / đã submit
        $sumVerified  = $invoice->payments()->where('status','verified')->sum('amount');
        $sumSubmitted = $invoice->payments()->where('status','submitted')->sum('amount');
        // chủ hóa đơn mới được nộp; trạng thái cho phép nộp
        $canSubmit   = ($isMine || $isTreasurerLike) && in_array($invoice->status, ['unpaid','submitted'], true);
        $canMarkPaid = $isTreasurerLike;

        // treasurer/owner có thể đánh dấu đã thu (nếu bạn dùng)
        $canMarkPaid  = $isTreasurerLike;

        return response()->json([
        'id'            => $invoice->id,
        'fee_cycle_id'  => $invoice->fee_cycle_id,
        'amount'        => $invoice->amount,
        'status'        => $invoice->status,
        'sum_verified'  => $sumVerified,
        'sum_submitted' => $sumSubmitted,
        'can_submit'    => $canSubmit,
        'can_mark_paid' => $canMarkPaid,
        'title'         => optional($invoice->cycle)->name,
        'fee_cycle'     => [
            'id'       => optional($invoice->cycle)->id,
            'name'     => optional($invoice->cycle)->name,
            'term'     => optional($invoice->cycle)->term,
            'due_date' => optional($invoice->cycle)->due_date,
            'status'   => optional($invoice->cycle)->status,
            ],
        ]);
    }

    // Treaurer/Owner đánh dấu đã thu (tùy bạn có dùng hay không)
    public function markPaid(Request $r, Classroom $class, Invoice $invoice)
    {
        ClassAccess::ensureTreasurerLike($r->user(), $class);
        abort_unless($invoice->cycle->class_id === $class->id, 404);

        $invoice->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json($invoice);
    }
}
