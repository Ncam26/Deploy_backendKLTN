<?php

namespace App\Services;

use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class TransactionalMailService
{
    public function sendPasswordResetOtp(
        string $email,
        string $userName,
        string $otp
    ): void {
        $subject = 'Mã xác nhận đặt lại mật khẩu';

        $text = "Xin chào {$userName},\n\n"
            ."Mã xác nhận đặt lại mật khẩu của bạn là: {$otp}\n\n"
            ."Mã này có hiệu lực trong 5 phút.\n"
            .'Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua email này.';

        if ($this->usesBrevo()) {
            $safeName = e($userName);
            $safeOtp = e($otp);

            $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<body style="margin:0;padding:24px;background:#f2f8f5;font-family:Arial,sans-serif;color:#1d3029;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dce9e3;border-radius:16px;overflow:hidden;">
        <div style="padding:20px 24px;background:#0a967a;color:#ffffff;">
            <h1 style="margin:0;font-size:21px;">SÂN MINH TIẾN</h1>
            <p style="margin:7px 0 0;">Đặt lại mật khẩu</p>
        </div>
        <div style="padding:24px;">
            <p>Xin chào <strong>{$safeName}</strong>,</p>
            <p>Mã xác nhận đặt lại mật khẩu của bạn là:</p>
            <div style="margin:20px 0;padding:16px;text-align:center;background:#f4faf7;border-radius:12px;font-size:30px;font-weight:bold;letter-spacing:8px;color:#087b65;">
                {$safeOtp}
            </div>
            <p>Mã này có hiệu lực trong <strong>5 phút</strong>.</p>
            <p style="margin-bottom:0;color:#687870;">Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua email này.</p>
        </div>
    </div>
</body>
</html>
HTML;

            $this->sendViaBrevo(
                $email,
                $userName,
                $subject,
                $html,
                $text
            );

            return;
        }

        Mail::raw($text, function ($mail) use ($email, $subject) {
            $mail->to($email)->subject($subject);
        });
    }

    public function sendBookingConfirmation(Booking $booking): void
    {
        if (! $this->usesBrevo()) {
            Mail::to($booking->user->email)
                ->send(new BookingConfirmedMail($booking));

            return;
        }

        $subject = 'Xác nhận đặt sân thành công - Phiếu #'.$booking->id;
        $html = view('emails.booking-confirmed', [
            'booking' => $booking,
        ])->render();

        $text = "Xin chào {$booking->user->name},\n\n"
            ."Phiếu đặt sân #{$booking->id} đã được xác nhận thanh toán thành công.\n"
            ."Sân: {$booking->court->name}\n"
            .'Ngày chơi: '.$booking->booking_date->format('d/m/Y')."\n"
            .'Khung giờ: '.substr($booking->start_time, 0, 5)
            .' - '.substr($booking->end_time, 0, 5)."\n"
            .'Số tiền: '.number_format($booking->total_price, 0, ',', '.')."đ\n\n"
            .'Vui lòng đến sân đúng giờ và cung cấp mã phiếu khi cần đối chiếu.';

        $this->sendViaBrevo(
            $booking->user->email,
            $booking->user->name,
            $subject,
            $html,
            $text
        );
    }

    private function usesBrevo(): bool
    {
        return config('services.transactional_mail.driver') === 'brevo';
    }

    private function sendViaBrevo(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $html,
        string $text
    ): void {
        $apiKey = (string) config('services.brevo.api_key');
        $senderEmail = (string) config('services.brevo.sender_email');
        $senderName = (string) config('services.brevo.sender_name');

        if ($apiKey === '' || $senderEmail === '') {
            throw new RuntimeException(
                'Thiếu BREVO_API_KEY hoặc BREVO_SENDER_EMAIL.'
            );
        }

        Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'api-key' => $apiKey,
            ])
            ->timeout((int) config('services.brevo.timeout', 15))
            ->post(
                (string) config('services.brevo.endpoint'),
                [
                    'sender' => [
                        'name' => $senderName,
                        'email' => $senderEmail,
                    ],
                    'to' => [[
                        'name' => $recipientName,
                        'email' => $recipientEmail,
                    ]],
                    'subject' => $subject,
                    'htmlContent' => $html,
                    'textContent' => $text,
                ]
            )
            ->throw();
    }
}
