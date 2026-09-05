<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Equipment;
use App\Models\RepairTicket;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EquipmentController extends Controller
{
    private array $categories = [
        'Gắn sân',
        'Chiếu sáng',
        'Cho thuê',
        'Cơ sở',
        'Cơ sở vật chất',
        'Điện - nước',
        'Khác',
    ];

    public function index(Request $request)
    {
        $equipment = Equipment::with([
            'court:id,code,name',
            'currentRepair',
        ])
            ->when(
                $request->court_id,
                function ($query, $courtId) {
                    $query->where(
                        'court_id',
                        $courtId
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
            ->when(
                $request->search,
                function ($query, $search) {
                    $query->where(
                        function ($item) use ($search) {
                            $item->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->get();

        return response()->json($equipment);
    }

    public function store(Request $request)
    {
        $request->merge([
            'code' => strtoupper(
                trim((string) $request->code)
            ),
        ]);

        $data = $request->validate(
            [
                'name' => [
                    'required',
                    'string',
                    'max:120',
                ],
                'code' => [
                    'required',
                    'string',
                    'max:50',
                    'unique:equipment,code',
                ],
                'court_id' => [
                    'nullable',
                    'exists:courts,id',
                ],
                'category' => [
                    'required',
                    Rule::in($this->categories),
                ],
                'purchase_date' => [
                    'nullable',
                    'date',
                    'before_or_equal:today',
                ],
                'note' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ],
            [
                'name.required' =>
                    'Vui lòng nhập tên thiết bị.',
                'code.required' =>
                    'Vui lòng nhập mã thiết bị.',
                'code.unique' =>
                    'Mã thiết bị đã tồn tại.',
                'category.required' =>
                    'Vui lòng chọn loại thiết bị.',
                'purchase_date.before_or_equal' =>
                    'Ngày mua không được lớn hơn ngày hiện tại.',
            ]
        );

        $equipment = Equipment::create([
            ...$data,
            'status' => 'Tốt',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Thêm thiết bị thành công.',
            'equipment' => $equipment->load([
                'court:id,code,name',
                'currentRepair',
            ]),
        ], 201);
    }

    public function update(
        Request $request,
        Equipment $equipment
    ) {
        $request->merge([
            'code' => strtoupper(
                trim((string) $request->code)
            ),
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(
                    'equipment',
                    'code'
                )->ignore($equipment->id),
            ],
            'court_id' => [
                'nullable',
                'exists:courts,id',
            ],
            'category' => [
                'required',
                Rule::in($this->categories),
            ],
            'purchase_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'note' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'Tốt',
                    'Cần bảo dưỡng',
                    'Hỏng',
                    'Đang sửa',
                ]),
            ],
        ]);

        $activeRepair = $equipment
            ->repairTickets()
            ->where('status', 'in_progress')
            ->first();

        if (
            $activeRepair &&
            (string) $equipment->court_id !==
                (string) ($data['court_id'] ?? '')
        ) {
            return response()->json([
                'message' =>
                    'Không thể chuyển sân khi thiết bị đang được sửa.',
            ], 422);
        }

        if ($activeRepair) {
            unset($data['status']);
        } elseif (! isset($data['status'])) {
            $data['status'] = $equipment->status;
        }

        /*
        * Không cho đổi mã tài sản sau khi tạo.
        */
        $data['code'] = $equipment->code;

        $equipment->update($data);
            /*
            * Nếu thiết bị đang sửa và có khóa sân,
            * cập nhật lại thông báo bảo trì của sân.
            */
            if (
                $activeRepair &&
                $activeRepair->blocks_court &&
                $equipment->court_id
            ) {
                $freshEquipment = $equipment
                    ->fresh();

                $this->syncCourtMaintenance(
                    $freshEquipment->court
                );
            }

            return response()->json([
            'message' => 'Cập nhật thiết bị thành công.',
            'equipment' => $equipment
                ->fresh()
                ->load([
                    'court:id,code,name',
                    'currentRepair',
                ]),
        ]);
    }

    public function repair(
        Request $request,
        Equipment $equipment
    ) {
        if (! $equipment->is_active) {
            return response()->json([
                'message' =>
                    'Thiết bị đã ngừng sử dụng.',
            ], 422);
        }

        $data = $request->validate(
            [
                'reason' => [
                    'required',
                    'string',
                    'max:200',
                ],
                'description' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
                'start_date' => [
                    'required',
                    'date',
                ],
                'end_date' => [
                    'required',
                    'date',
                    'after_or_equal:start_date',
                ],
                'estimated_cost' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],
                'technician' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
                'blocks_court' => [
                    'required',
                    'boolean',
                ],
            ],
            [
                'reason.required' =>
                    'Vui lòng nhập lý do hỏng.',
                'start_date.required' =>
                    'Vui lòng chọn ngày bắt đầu.',
                'end_date.required' =>
                    'Vui lòng chọn ngày dự kiến xong.',
                'end_date.after_or_equal' =>
                    'Ngày dự kiến xong phải sau ngày bắt đầu.',
                'estimated_cost.min' =>
                    'Chi phí không được là số âm.',
            ]
        );

        if (
            $data['blocks_court'] &&
            ! $equipment->court_id
        ) {
            return response()->json([
                'message' =>
                    'Thiết bị chưa được gán sân nên không thể khóa sân.',
            ], 422);
        }

        $ticket = DB::transaction(
            function () use (
                $data,
                $equipment
            ) {
                if (
                    $data['blocks_court'] &&
                    $equipment->court_id
                ) {
                    /*
                     * BookingController cũng khóa dòng sân.
                     * Vì vậy đặt sân và sửa chữa không thể
                     * cùng vượt qua kiểm tra một lúc.
                     */
                    DB::table('courts')
                        ->where(
                            'id',
                            $equipment->court_id
                        )
                        ->lockForUpdate()
                        ->first();

                    $affectedBookings =
                        Booking::with(
                            'user:id,name,email,phone'
                        )
                            ->where(
                                'court_id',
                                $equipment->court_id
                            )
                            ->whereBetween(
                                'booking_date',
                                [
                                    $data['start_date'],
                                    $data['end_date'],
                                ]
                            )
                            ->whereIn(
                                'status',
                                [
                                    'pending',
                                    'confirmed',
                                    'in_use',
                                ]
                            )
                            ->orderBy('booking_date')
                            ->orderBy('start_time')
                            ->get();

                    if (
                        $affectedBookings->isNotEmpty()
                    ) {
                        throw new HttpResponseException(
                            response()->json([
                                'message' =>
                                    'Không thể khóa sân vì đang có lịch đặt trong thời gian sửa.',
                                'affected_count' =>
                                    $affectedBookings->count(),
                                'affected_bookings' =>
                                    $affectedBookings,
                            ], 409)
                        );
                    }
                }

                $ticket = $equipment
                    ->repairTickets()
                    ->where(
                        'status',
                        'in_progress'
                    )
                    ->first();

                $ticketData = [
                    'court_id' =>
                        $equipment->court_id,
                    'reason' => $data['reason'],
                    'description' =>
                        $data['description'] ?? null,
                    'start_date' =>
                        $data['start_date'],
                    'expected_end_date' =>
                        $data['end_date'],
                    'technician' =>
                        $data['technician'] ?? null,
                    'estimated_cost' =>
                        $data['estimated_cost'] ?? 0,
                    'blocks_court' =>
                        $data['blocks_court'],
                    'status' => 'in_progress',
                ];

                if ($ticket) {
                    $ticket->update($ticketData);
                } else {
                    $ticket = $equipment
                        ->repairTickets()
                        ->create($ticketData);
                }

                /*
                 * Cập nhật các cột cũ để phần frontend
                 * hiện tại vẫn hoạt động trong lúc chuyển đổi.
                 */
                $equipment->update([
                    'status' => 'Đang sửa',
                    'repair_reason' =>
                        $data['reason'],
                    'repair_description' =>
                        $data['description'] ?? null,
                    'repair_start' =>
                        $data['start_date'],
                    'repair_end' =>
                        $data['end_date'],
                    'repair_cost' =>
                        $data['estimated_cost'] ?? 0,
                    'technician' =>
                        $data['technician'] ?? null,
                    'blocks_court' =>
                        $data['blocks_court'],
                ]);

                if ($equipment->court_id) {
                    $this->syncCourtMaintenance(
                        $equipment->court
                    );
                }

                return $ticket;
            }
        );

        return response()->json([
            'message' =>
                $data['blocks_court']
                    ? 'Đã lưu phiếu sửa và khóa sân trong thời gian sửa.'
                    : 'Đã lưu phiếu sửa. Sân vẫn hoạt động.',
            'ticket' => $ticket,
            'equipment' => $equipment
                ->fresh()
                ->load([
                    'court:id,code,name',
                    'currentRepair',
                ]),
        ]);
    }

    public function complete(
        Request $request,
        Equipment $equipment
    ) {
        $data = $request->validate([
            'actual_cost' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'completion_note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $ticket = $equipment
            ->repairTickets()
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if (! $ticket) {
            return response()->json([
                'message' =>
                    'Thiết bị không có phiếu sửa đang thực hiện.',
            ], 422);
        }

        DB::transaction(
            function () use (
                $data,
                $equipment,
                $ticket
            ) {
                if ($equipment->court_id) {
                    DB::table('courts')
                        ->where(
                            'id',
                            $equipment->court_id
                        )
                        ->lockForUpdate()
                        ->first();
                }

                $description =
                    $ticket->description;

                if (! empty(
                    $data['completion_note']
                )) {
                    $description = trim(
                        ($description
                            ? $description."\n\n"
                            : '').
                        'Kết quả: '.
                        $data['completion_note']
                    );
                }

                $ticket->update([
                    'description' => $description,
                    'actual_cost' =>
                        $data['actual_cost'] ??
                        $ticket->estimated_cost,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                $equipment->update([
                    'status' => 'Tốt',
                    'repair_reason' => null,
                    'repair_description' => null,
                    'repair_start' => null,
                    'repair_end' => null,
                    'repair_cost' => 0,
                    'technician' => null,
                    'blocks_court' => false,
                ]);

                if ($equipment->court_id) {
                    $this->syncCourtMaintenance(
                        $equipment->court
                    );
                }
            }
        );

        return response()->json([
            'message' =>
                'Đã hoàn tất sửa chữa và lưu vào lịch sử.',
            'equipment' => $equipment
                ->fresh()
                ->load([
                    'court:id,code,name',
                    'currentRepair',
                ]),
        ]);
    }

    public function history(
        Equipment $equipment
    ) {
        $tickets = $equipment
            ->repairTickets()
            ->with('court:id,code,name')
            ->latest('start_date')
            ->latest('id')
            ->get();

        return response()->json([
            'equipment' => $equipment->only([
                'id',
                'name',
                'code',
            ]),
            'tickets' => $tickets,
        ]);
    }

    public function retire(
        Equipment $equipment
    ) {
        $activeRepair = $equipment
            ->repairTickets()
            ->where('status', 'in_progress')
            ->exists();

        if ($activeRepair) {
            return response()->json([
                'message' =>
                    'Hãy hoàn tất phiếu sửa trước khi ngừng sử dụng thiết bị.',
            ], 422);
        }

        $equipment->update([
            'is_active' => false,
        ]);

        return response()->json([
            'message' =>
                'Đã chuyển thiết bị sang ngừng sử dụng.',
            'equipment' => $equipment,
        ]);
    }

    private function syncCourtMaintenance(
        Court $court
    ): void {
        /*
         * Tìm phiếu sửa khác vẫn đang khóa sân.
         */
        $blockingTicket = RepairTicket::with(
            'equipment:id,name'
        )
            ->where('court_id', $court->id)
            ->where('status', 'in_progress')
            ->where('blocks_court', true)
            ->orderByDesc('expected_end_date')
            ->first();

        if ($blockingTicket) {
            $equipmentName =
                $blockingTicket->equipment?->name ??
                'Thiết bị';

            $court->update([
                'status' => 'Bảo trì',
                'maintenance_reason' =>
                    "Thiết bị hỏng: {$equipmentName}",
                'maintenance_description' =>
                    $blockingTicket->description,
                'maintenance_start' =>
                    $blockingTicket->start_date,
                'maintenance_end' =>
                    $blockingTicket->expected_end_date,
                'maintenance_source' =>
                    'equipment',
            ]);

            return;
        }

        /*
         * Chỉ tự mở sân nếu sân bị khóa do thiết bị.
         * Không mở sân đang bảo trì thủ công.
         */
        if (
            $court->maintenance_source ===
            'equipment'
        ) {
            $court->update([
                'status' => 'Hoạt động',
                'maintenance_reason' => null,
                'maintenance_description' => null,
                'maintenance_start' => null,
                'maintenance_end' => null,
                'maintenance_source' => null,
            ]);
        }
    }
}
