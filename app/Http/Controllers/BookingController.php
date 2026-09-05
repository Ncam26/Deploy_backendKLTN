<?php

namespace App\Http\Controllers;

use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Court;
use App\Models\Notification;
use App\Models\RepairTicket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function availableTimes(Request $request)
{
        $this->expireOldBankTransfers();

        $data = $request->validate([
        'court_id' => 'required|exists:courts,id',
        'date' => [
            'required',
            'date',
            'after_or_equal:today',
            'before_or_equal:'.now()
                ->addDays(30)
                ->format('Y-m-d'),
        ],
    ]);

    $court = Court::findOrFail(
        $data['court_id']
    );

    $openingTime = substr(
        (string) (
            $court->opening_time ?: '06:00'
        ),
        0,
        5
    );

    $closingTime = substr(
        (string) (
            $court->closing_time ?: '22:00'
        ),
        0,
        5
    );

    $start = Carbon::createFromFormat(
        'Y-m-d H:i',
        $data['date'].' '.$openingTime
    );

    $closing = Carbon::createFromFormat(
        'Y-m-d H:i',
        $data['date'].' '.$closingTime
    );

    $bookings = Booking::where(
        'court_id',
        $court->id
    )
        ->whereDate(
            'booking_date',
            $data['date']
        )
        ->whereIn('status', [
            'pending',
            'confirmed',
            'in_use',
        ])
        ->get([
            'start_time',
            'end_time',
            'status',
            'payment_status',
            'payment_method',
        ]);

    $maintenanceStart =
        $court->maintenance_start
            ? $court->maintenance_start
                ->format('Y-m-d')
            : null;

    $maintenanceEnd =
        $court->maintenance_end
            ? $court->maintenance_end
                ->format('Y-m-d')
            : null;

    $hasBlockingRepair = RepairTicket::where(
        'court_id',
        $court->id
    )
        ->where('status', 'in_progress')
        ->where('blocks_court', true)
        ->whereDate('start_date', '<=', $data['date'])
        ->whereDate('expected_end_date', '>=', $data['date'])
        ->exists();

    $isMaintenanceDate =
        $hasBlockingRepair ||
        $court->status === 'Bảo trì' &&
        (
            ! $maintenanceStart ||
            ! $maintenanceEnd ||
            (
                $data['date'] >=
                    $maintenanceStart &&
                $data['date'] <=
                    $maintenanceEnd
            )
        );

    $slots = [];

    while ($start->lt($closing)) {
        $slotEnd = $start
            ->copy()
            ->addHour();

        if ($slotEnd->gt($closing)) {
            break;
        }

        $startTime = $start->format('H:i');
        $endTime = $slotEnd->format('H:i');

        $blockingBooking = $bookings->first(
            function ($booking) use (
                $startTime,
                $endTime
            ) {
                $bookingStart = substr(
                    (string) $booking->start_time,
                    0,
                    5
                );

                $bookingEnd = substr(
                    (string) $booking->end_time,
                    0,
                    5
                );

                return (
                    $startTime < $bookingEnd &&
                    $endTime > $bookingStart
                );
            }
        );

        $hasBooking = $blockingBooking !== null;

        $isTooLate = $start->lt(
            now()->addMinutes(30)
        );

        $available =
            ! $isMaintenanceDate &&
            ! $hasBooking &&
            ! $isTooLate;

        $reason = null;

        if ($isMaintenanceDate) {
            $reason = 'Sân bảo trì';
        } elseif ($hasBooking) {
            if (
                $blockingBooking->payment_method ===
                    'bank_transfer' &&
                $blockingBooking->payment_status ===
                    'unpaid'
            ) {
                $reason = 'Đang giữ chỗ thanh toán';
            } elseif (
                $blockingBooking->payment_method ===
                    'bank_transfer' &&
                $blockingBooking->payment_status ===
                    'pending'
            ) {
                $reason = 'Chờ kiểm tra chuyển khoản';
            } elseif (
                $blockingBooking->status === 'pending'
            ) {
                $reason = 'Đang chờ xác nhận';
            } else {
                $reason = 'Đã có người đặt';
            }
        } elseif ($isTooLate) {
            $reason = 'Đã qua giờ đặt';
        }

        $slots[] = [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'available' => $available,
            'reason' => $reason,
        ];

        $start->addHour();
    }

    return response()->json([
        'court_id' => $court->id,
        'date' => $data['date'],
        'opening_time' => $openingTime,
        'closing_time' => $closingTime,
        'slots' => $slots,
    ]);
}
    public function store(Request $request)
    {
        $this->expireOldBankTransfers();

        $data = $request->validate([
            'court_id' => 'required|exists:courts,id',
            'booking_date' => [
                'required',
                'date',
                'after_or_equal:today',
                'before_or_equal:'.now()
                    ->addDays(30)
                    ->format('Y-m-d'),
            ],
            'start_time' => 'required|date_format:H:i',
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
            ],
            'note' => 'nullable|string|max:255',
            'payment_method' => [
                'nullable',
                Rule::in([
                    'pay_at_court',
                    'bank_transfer',
                ]),
            ],
        ]);

        $paymentMethod =
            $data['payment_method'] ??
            'pay_at_court';

        if (
            $paymentMethod === 'bank_transfer' &&
            ! $this->bankTransferConfigured()
        ) {
            abort(
                503,
                'Chưa cấu hình số tài khoản và tên chủ tài khoản ACB.'
            );
        }

        $booking = DB::transaction(function () use (
            $request,
            $data,
            $paymentMethod
        ) {
            $court = Court::where(
                'id',
                $data['court_id']
            )
                ->lockForUpdate()
                ->firstOrFail();

            $this->checkCourtAndTime(
                $court,
                $data['booking_date'],
                $data['start_time'],
                $data['end_time']
            );

            $totalPrice = $this->calculatePrice(
                $court,
                $data['start_time'],
                $data['end_time']
            );

            $booking = Booking::create([
                'user_id' => $request->user()->id,
                'court_id' => $court->id,
                'booking_date' => $data['booking_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'total_price' => $totalPrice,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $paymentMethod,
                'payment_expires_at' =>
                    $paymentMethod === 'bank_transfer'
                        ? now()->addMinutes(15)
                        : null,
                'note' => $data['note'] ?? null,
            ]);

            $notificationMessage =
                $paymentMethod === 'bank_transfer'
                    ? 'Phiếu đang giữ chỗ 15 phút. Vui lòng chuyển khoản đúng số tiền và nội dung.'
                    : 'Yêu cầu đang chờ quản trị viên xác nhận. Bạn sẽ thanh toán trực tiếp tại sân.';

            Notification::create([
                'user_id' => $request->user()->id,
                'booking_id' => $booking->id,
                'title' => 'Đã tiếp nhận yêu cầu đặt sân',
                'message' => $notificationMessage,
                'send_at' => now(),
            ]);

            return $booking;
        });

        $response = [
            'message' =>
                $paymentMethod === 'bank_transfer'
                    ? 'Đã giữ sân trong 15 phút. Vui lòng quét QR để chuyển khoản.'
                    : 'Đã gửi yêu cầu đặt sân. Vui lòng chờ quản trị viên xác nhận.',
            'booking' => $booking->load('court'),
        ];

        if ($paymentMethod === 'bank_transfer') {
            $response['bank_transfer'] =
                $this->bankTransferData($booking);
        }

        return response()->json($response, 201);
    }

    public function myBookings(Request $request)
    {
        $this->expireOldBankTransfers();

        $bookings = Booking::with(
            'court:id,name,area'
        )
            ->where(
                'user_id',
                $request->user()->id
            )
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->get();

        return response()->json($bookings);
    }

    public function cancel(
        Request $request,
        Booking $booking
    ) {
        if (
            $booking->user_id !==
            $request->user()->id
        ) {
            return response()->json([
                'message' => 'Bạn không thể hủy lịch này.',
            ], 403);
        }

        if (! in_array($booking->status, [
            'pending',
            'confirmed',
        ])) {
            return response()->json([
                'message' => 'Phiếu này không thể hủy.',
            ], 422);
        }

        $booking->update([
            'status' => 'cancelled',
            'action_reason' => 'Khách hàng tự hủy',
        ]);

        return response()->json([
            'message' => 'Hủy lịch thành công.',
        ]);
    }

    public function submitBankTransfer(
        Request $request,
        Booking $booking
    ) {
        if ($booking->user_id !== $request->user()->id) {
            abort(403, 'Bạn không thể cập nhật phiếu này.');
        }

        $this->expireOldBankTransfers();
        $booking->refresh();

        if ($booking->payment_method !== 'bank_transfer') {
            abort(422, 'Phiếu này không thanh toán bằng chuyển khoản.');
        }

        if ($booking->status !== 'pending') {
            abort(422, 'Phiếu này không còn chờ chuyển khoản.');
        }

        if ($booking->payment_status === 'paid') {
            abort(422, 'Phiếu này đã được xác nhận thanh toán.');
        }

        $booking->update([
            'payment_status' => 'pending',
            'payment_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Đã báo chuyển khoản. Vui lòng chờ admin kiểm tra tiền vào.',
            'booking' => $booking->fresh(),
        ]);
    }

    public function index(Request $request)
    {
        $this->expireOldBankTransfers();

        $bookings = Booking::with([
            'user:id,name,email,phone',
            'court:id,name,price',
        ])
            ->when(
                $request->status,
                function ($query, $status) {
                    $query->where(
                        'status',
                        $status
                    );
                }
            )
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->get();

        return response()->json($bookings);
    }

    public function show(Booking $booking)
    {
        return response()->json(
            $booking->load([
                'user:id,name,email,phone',
                'court:id,name,price',
                'histories.admin:id,name',
            ])
        );
    }

    public function process(
        Request $request,
        Booking $booking
    ) {
        $data = $request->validate([
            'action' => [
                'required',
                Rule::in([
                    'confirm',
                    'reschedule',
                    'start',
                    'complete',
                    'cancel',
                    'mark_paid',
                ]),
            ],
            'court_id' => 'nullable|exists:courts,id',
            'booking_date' => 'nullable|date|after_or_equal:today',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ]);

        $updatedBooking = DB::transaction(function () use (
            $request,
            $booking,
            $data
        ) {
            $booking = Booking::where(
                'id',
                $booking->id
            )
                ->lockForUpdate()
                ->firstOrFail();

            $oldData = [
                'court_id' => $booking->court_id,
                'booking_date' => $booking->booking_date
                    ->format('Y-m-d'),
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'status' => $booking->status,
            ];

            $action = $data['action'];

            if ($action === 'confirm') {
                $this->confirmBooking($booking);
            }

            if ($action === 'reschedule') {
                $this->rescheduleBooking(
                    $booking,
                    $data
                );
            }

            if ($action === 'start') {
                $this->startBooking($booking);
            }

            if ($action === 'complete') {
                $this->completeBooking($booking);
            }

            if ($action === 'cancel') {
                $this->cancelBooking(
                    $booking,
                    $data['reason'] ?? null
                );
            }

            if ($action === 'mark_paid') {
                $this->markBookingPaid($booking);
            }

            $booking->refresh();

            BookingHistory::create([
                'booking_id' => $booking->id,
                'admin_id' => $request->user()->id,
                'action' => $action,
                'reason' => $data['reason'] ?? null,

                'old_court_id' => $oldData['court_id'],
                'new_court_id' => $booking->court_id,

                'old_booking_date' => $oldData['booking_date'],
                'new_booking_date' => $booking->booking_date,

                'old_start_time' => $oldData['start_time'],
                'new_start_time' => $booking->start_time,

                'old_end_time' => $oldData['end_time'],
                'new_end_time' => $booking->end_time,

                'old_status' => $oldData['status'],
                'new_status' => $booking->status,
            ]);

            return $booking;
        });

        $updatedBooking->load([
            'user:id,name,email,phone',
            'court:id,name,price',
        ]);

        $message = 'Đã xử lý phiếu đặt sân.';
        $emailSent = false;

        if (
            $data['action'] === 'confirm' &&
            $updatedBooking->payment_method === 'bank_transfer' &&
            $updatedBooking->payment_status === 'paid'
        ) {
            try {
                Mail::to($updatedBooking->user->email)
                    ->send(
                        new BookingConfirmedMail(
                            $updatedBooking
                        )
                    );

                $emailSent = true;
                $message = 'Đã xác nhận phiếu và gửi email cho khách hàng.';
            } catch (\Throwable $error) {
                Log::error(
                    'Không gửi được email xác nhận đặt sân.',
                    [
                        'booking_id' => $updatedBooking->id,
                        'error' => $error->getMessage(),
                    ]
                );

                $message = 'Đã xác nhận phiếu nhưng chưa gửi được email. Vui lòng kiểm tra cấu hình Gmail.';
            }
        }

        return response()->json([
            'message' => $message,
            'email_sent' => $emailSent,
            'booking' => $updatedBooking,
        ]);
    }

    private function confirmBooking(Booking $booking)
    {
        if ($booking->status !== 'pending') {
            abort(422, 'Chỉ xác nhận được phiếu đang chờ.');
        }

        if (
            $booking->payment_method === 'bank_transfer' &&
            $booking->payment_status !== 'paid'
        ) {
            abort(
                422,
                'Chưa thể xác nhận vì chưa kiểm tra được tiền chuyển khoản.'
            );
        }

        $court = Court::where(
            'id',
            $booking->court_id
        )
            ->lockForUpdate()
            ->firstOrFail();

        $this->checkCourtAndTime(
            $court,
            $booking->booking_date->format('Y-m-d'),
            $booking->start_time,
            $booking->end_time,
            $booking->id
        );

        $booking->update([
            'status' => 'confirmed',
            'action_reason' => null,
        ]);
    }

    private function rescheduleBooking(
        Booking $booking,
        array $data
    ) {
        if (! in_array($booking->status, [
            'pending',
            'confirmed',
        ])) {
            abort(
                422,
                'Trạng thái hiện tại không được đổi lịch.'
            );
        }

        foreach ([
            'court_id',
            'booking_date',
            'start_time',
            'end_time',
        ] as $field) {
            if (empty($data[$field])) {
                abort(
                    422,
                    'Vui lòng nhập đầy đủ sân, ngày và giờ mới.'
                );
            }
        }

        if (empty($data['reason'])) {
            abort(
                422,
                'Vui lòng nhập lý do đổi lịch.'
            );
        }

        $court = Court::where(
            'id',
            $data['court_id']
        )
            ->lockForUpdate()
            ->firstOrFail();

        $this->checkCourtAndTime(
            $court,
            $data['booking_date'],
            $data['start_time'],
            $data['end_time'],
            $booking->id
        );

        $booking->update([
            'court_id' => $court->id,
            'booking_date' => $data['booking_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'total_price' => $this->calculatePrice(
                $court,
                $data['start_time'],
                $data['end_time']
            ),
            'action_reason' => $data['reason'],
        ]);
    }

    private function startBooking(Booking $booking)
    {
        if ($booking->status !== 'confirmed') {
            abort(
                422,
                'Chỉ bắt đầu được phiếu đã xác nhận.'
            );
        }

        if (
            $booking->booking_date->format('Y-m-d')
            !== now()->format('Y-m-d')
        ) {
            abort(
                422,
                'Chỉ bắt đầu phiếu trong đúng ngày đặt sân.'
            );
        }

        if ($booking->payment_status !== 'paid') {
            abort(
                422,
                'Vui lòng xác nhận khách đã thanh toán tại sân trước khi bắt đầu.'
            );
        }

        $booking->update([
            'status' => 'in_use',
        ]);
    }

    private function completeBooking(Booking $booking)
    {
        if (! in_array($booking->status, [
            'confirmed',
            'in_use',
        ])) {
            abort(
                422,
                'Phiếu này chưa thể hoàn thành.'
            );
        }

        $booking->update([
            'status' => 'completed',
        ]);
    }

    private function cancelBooking(
        Booking $booking,
        ?string $reason
    ) {
        if (! in_array($booking->status, [
            'pending',
            'confirmed',
        ])) {
            abort(
                422,
                'Trạng thái hiện tại không được hủy.'
            );
        }

        if (! $reason) {
            abort(
                422,
                'Vui lòng nhập lý do hủy phiếu.'
            );
        }

        $booking->update([
            'status' => 'cancelled',
            'action_reason' => $reason,
        ]);
    }

    private function markBookingPaid(Booking $booking)
    {
        if ($booking->status === 'cancelled') {
            abort(422, 'Không thể thu tiền cho phiếu đã hủy.');
        }

        if ($booking->payment_status === 'paid') {
            abort(422, 'Phiếu này đã được xác nhận thanh toán.');
        }

        $booking->update([
            'payment_status' => 'paid',
            'payment_method' =>
                $booking->payment_method ?: 'pay_at_court',
            'payment_expires_at' => null,
        ]);
    }

    private function bankTransferConfigured(): bool
    {
        return
            filled(config('bank.bin')) &&
            filled(config('bank.account_number')) &&
            filled(config('bank.account_name'));
    }

    private function bankTransferData(Booking $booking): array
    {
        $transferContent =
            'DAT SAN PHIEU '.$booking->id;

        $query = http_build_query([
            'amount' => $booking->total_price,
            'addInfo' => $transferContent,
            'accountName' => config('bank.account_name'),
        ]);

        $qrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?%s',
            rawurlencode(config('bank.bin')),
            rawurlencode(config('bank.account_number')),
            $query
        );

        return [
            'booking_id' => $booking->id,
            'bank_name' => config('bank.name'),
            'bank_bin' => config('bank.bin'),
            'account_number' => config('bank.account_number'),
            'account_name' => config('bank.account_name'),
            'amount' => $booking->total_price,
            'transfer_content' => $transferContent,
            'qr_url' => $qrUrl,
            'expires_at' => optional(
                $booking->payment_expires_at
            )->toISOString(),
        ];
    }

    private function expireOldBankTransfers(): void
    {
        Booking::where('status', 'pending')
            ->where('payment_method', 'bank_transfer')
            ->where('payment_status', 'unpaid')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->update([
                'status' => 'cancelled',
                'payment_status' => 'expired',
                'action_reason' => 'Hết thời gian chuyển khoản ngân hàng',
            ]);
    }

    private function checkCourtAndTime(
        Court $court,
        string $date,
        string $startTime,
        string $endTime,
        ?int $ignoreBookingId = null
    ) {
        if ($endTime <= $startTime) {
            abort(
                422,
                'Giờ kết thúc phải sau giờ bắt đầu.'
            );
        }

        $bookingDateTime = Carbon::parse(
            $date.' '.$startTime
        );

        if (
            $bookingDateTime->lessThan(
                now()->addMinutes(30)
            )
        ) {
            abort(
                422,
                'Phải đặt sân trước ít nhất 30 phút.'
            );
        }

        if (
            $court->opening_time &&
            $startTime < substr(
                $court->opening_time,
                0,
                5
            )
        ) {
            abort(
                422,
                'Giờ đặt nằm ngoài giờ mở cửa.'
            );
        }

        if (
            $court->closing_time &&
            $endTime > substr(
                $court->closing_time,
                0,
                5
            )
        ) {
            abort(
                422,
                'Giờ đặt nằm ngoài giờ đóng cửa.'
            );
        }

        $hasBlockingRepair = RepairTicket::where(
            'court_id',
            $court->id
        )
            ->where('status', 'in_progress')
            ->where('blocks_court', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('expected_end_date', '>=', $date)
            ->exists();

        $isMaintenanceDate =
            $hasBlockingRepair ||
            $court->status === 'Bảo trì' &&
            (
                ! $court->maintenance_start ||
                ! $court->maintenance_end ||
                (
                    $date >= $court->maintenance_start
                        ->format('Y-m-d') &&
                    $date <= $court->maintenance_end
                        ->format('Y-m-d')
                )
            );

        if ($isMaintenanceDate) {
            abort(
                422,
                'Sân đang bảo trì trong ngày đã chọn.'
            );
        }

        $conflictQuery = Booking::where(
            'court_id',
            $court->id
        )
            ->whereDate('booking_date', $date)
            ->whereIn('status', [
                'pending',
                'confirmed',
                'in_use',
            ])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($ignoreBookingId) {
            $conflictQuery->where(
                'id',
                '!=',
                $ignoreBookingId
            );
        }

        if ($conflictQuery->exists()) {
            abort(
                409,
                'Sân đã có người đặt trong khung giờ này.'
            );
        }
    }

    private function calculatePrice(
        Court $court,
        string $startTime,
        string $endTime
    ) {
        $start = Carbon::createFromFormat(
            'H:i',
            substr($startTime, 0, 5)
        );

        $end = Carbon::createFromFormat(
            'H:i',
            substr($endTime, 0, 5)
        );

        $minutes = $start->diffInMinutes($end);

        return (int) round(
            $court->price * ($minutes / 60)
        );
    }

}
