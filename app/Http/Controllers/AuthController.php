<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ClassPicker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        // Y-m-d cho dob. Phone tuỳ bạn có thể thêm regex cho VN.
        $data = $r->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed', // FE gửi kèm password_confirmation
            'phone'    => 'nullable|string|max:20|unique:users,phone',
            'dob'      => 'nullable|date',            // "2001-05-20"
        ]);

        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password']),
            'phone'       => $data['phone'] ?? null,
            'dob'         => $data['dob'] ?? null,
            // 'role'     => 'member', // nếu muốn mặc định
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'dob'   => optional($user->dob)->format('Y-m-d'),
                'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
                'role'  => $user->role ?? 'member',
            ],
        ], 201);
    }

    public function login(Request $r)
    {
        $data = $r->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Thông tin đăng nhập không đúng'], 422);
        }

        $token = $user->createToken('api')->plainTextToken;
        $picked = ClassPicker::pickForUser($user->id);

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'dob'   => optional($user->dob)->format('Y-m-d'),
                'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
                'role'  => $user->role ?? 'member',
            ],
            'class_id' => $picked['class_id'],
            'role'     => $picked['role'],
        ]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken();
        return response()->json(['message' => 'Đã đăng xuất']);
    }

    public function me(Request $r)
    {
        $u = $r->user();

        // Tính các trường còn thiếu (để FE hiển thị % hoàn thiện)
        $required = ['name','email'];
        $optional = ['phone','dob','avatar_path'];
        $missing  = [];
        foreach (array_merge($required,$optional) as $f) {
            if (empty($u->$f)) $missing[] = $f;
        }
        $completion = (int) round(
            ((count($required)+count($optional)-count($missing)) /
             (count($required)+count($optional))) * 100
        );

        return response()->json([
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'dob'   => optional($u->dob)->format('Y-m-d'),
            'avatar_url' => $u->avatar_path ? asset('storage/'.$u->avatar_path) : null,
            'role'  => $u->role ?? 'member',

            // thêm thông tin tiện ích
            'profile_missing_fields' => $missing,
            'profile_completion'     => $completion,
        ]);
    }

}
