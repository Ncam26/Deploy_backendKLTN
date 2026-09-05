<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\Booking;
use App\Models\RepairTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CourtController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $courts = Court::when(
            $search,
            function ($query) use ($search) {
                $query->where(function ($item) use ($search) {
                    $item->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('area', 'like', "%{$search}%");
                });
            }
        )
            ->orderBy('code')
            ->get();

        return response()->json($courts);
    }

    public function show(Court $court)
    {
        return response()->json($court);
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
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:courts,code',
                ],
                'name' => [
                    'required',
                    'string',
                    'max:120',
                ],
                'area' => [
                    'required',
                    'string',
                    'max:200',
                ],
                'price' => [
                    'required',
                    'integer',
                    'min:1000',
                ],
                'opening_time' => [
                    'required',
                    'date_format:H:i',
                ],
                'closing_time' => [
                    'required',
                    'date_format:H:i',
                    'after:opening_time',
                ],
                'amenities' => [
                    'nullable',
                    'array',
                ],
                'amenities.*' => [
                    'string',
                    'max:100',
                ],
                'image_file' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
                ],
            ],
            [
                'code.required' => 'Vui lòng nhập mã sân.',
                'code.unique' => 'Mã sân này đã tồn tại.',
                'name.required' => 'Vui lòng nhập tên sân.',
                'area.required' => 'Vui lòng nhập khu vực hoặc địa chỉ.',
                'price.required' => 'Vui lòng nhập giá sân.',
                'price.min' => 'Giá sân phải lớn hơn hoặc bằng 1.000 đồng.',
                'opening_time.required' => 'Vui lòng nhập giờ mở cửa.',
                'closing_time.required' => 'Vui lòng nhập giờ đóng cửa.',
                'closing_time.after' => 'Giờ đóng cửa phải sau giờ mở cửa.',
                'image_file.image' => 'File đã chọn phải là hình ảnh.',
                'image_file.mimes' => 'Ảnh phải có định dạng JPG, PNG hoặc WEBP.',
                'image_file.max' => 'Ảnh không được vượt quá 2MB.',
            ]
        );

        if ($request->hasFile('image_file')) {
            $path = $request
                ->file('image_file')
                ->store('courts', 'public');

            $data['image'] = url(
                Storage::url($path)
            );
        }

        unset($data['image_file']);

        $data['rating'] = 0;
        $data['status'] = 'Hoạt động';

        $court = Court::create($data);

        return response()->json([
            'message' => 'Thêm sân thành công.',
            'court' => $court,
        ], 201);
    }

    public function update(
        Request $request,
        Court $court
    ) {
        $request->merge([
            'code' => strtoupper(
                trim((string) $request->code)
            ),
        ]);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('courts', 'code')
                    ->ignore($court->id),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'area' => [
                'required',
                'string',
                'max:200',
            ],
            'price' => [
                'required',
                'integer',
                'min:1000',
            ],
            'opening_time' => [
                'required',
                'date_format:H:i',
            ],
            'closing_time' => [
                'required',
                'date_format:H:i',
                'after:opening_time',
            ],
            'amenities' => [
                'nullable',
                'array',
            ],
            'amenities.*' => [
                'string',
                'max:100',
            ],
            'image_file' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        if ($request->hasFile('image_file')) {
            $path = $request
                ->file('image_file')
                ->store('courts', 'public');

            $data['image'] = url(
                Storage::url($path)
            );
        }

        unset($data['image_file']);

        $court->update($data);

        return response()->json([
            'message' => 'Cập nhật sân thành công.',
            'court' => $court->fresh(),
        ]);
    }

    public function maintenance(
        Request $request,
        Court $court
    ) {
        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:200',
            ],
            'description' => [
                'nullable',
                'string',
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
        ]);

        $affectedBookings = Booking::where('court_id', $court->id)
            ->whereBetween('booking_date', [
                $data['start_date'],
                $data['end_date'],
            ])
            ->whereIn('status', [
                'pending',
                'confirmed',
                'in_use',
            ])
            ->count();

        if ($affectedBookings > 0) {
            return response()->json([
                'message' => 'Không thể bảo trì vì sân đang có lịch đặt trong khoảng ngày này.',
                'affected_count' => $affectedBookings,
            ], 409);
        }

        $court->update([
            'status' => 'Bảo trì',
            'maintenance_reason' => $data['reason'],
            'maintenance_description' =>
                $data['description'] ?? null,
            'maintenance_start' => $data['start_date'],
            'maintenance_end' => $data['end_date'],
            'maintenance_source' => 'court',
        ]);

        return response()->json([
            'message' => 'Đã chuyển sân sang bảo trì.',
            'court' => $court->fresh(),
        ]);
    }

    public function reopen(Court $court)
    {
        $hasBlockingRepair = RepairTicket::where('court_id', $court->id)
            ->where('status', 'in_progress')
            ->where('blocks_court', true)
            ->exists();

        if ($hasBlockingRepair) {
            return response()->json([
                'message' => 'Chưa thể mở sân vì vẫn còn thiết bị đang sửa và khóa sân.',
            ], 422);
        }

        $court->update([
            'status' => 'Hoạt động',
            'maintenance_reason' => null,
            'maintenance_description' => null,
            'maintenance_start' => null,
            'maintenance_end' => null,
            'maintenance_source' => null,
        ]);

        return response()->json([
            'message' => 'Đã mở lại sân.',
            'court' => $court->fresh(),
        ]);
    }

    public function destroy(Court $court)
    {
        if ($court->bookings()->exists()) {
            return response()->json([
                'message' => 'Sân đã có lịch sử đặt nên không thể xóa.',
            ], 422);
        }

        $court->delete();

        return response()->json([
            'message' => 'Xóa sân thành công.',
        ]);
    }
}
