<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Services\TransactionalMailService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransactionalMailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.transactional_mail.driver' => 'brevo',
            'services.brevo.api_key' => 'test-brevo-key',
            'services.brevo.sender_email' => 'anhminh20221@gmail.com',
            'services.brevo.sender_name' => 'Sân Minh Tiến',
            'services.brevo.endpoint' => 'https://api.brevo.com/v3/smtp/email',
            'services.brevo.timeout' => 15,
        ]);

        Http::fake([
            'api.brevo.com/*' => Http::response([
                'messageId' => 'test-message-id',
            ], 201),
        ]);
    }

    public function test_password_reset_otp_is_sent_through_brevo_api(): void
    {
        app(TransactionalMailService::class)
            ->sendPasswordResetOtp(
                'customer@example.com',
                'Nguyễn Văn A',
                '123456'
            );

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-brevo-key')
                && $data['sender']['email'] === 'anhminh20221@gmail.com'
                && $data['to'][0]['email'] === 'customer@example.com'
                && str_contains($data['htmlContent'], '123456');
        });
    }

    public function test_booking_confirmation_is_sent_through_brevo_api(): void
    {
        $user = new User([
            'name' => 'Nguyễn Văn A',
            'email' => 'customer@example.com',
        ]);

        $court = new Court([
            'name' => 'Sân kiểm tra',
            'price' => 90000,
        ]);

        $booking = new Booking([
            'booking_date' => '2026-09-06',
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'total_price' => 90000,
        ]);

        $booking->id = 25;
        $booking->setRelation('user', $user);
        $booking->setRelation('court', $court);

        app(TransactionalMailService::class)
            ->sendBookingConfirmation($booking);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $data['to'][0]['email'] === 'customer@example.com'
                && $data['subject'] === 'Xác nhận đặt sân thành công - Phiếu #25'
                && str_contains($data['htmlContent'], 'Sân kiểm tra')
                && str_contains($data['textContent'], '90.000đ');
        });
    }
}
