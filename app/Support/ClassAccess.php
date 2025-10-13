<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\User;

/**
 * ClassAccess
 * ------------------------------------------------------------
 * Helpers kiểm tra quyền trong phạm vi 1 lớp.
 * - Member: là thành viên của lớp
 * - Owner: lớp trưởng/chủ lớp (duy nhất)
 * - TreasurerLike: owner hoặc treasurer (thủ quỹ)
 *
 * Cách dùng trong controller:
 *   ClassAccess::ensureMember($request->user(), $class);
 *   ClassAccess::ensureOwner($request->user(), $class);
 *   ClassAccess::ensureTreasurerLike($request->user(), $class);
 *   ClassAccess::isTreasurerLike($request->user(), $class); // TRUE/FALSE
 */
class ClassAccess
{
    public static function roleInClass(User $user, Classroom $class): ?string
    {
        $member = $class->members()->where('user_id', $user->id)->first();
        return $member?->role;
    }

    public static function ensureMember(User $user, Classroom $class): void
    {
        $role = self::roleInClass($user, $class);
        abort_unless($role !== null, 403, 'Không phải thành viên của lớp này');
    }

    public static function ensureOwner(User $user, Classroom $class): void
    {
        $role = self::roleInClass($user, $class);
        abort_unless($role === 'owner', 403, 'Chỉ Owner mới được thao tác');
    }

    public static function ensureTreasurerLike(User $user, Classroom $class): void
    {
        $role = self::roleInClass($user, $class);
        abort_unless(in_array($role, ['owner','treasurer'], true), 403, 'Cần quyền owner/treasurer');
    }

    // ===== Thêm hàm isTreasurerLike =====
    public static function isTreasurerLike(User $user, Classroom $class): bool
    {
        $role = self::roleInClass($user, $class);
        return in_array($role, ['owner','treasurer'], true);
    }

    public static function assertSameClass(int $resourceClassId, Classroom $class): void
    {
        abort_unless($resourceClassId === $class->id, 404, 'Tài nguyên không thuộc lớp này');
    }
}
