<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@sanminhtien.vn'],
            [
                'name' => 'Quản trị viên',
                'password' => Hash::make('123456'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'minhanh@example.com'],
            [
                'name' => 'Nguyễn Minh Anh',
                'password' => Hash::make('123456'),
                'phone' => '0901234567',
                'role' => 'user',
                'status' => 'active',
            ]
        );

        $courtData = [
            ['name' => 'Sân Minh Tiến', 'area' => 'Quận 7, TP. Hồ Chí Minh', 'price' => 85000, 'rating' => 4.8, 'image' => 'https://images.unsplash.com/photo-1626224583764-847ea6d7f3b3?auto=format&fit=crop&w=900&q=80', 'status' => 'Hoạt động'],
            ['name' => 'VietSmash Badminton', 'area' => 'Quận 4, TP. Hồ Chí Minh', 'price' => 100000, 'rating' => 4.7, 'image' => 'https://images.unsplash.com/photo-1593341646782-e0b495cff86d?auto=format&fit=crop&w=900&q=80', 'status' => 'Hoạt động'],
            ['name' => 'Tân Phú Sports Center', 'area' => 'Tân Phú, TP. Hồ Chí Minh', 'price' => 70000, 'rating' => 4.6, 'image' => 'https://images.unsplash.com/photo-1594736797933-d0dc4d63e7f1?auto=format&fit=crop&w=900&q=80', 'status' => 'Bảo trì'],
        ];

        foreach ($courtData as $item) {
            Court::updateOrCreate(['name' => $item['name']], $item);
        }

        $courtOne = Court::where('name', 'Sân Minh Tiến')->first();
        $courtTwo = Court::where('name', 'VietSmash Badminton')->first();

        $equipmentData = [
            ['name' => 'Lưới thi đấu', 'code' => 'NET-01', 'court_id' => $courtOne->id, 'category' => 'Gắn sân', 'status' => 'Tốt'],
            ['name' => 'Đèn LED sân', 'code' => 'LED-01', 'court_id' => $courtOne->id, 'category' => 'Gắn sân', 'status' => 'Cần bảo dưỡng'],
            ['name' => 'Bộ vợt cho thuê', 'code' => 'RKT-02', 'court_id' => $courtTwo->id, 'category' => 'Cho thuê', 'status' => 'Tốt'],
        ];

        foreach ($equipmentData as $item) {
            Equipment::updateOrCreate(['code' => $item['code']], $item);
        }
    }
}
