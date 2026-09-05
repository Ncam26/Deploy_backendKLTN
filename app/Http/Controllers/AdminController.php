<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Equipment;
use App\Models\RepairTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /*
     * Trang tổng quan quản trị.
     */
    public function dashboard()
    {
        $this->expireOldBankTransfers();

        $today = now()->toDateString();

        $monthStart = now()
            ->startOfMonth()
            ->toDateString();

        $monthEnd = now()
            ->endOfMonth()
            ->toDateString();

        $todayBookings = Booking::whereDate(
            'booking_date',
            $today
        );

        $recentBookings = Booking::with([
            'user:id,name,email,phone',
            'court:id,code,name',
        ])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $todaySchedule = Booking::with([
            'user:id,name,phone',
            'court:id,code,name',
        ])
            ->whereDate('booking_date', $today)
            ->whereIn('status', [
                'pending',
                'confirmed',
                'in_use',
            ])
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'today' => [
                'total_bookings' =>
                    (clone $todayBookings)
                        ->where(
                            'status',
                            '!=',
                            'cancelled'
                        )
                        ->count(),

                'pending_bookings' =>
                    (clone $todayBookings)
                        ->where(
                            'status',
                            'pending'
                        )
                        ->count(),

                'confirmed_bookings' =>
                    (clone $todayBookings)
                        ->where(
                            'status',
                            'confirmed'
                        )
                        ->count(),

                'in_use_bookings' =>
                    (clone $todayBookings)
                        ->where(
                            'status',
                            'in_use'
                        )
                        ->count(),

                'completed_bookings' =>
                    (clone $todayBookings)
                        ->where(
                            'status',
                            'completed'
                        )
                        ->count(),

                'revenue' =>
                    (clone $todayBookings)
                        ->where(
                            'payment_status',
                            'paid'
                        )
                        ->where(
                            'status',
                            '!=',
                            'cancelled'
                        )
                        ->sum('total_price'),
            ],

            'month' => [
                'revenue' => Booking::whereBetween(
                    'booking_date',
                    [$monthStart, $monthEnd]
                )
                    ->where(
                        'payment_status',
                        'paid'
                    )
                    ->where(
                        'status',
                        '!=',
                        'cancelled'
                    )
                    ->sum('total_price'),

                'booking_count' =>
                    Booking::whereBetween(
                        'booking_date',
                        [$monthStart, $monthEnd]
                    )
                        ->where(
                            'status',
                            '!=',
                            'cancelled'
                        )
                        ->count(),
            ],

            'customers' => [
                'total' => User::where(
                    'role',
                    'user'
                )->count(),

                'new_today' => User::where(
                    'role',
                    'user'
                )
                    ->whereDate(
                        'created_at',
                        $today
                    )
                    ->count(),

                'blocked' => User::where(
                    'role',
                    'user'
                )
                    ->where(
                        'status',
                        'blocked'
                    )
                    ->count(),
            ],

            'courts' => [
                'total' => Court::count(),

                'active' => Court::where(
                    'status',
                    'Hoạt động'
                )->count(),

                'maintenance' => Court::where(
                    'status',
                    'Bảo trì'
                )->count(),
            ],

            'equipment' => [
                'total' => Equipment::where(
                    'is_active',
                    true
                )->count(),

                'repairing' => Equipment::where(
                    'is_active',
                    true
                )
                    ->where(
                        'status',
                        'Đang sửa'
                    )
                    ->count(),

                'problems' => Equipment::where(
                    'is_active',
                    true
                )
                    ->whereIn('status', [
                        'Hỏng',
                        'Cần bảo dưỡng',
                        'Đang sửa',
                    ])
                    ->count(),

                'overdue_repairs' =>
                    RepairTicket::where(
                        'status',
                        'in_progress'
                    )
                        ->whereDate(
                            'expected_end_date',
                            '<',
                            $today
                        )
                        ->count(),
            ],

            'today_schedule' => $todaySchedule,
            'recent_bookings' => $recentBookings,
        ]);
    }

    /*
     * Danh sách khách hàng.
     */
    public function customers(Request $request)
    {
        $customers = User::query()
            ->where('role', 'user')

            ->when(
                $request->search,
                function ($query, $search) {
                    $search = trim($search);

                    $query->where(
                        function ($item) use ($search) {
                            $item->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )

            ->when(
                $request->status,
                function ($query, $status) {
                    $query->where(
                        'status',
                        $status
                    );
                }
            )

            ->withCount('bookings')

            ->withCount([
                'bookings as cancelled_bookings_count'
                    => function ($query) {
                        $query->where(
                            'status',
                            'cancelled'
                        );
                    },
            ])

            ->withSum([
                'bookings as paid_total'
                    => function ($query) {
                        $query
                            ->where(
                                'payment_status',
                                'paid'
                            )
                            ->where(
                                'status',
                                '!=',
                                'cancelled'
                            );
                    },
            ], 'total_price')

            ->withMax([
                'bookings as last_booking_date',
            ], 'booking_date')

            ->orderByDesc('created_at')
            ->get();

        return response()->json($customers);
    }

    /*
     * Chi tiết một khách hàng.
     */
    public function customerDetail(User $user)
    {
        if ($user->role !== 'user') {
            return response()->json([
                'message' =>
                    'Tài khoản này không phải khách hàng.',
            ], 422);
        }

        $bookings = $user->bookings()
            ->with('court:id,code,name')
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->get();

        $summary = [
            'total_bookings' =>
                $bookings->count(),

            'completed_bookings' =>
                $bookings
                    ->where(
                        'status',
                        'completed'
                    )
                    ->count(),

            'cancelled_bookings' =>
                $bookings
                    ->where(
                        'status',
                        'cancelled'
                    )
                    ->count(),

            'paid_total' =>
                $bookings
                    ->where(
                        'payment_status',
                        'paid'
                    )
                    ->where(
                        'status',
                        '!=',
                        'cancelled'
                    )
                    ->sum('total_price'),

            'last_booking_date' =>
                optional(
                    $bookings->first()
                )->booking_date,
        ];

        return response()->json([
            'customer' => $user,
            'summary' => $summary,
            'bookings' => $bookings,
        ]);
    }

    /*
     * Khóa hoặc mở tài khoản khách hàng.
     */
    public function updateCustomerStatus(
        Request $request,
        User $user
    ) {
        if ($user->role !== 'user') {
            return response()->json([
                'message' =>
                    'Không được thay đổi trạng thái tài khoản quản trị.',
            ], 403);
        }

        $data = $request->validate(
            [
                'status' => [
                    'required',
                    Rule::in([
                        'active',
                        'blocked',
                    ]),
                ],
            ],
            [
                'status.required' =>
                    'Vui lòng chọn trạng thái tài khoản.',
                'status.in' =>
                    'Trạng thái tài khoản không hợp lệ.',
            ]
        );

        $user->update([
            'status' => $data['status'],
        ]);

        /*
         * Nếu khóa tài khoản thì xóa token,
         * khách đang đăng nhập sẽ phải thoát.
         */
        if ($data['status'] === 'blocked') {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' =>
                $data['status'] === 'blocked'
                    ? 'Đã khóa tài khoản khách hàng.'
                    : 'Đã mở lại tài khoản khách hàng.',

            'customer' => $user->fresh(),
        ]);
    }

    /*
     * Các số liệu chung của báo cáo.
     */
    public function reportSummary(Request $request)
    {
        [$startDate, $endDate] =
            $this->getReportPeriod($request);

        $baseBookings = Booking::whereBetween(
            'booking_date',
            [$startDate, $endDate]
        );

        $paidRevenue = Booking::whereBetween(
            'booking_date',
            [$startDate, $endDate]
        )
            ->where(
                'payment_status',
                'paid'
            )
            ->where(
                'status',
                '!=',
                'cancelled'
            )
            ->sum('total_price');

        $repairCost = RepairTicket::where(
            'status',
            'completed'
        )
            ->whereBetween(
                DB::raw('DATE(completed_at)'),
                [$startDate, $endDate]
            )
            ->sum('actual_cost');

        $totalBookings =
            (clone $baseBookings)->count();

        $cancelledBookings =
            (clone $baseBookings)
                ->where(
                    'status',
                    'cancelled'
                )
                ->count();

        $completedBookings =
            (clone $baseBookings)
                ->where(
                    'status',
                    'completed'
                )
                ->count();

        $cancelRate = $totalBookings > 0
            ? round(
                (
                    $cancelledBookings /
                    $totalBookings
                ) * 100,
                1
            )
            : 0;

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],

            'summary' => [
                'revenue' =>
                    (int) $paidRevenue,

                'repair_cost' =>
                    (int) $repairCost,

                'temporary_profit' =>
                    (int) (
                        $paidRevenue -
                        $repairCost
                    ),

                'total_bookings' =>
                    $totalBookings,

                'completed_bookings' =>
                    $completedBookings,

                'cancelled_bookings' =>
                    $cancelledBookings,

                'cancel_rate' =>
                    $cancelRate,

                'unique_customers' =>
                    (clone $baseBookings)
                        ->distinct('user_id')
                        ->count('user_id'),
            ],
        ]);
    }

    /*
     * Doanh thu theo ngày.
     */
    public function revenue(Request $request)
    {
        [$startDate, $endDate] =
            $this->getReportPeriod($request);

        $report = Booking::selectRaw(
            '
                booking_date AS date,
                COUNT(*) AS booking_count,
                SUM(total_price) AS revenue
            '
        )
            ->whereBetween(
                'booking_date',
                [$startDate, $endDate]
            )
            ->where(
                'payment_status',
                'paid'
            )
            ->where(
                'status',
                '!=',
                'cancelled'
            )
            ->groupBy('booking_date')
            ->orderBy('booking_date')
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'items' => $report,
        ]);
    }

    /*
     * Thống kê theo từng sân.
     */
    public function courtReport(Request $request)
    {
        [$startDate, $endDate] =
            $this->getReportPeriod($request);

        $items = Booking::query()
            ->join(
                'courts',
                'courts.id',
                '=',
                'bookings.court_id'
            )
            ->whereBetween(
                'bookings.booking_date',
                [$startDate, $endDate]
            )
            ->selectRaw(
                '
                    courts.id,
                    courts.code,
                    courts.name,
                    COUNT(bookings.id)
                        AS booking_count,
                    SUM(
                        CASE
                            WHEN bookings.payment_status = "paid"
                            AND bookings.status != "cancelled"
                            THEN bookings.total_price
                            ELSE 0
                        END
                    ) AS revenue
                '
            )
            ->groupBy(
                'courts.id',
                'courts.code',
                'courts.name'
            )
            ->orderByDesc('booking_count')
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'items' => $items,
        ]);
    }

    /*
     * Các giờ được đặt nhiều nhất.
     */
    public function peakHours(Request $request)
    {
        [$startDate, $endDate] =
            $this->getReportPeriod($request);

        $items = Booking::selectRaw(
            '
                TIME_FORMAT(
                    start_time,
                    "%H:%i"
                ) AS start_time,
                COUNT(*) AS booking_count
            '
        )
            ->whereBetween(
                'booking_date',
                [$startDate, $endDate]
            )
            ->where(
                'status',
                '!=',
                'cancelled'
            )
            ->groupBy('start_time')
            ->orderByDesc('booking_count')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'items' => $items,
        ]);
    }

    /*
     * Xuất danh sách giao dịch thành CSV.
     */
    public function exportReport(Request $request)
    {
        [$startDate, $endDate] =
            $this->getReportPeriod($request);

        $bookings = Booking::with([
            'user:id,name,email,phone',
            'court:id,code,name',
        ])
            ->whereBetween(
                'booking_date',
                [$startDate, $endDate]
            )
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        $fileName =
            "bao-cao-{$startDate}-{$endDate}.csv";

        return response()->streamDownload(
            function () use ($bookings) {
                $file = fopen(
                    'php://output',
                    'w'
                );

                /*
                 * BOM giúp Excel đọc đúng tiếng Việt.
                 */
                fwrite($file, "\xEF\xBB\xBF");

                fputcsv($file, [
                    'Mã phiếu',
                    'Khách hàng',
                    'Email',
                    'Số điện thoại',
                    'Sân',
                    'Ngày đặt',
                    'Giờ bắt đầu',
                    'Giờ kết thúc',
                    'Tổng tiền',
                    'Trạng thái',
                    'Thanh toán',
                ], ';');

                foreach ($bookings as $booking) {
                    fputcsv($file, [
                        $booking->id,
                        $booking->user?->name,
                        $booking->user?->email,
                        $booking->user?->phone,
                        $booking->court?->name,
                        $booking->booking_date
                            ->format('Y-m-d'),
                        substr(
                            $booking->start_time,
                            0,
                            5
                        ),
                        substr(
                            $booking->end_time,
                            0,
                            5
                        ),
                        $booking->total_price,
                        $booking->status,
                        $booking->payment_status,
                    ], ';');
                }

                fclose($file);
            },
            $fileName,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    /*
     * Kiểm tra khoảng ngày báo cáo.
     */
    private function getReportPeriod(
        Request $request
    ): array {
        $data = $request->validate([
            'start_date' => [
                'nullable',
                'date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        $startDate =
            $data['start_date'] ??
            now()
                ->subDays(29)
                ->toDateString();

        $endDate =
            $data['end_date'] ??
            now()->toDateString();

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($start->diffInDays($end) > 366) {
            abort(
                422,
                'Chỉ được xem báo cáo tối đa 366 ngày.'
            );
        }

        return [
            $start->toDateString(),
            $end->toDateString(),
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

}
