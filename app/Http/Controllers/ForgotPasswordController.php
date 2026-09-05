<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    public function sendOtp(Request $request)
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
            ],
            [
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không đúng định dạng.',
            ]
        );

        $message = 'Nếu email đã đăng ký, mã xác nhận sẽ được gửi đến Gmail của bạn.';

        $user = User::where('email', $data['email'])->first();

        // Không thông báo rõ email có tồn tại hay không
        if (! $user) {
            return response()->json([
                'message' => $message,
                'retry_after' => 60,
            ]);
        }

        $oldOtp = PasswordResetOtp::where('email', $data['email'])->first();

        if (
            $oldOtp &&
            $oldOtp->resend_available_at &&
            $oldOtp->resend_available_at->isFuture()
        ) {
            $seconds = now()->diffInSeconds(
                $oldOtp->resend_available_at
            );

            return response()->json([
                'message' => $message,
                'retry_after' => max(1, (int) $seconds),
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        $otpRecord = PasswordResetOtp::updateOrCreate(
            [
                'email' => $data['email'],
            ],
            [
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
                'resend_available_at' => now()->addSeconds(60),
                'verified_at' => null,
            ]
        );

        $email = $data['email'];
        $userName = $user->name;
        $otpRecordId = $otpRecord->id;

        /*
         * Railway có thể mất khá lâu để mở kết nối SMTP Gmail lần đầu.
         * Gửi sau response để trình duyệt không bị 502 và hiểu nhầm là lỗi CORS.
         */
        dispatch(function () use (
            $email,
            $userName,
            $otp,
            $otpRecordId
        ) {
            try {
                Mail::raw(
                    "Xin chào {$userName},\n\n"
                    ."Mã xác nhận đặt lại mật khẩu của bạn là: {$otp}\n\n"
                    ."Mã này có hiệu lực trong 5 phút.\n"
                    ."Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua email này.",
                    function ($mail) use ($email) {
                        $mail->to($email)
                            ->subject('Mã xác nhận đặt lại mật khẩu');
                    }
                );
            } catch (\Throwable $error) {
                PasswordResetOtp::whereKey($otpRecordId)->delete();
                report($error);
            }
        })->afterResponse();

        return response()->json([
            'message' => $message,
            'retry_after' => 60,
        ]);
    }

    public function verifyOtp(Request $request)
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
                'otp' => [
                    'required',
                    'digits:6',
                ],
            ],
            [
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không đúng định dạng.',
                'otp.required' => 'Vui lòng nhập mã xác nhận.',
                'otp.digits' => 'Mã xác nhận phải gồm đúng 6 số.',
            ]
        );

        $otpRecord = PasswordResetOtp::where(
            'email',
            $data['email']
        )->first();

        if (! $otpRecord) {
            return response()->json([
                'message' => 'Mã xác nhận không đúng hoặc đã hết hạn.',
            ], 422);
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->delete();

            return response()->json([
                'message' => 'Mã xác nhận đã hết hạn. Vui lòng gửi mã mới.',
            ], 422);
        }

        if ($otpRecord->attempts >= 5) {
            return response()->json([
                'message' => 'Bạn đã nhập sai quá 5 lần. Vui lòng gửi mã mới.',
            ], 422);
        }

        if (! Hash::check($data['otp'], $otpRecord->otp_hash)) {
            $otpRecord->increment('attempts');

            $remainingAttempts = 5 - $otpRecord->attempts;

            return response()->json([
                'message' => 'Mã xác nhận không chính xác.',
                'remaining_attempts' => max(0, $remainingAttempts),
            ], 422);
        }

        $otpRecord->update([
            'verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Xác nhận Gmail thành công.',
        ]);
    }

    public function resetPassword(Request $request)
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
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[A-Za-z])(?=.*[0-9]).+$/',
                ],
            ],
            [
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không đúng định dạng.',
                'password.required' => 'Vui lòng nhập mật khẩu mới.',
                'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
                'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
                'password.regex' => 'Mật khẩu mới phải có cả chữ và số.',
            ]
        );

        $otpRecord = PasswordResetOtp::where(
            'email',
            $data['email']
        )->first();

        if (
            ! $otpRecord ||
            ! $otpRecord->verified_at ||
            $otpRecord->expires_at->isPast()
        ) {
            return response()->json([
                'message' => 'Bạn chưa xác nhận Gmail hoặc mã đã hết hạn.',
            ], 422);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Không thể đặt lại mật khẩu.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        // Đăng xuất các thiết bị đang đăng nhập
        $user->tokens()->delete();

        $otpRecord->delete();

        return response()->json([
            'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.',
        ]);
    }
}
