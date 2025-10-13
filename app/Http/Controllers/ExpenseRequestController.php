<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ExpenseRequest;
use App\Models\ExpenseApproval;
use Illuminate\Http\Request;
use App\Support\ClassAccess;

class ExpenseRequestController extends Controller
{
    public function index(Request $r, Classroom $class)
    {
        ClassAccess::ensureMember($r->user(), $class);
        return ExpenseRequest::where('class_id',$class->id)->orderByDesc('created_at')->paginate(20);
    }

    public function store(Request $r, Classroom $class)
    {
        ClassAccess::ensureMember($r->user(), $class);

        $data = $r->validate([
            'title'=>'required|string',
            'reason'=>'nullable|string',
            'amount_est'=>'required|integer|min:0'
        ]);
        $data['class_id'] = $class->id;
        $data['requested_by'] = $r->user()->id;
        $req = ExpenseRequest::create($data);
        return response()->json($req, 201);
    }

    public function approve(Request $r, Classroom $class, ExpenseRequest $req)
    {
        ClassAccess::ensureOwner($r->user(), $class);
        abort_unless($req->class_id === $class->id, 404);

        // phiếu duyệt nhanh 1 người (treasurer/owner)
        $req->update(['status'=>'approved']);
        ExpenseApproval::updateOrCreate(
            ['request_id'=>$req->id, 'voter_id'=>$r->user()->id],
            ['vote'=>'approve']
        );
        return response()->json($req);
    }

    public function reject(Request $r, Classroom $class, ExpenseRequest $req)
    {
        ClassAccess::ensureTreasurerLike($r->user(), $class);
        abort_unless($req->class_id === $class->id, 404);

        $req->update(['status'=>'rejected']);
        ExpenseApproval::updateOrCreate(
            ['request_id'=>$req->id, 'voter_id'=>$r->user()->id],
            ['vote'=>'reject']
        );
        return response()->json($req);
    }
}
