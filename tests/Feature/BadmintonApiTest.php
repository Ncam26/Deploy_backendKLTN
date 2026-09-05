<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmedMail;
use App\Models\Court;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BadmintonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_api_is_working(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['message' => 'Laravel API đang hoạt động.']);
    }

    public function test_user_can_register(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Nguyễn Văn A',
            'email' => 'nguyenvana@example.com',
            'phone' => '0901234567',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertCreated()
            ->assertJsonStructure(['message', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'nguyenvana@example.com',
            'role' => 'user',
        ]);
    }

    public function test_normal_user_cannot_open_admin_page(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_booking_cannot_overlap(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $court = Court::create([
            'name' => 'Sân kiểm thử',
            'area' => 'Quận 7',
            'price' => 85000,
            'rating' => 4.8,
            'status' => 'Hoạt động',
        ]);
        Sanctum::actingAs($user);

        $booking = [
            'court_id' => $court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '18:30',
            'end_time' => '19:30',
        ];

        $this->postJson('/api/bookings', $booking)->assertCreated();
        $this->postJson('/api/bookings', $booking)->assertStatus(409);
    }

    public function test_acb_transfer_returns_qr_and_requires_payment_before_confirmation(): void
    {
        config([
            'bank.name' => 'ACB',
            'bank.bin' => '970416',
            'bank.account_number' => '123456789',
            'bank.account_name' => 'NGUYEN VAN A',
        ]);

        $customer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $court = Court::create([
            'name' => 'Sân chuyển khoản',
            'area' => 'Quận 7',
            'price' => 90000,
            'status' => 'Hoạt động',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/bookings', [
            'court_id' => $court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'payment_method' => 'bank_transfer',
        ])->assertCreated()
            ->assertJsonPath('booking.payment_method', 'bank_transfer')
            ->assertJsonPath('bank_transfer.bank_name', 'ACB')
            ->assertJsonPath('bank_transfer.bank_bin', '970416')
            ->assertJsonPath('bank_transfer.amount', 90000);

        $bookingId = $response->json('booking.id');
        $qrUrl = $response->json('bank_transfer.qr_url');

        $this->assertStringContainsString(
            '970416-123456789-compact2.png',
            $qrUrl
        );

        $this->putJson(
            "/api/bookings/{$bookingId}/transfer-submitted"
        )->assertOk();

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'payment_status' => 'pending',
            'payment_method' => 'bank_transfer',
            'payment_expires_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/bookings/{$bookingId}/process",
            ['action' => 'confirm']
        )->assertStatus(422);

        $this->putJson(
            "/api/admin/bookings/{$bookingId}/process",
            ['action' => 'mark_paid']
        )->assertOk();

        Mail::fake();

        $this->putJson(
            "/api/admin/bookings/{$bookingId}/process",
            ['action' => 'confirm']
        )->assertOk()
            ->assertJsonPath('email_sent', true);

        Mail::assertSent(
            BookingConfirmedMail::class,
            function ($mail) use ($customer) {
                return $mail->hasTo($customer->email);
            }
        );
    }

    public function test_unpaid_bank_hold_expires_after_fifteen_minutes(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $court = Court::create([
            'name' => 'Sân kiểm tra hết hạn',
            'area' => 'Quận 7',
            'price' => 70000,
            'status' => 'Hoạt động',
        ]);
        $date = now()->addDay()->toDateString();

        $booking = Booking::create([
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'booking_date' => $date,
            'start_time' => '18:00',
            'end_time' => '19:00',
            'total_price' => 70000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'bank_transfer',
            'payment_expires_at' => now()->subMinute(),
        ]);

        $this->getJson(
            '/api/bookings/available-times?'.http_build_query([
                'court_id' => $court->id,
                'date' => $date,
            ])
        )->assertOk();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'payment_status' => 'expired',
            'action_reason' => 'Hết thời gian chuyển khoản ngân hàng',
        ]);
    }

    public function test_admin_confirms_booking_and_records_cash_payment(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $court = Court::create([
            'name' => 'Sân xác nhận',
            'area' => 'Quận 7',
            'price' => 80000,
            'status' => 'Hoạt động',
        ]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '19:00',
            'total_price' => 80000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'pay_at_court',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/bookings/{$booking->id}/process",
            ['action' => 'confirm']
        )->assertOk();

        $this->putJson(
            "/api/admin/bookings/{$booking->id}/process",
            ['action' => 'mark_paid']
        )->assertOk();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }

    public function test_maintenance_cannot_overlap_existing_booking(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $court = Court::create([
            'name' => 'Sân đang có lịch',
            'area' => 'Quận 7',
            'price' => 80000,
            'status' => 'Hoạt động',
        ]);
        $date = now()->addDay()->toDateString();

        Booking::create([
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'booking_date' => $date,
            'start_time' => '18:00',
            'end_time' => '19:00',
            'total_price' => 80000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'pay_at_court',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/courts/{$court->id}/maintenance",
            [
                'reason' => 'Sửa mặt sân',
                'start_date' => $date,
                'end_date' => $date,
            ]
        )->assertStatus(409);
    }
}
