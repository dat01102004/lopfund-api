<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

class ClassPicker
{
    public static function pickForUser(int $userId): array
    {
        $row = DB::table('class_members as cm')
            ->join('classes as c', 'c.id', '=', 'cm.class_id') // <-- đổi classrooms -> classes
            ->where('cm.user_id', $userId)
            ->where('cm.status', 'active')                      // giá trị 'active' là string
            ->orderByDesc('cm.joined_at')
            ->select('c.id as class_id', 'cm.role')
            ->first();

        if ($row) {
            return ['class_id' => (int)$row->class_id, 'role' => (string)$row->role];
        }
        return ['class_id' => null, 'role' => null];
    }
}
