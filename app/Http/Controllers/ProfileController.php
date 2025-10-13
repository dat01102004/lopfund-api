<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function update(Request $r)
    {
        $u = $r->user();

        $data = $r->validate([
            'name'   => 'sometimes|string|max:100',
            'email'  => 'sometimes|email|unique:users,email,'.$u->id,
            'phone'  => 'sometimes|nullable|string|max:20|unique:users,phone,'.$u->id,
            'dob'    => 'sometimes|nullable|date',
            'avatar' => 'sometimes|file|image|max:2048', // <=2MB
        ]);

        if ($r->hasFile('avatar')) {
            $path = $r->file('avatar')->store('avatars', 'public');
            $data['avatar_path'] = $path;
        }

        $u->update($data);

        return response()->json([
            'message'    => 'Cập nhật thành công',
            'user'       => $u->only(['id','name','email','phone','dob','avatar_path']),
            'avatar_url' => $u->avatar_path ? asset('storage/'.$u->avatar_path) : null,
        ]);
    }

    public function changePassword(Request $r)
    {
        $r->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:6|confirmed', // FE gửi new_password_confirmation
        ]);

        $u = $r->user();
        if (!Hash::check($r->current_password, $u->password)) {
            return response()->json(['message'=>'Mật khẩu hiện tại không đúng'], 422);
        }

        $u->password = Hash::make($r->new_password);
        $u->save();

        return response()->json(['message'=>'Đổi mật khẩu thành công']);
    }
}
