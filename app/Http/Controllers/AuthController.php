<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
            'phone' => trim((string) $request->phone),
        ]);

        $data = $request->validate(
            [
                'name' => [
                    'required',
                    'string',
                    'min:2',
                    'max:100',
                ],
                'phone' => [
                    'required',
                    'regex:/^0[0-9]{9}$/',
                ],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    'unique:users,email',
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[A-Za-z])(?=.*[0-9]).+$/',
                ],
            ],
            [
                'name.required' => 'Vui lòng nhập họ và tên.',
                'name.min' => 'Họ và tên phải có ít nhất 2 ký tự.',
                'name.max' => 'Họ và tên không được vượt quá 100 ký tự.',

                'phone.required' => 'Vui lòng nhập số điện thoại.',
                'phone.regex' => 'Số điện thoại phải gồm 10 số và bắt đầu bằng số 0.',

                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không đúng định dạng.',
                'email.unique' => 'Email này đã được đăng ký.',
                'email.max' => 'Email không được vượt quá 255 ký tự.',

                'password.required' => 'Vui lòng nhập mật khẩu.',
                'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
                'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
                'password.regex' => 'Mật khẩu phải có cả chữ và số.',
            ]
        );

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng đăng nhập.',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $data = $request->validate(
            [
                'email' => [
                    'required',
                    'email',
                ],
                'password' => [
                    'required',
                    'string',
                ],
            ],
            [
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không đúng định dạng.',
                'password.required' => 'Vui lòng nhập mật khẩu.',
            ]
        );

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [
                    'Email hoặc mật khẩu không chính xác.',
                ],
            ]);
        }

        if ($user->status === 'blocked') {
            return response()->json([
                'message' => 'Tài khoản của bạn đã bị khóa.',
            ], 403);
        }

        $token = $user->createToken('react-app')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],
            'phone' => [
                'required',
                'regex:/^0[0-9]{9}$/',
            ],
            'reminder_enabled' => [
                'required',
                'boolean',
            ],
        ]);

        $request->user()->update($data);

        return response()->json([
            'message' => 'Cập nhật thông tin thành công.',
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công.',
        ]);
    }
}