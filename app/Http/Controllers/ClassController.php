<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\User;
use App\Models\ClassMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\ClassAccess;
use Symfony\Component\HttpFoundation\JsonResponse;

class ClassController extends Controller
{
    public function myRole(Request $r, Classroom $class): JsonResponse
    {
        ClassAccess::ensureMember($r->user(), $class);
        return response()->json(['role' => ClassAccess::roleInClass($r->user(), $class)]);
    }
    public function myClasses(Request $r)
    {
        $uid = $r->user()->id;

        $rows = DB::table('classes as c')                               // <-- ở đây
        ->join('class_members as cm', 'cm.class_id', '=', 'c.id')
        ->where('cm.user_id', $uid)
        ->orderByDesc('c.created_at')
        ->select([
            'c.id',
            'c.name',
            'c.code',
            'c.owner_id',
            DB::raw('cm.role as role'),
            DB::raw('cm.status as member_status'),
        ])
        ->get();
        return response()->json($rows);
    }
    public function members(Request $r, Classroom $class)
{
    // đảm bảo user là member của lớp (hoặc owner/treasurer tuỳ quyền)
    ClassAccess::ensureMember($r->user(), $class);

    $rows = DB::table('class_members as cm')
        ->join('users as u', 'u.id', '=', 'cm.user_id')
        ->where('cm.class_id', $class->id)
        ->select([
            'u.id as user_id',
            'u.name',
            'u.email',
            'cm.id as member_id',
            'cm.role',
            'cm.status',
            'cm.joined_at',
        ])
        ->orderBy('u.name')
        ->get();

    return response()->json(['members' => $rows]);
}
    // ✅ Tạo lớp: tự sinh code + gán owner + tạo membership owner
    public function store(Request $r): JsonResponse
    {
        $data = $r->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $r->user();

        $class = DB::transaction(function () use ($data, $user) {
            $class = Classroom::create([
                'name'     => $data['name'],
                'code'     => Classroom::generateUniqueCode(6),
                'owner_id' => $user->id,
            ]);

            ClassMember::updateOrCreate(
                ['class_id' => $class->id, 'user_id' => $user->id],
                ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]
            );

            return $class;
        });

        return response()->json([
            'class' => $class,
            'role'  => 'owner',
        ], 201);
    }

    public function setRole(Request $r, Classroom $class, int $userId): JsonResponse
    {
        ClassAccess::ensureOwner($r->user(), $class);

        $data = $r->validate(['role' => 'required|in:member,treasurer']);
        // 'owner' không cho set ở đây
        // nếu muốn có thể trả 422 nếu role=owner

        $member = ClassMember::firstOrCreate(
            ['class_id' => $class->id, 'user_id' => $userId],
            ['status' => 'active', 'role' => 'member', 'joined_at' => now()]
        );
        $member->update(['role' => $data['role']]);

        return response()->json($member->fresh());
    }

    // ✅ Join bằng mã: trả 'role' (không phải 'member_role') để khớp FE
    public function joinByCode(Request $r): JsonResponse
    {
        $data = $r->validate(['code' => 'required|string']);
        $user = $r->user();

        $class = Classroom::where('code', $data['code'])->first();
        if (!$class) {
            return response()->json(['message' => 'Mã lớp không tồn tại'], 404);
        }

        $member = ClassMember::firstOrCreate(
            ['class_id' => $class->id, 'user_id' => $user->id],
            ['role' => 'member', 'status' => 'active', 'joined_at' => now()]
        );

        return response()->json([
            'class' => $class,
            'role'  => $member->role,  // <-- FE đang đọc 'role'
        ]);
    }
public function index(Request $r): JsonResponse
{
    $uid = $r->user()->id;

    $rows = DB::table('classes as c') // ✅ đổi classrooms -> classes
        ->join('class_members as cm', 'cm.class_id', '=', 'c.id')
        ->where('cm.user_id', $uid)
        ->orderByDesc('c.created_at')
        ->select([
            'c.id',
            'c.name',
            'c.code',
            'c.owner_id',
            DB::raw('cm.role as role'),
            DB::raw('cm.status as member_status'),
            'c.created_at',
        ])
        // ✅ thay vì withCount('members') (dễ lỗi với alias)
        ->selectSub(
            DB::table('class_members')
                ->selectRaw('count(*)')
                ->whereColumn('class_members.class_id', 'c.id'),
            'members_count'
        )
        ->get();

    return response()->json(['classes' => $rows]);
}
    public function transferOwnership(Request $r, Classroom $class, int $userId): JsonResponse
    {
        ClassAccess::ensureOwner($r->user(), $class);

        $newOwner = User::findOrFail($userId);

        DB::transaction(function () use ($class, $r, $newOwner) {
            // upsert membership cho new owner
            $newMem = ClassMember::firstOrCreate(
                ['class_id' => $class->id, 'user_id' => $newOwner->id],
                ['status' => 'active', 'joined_at' => now()]
            );

            // hạ cấp owner cũ -> treasurer
            if ($old = ClassMember::where('class_id',$class->id)->where('user_id',$r->user()->id)->first()) {
                $old->update(['role' => 'treasurer']);
            }

            // set owner mới
            $newMem->update(['role' => 'owner']);
            $class->update(['owner_id' => $newOwner->id]);
        });

        return response()->json([
            'message' => 'Đã chuyển owner',
            'class_id' => $class->id,
            'new_owner_user_id' => $newOwner->id,
        ]);
    }
}
