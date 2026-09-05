# Backend Laravel - Đặt sân cầu lông

Backend dùng Laravel 12, Laravel Sanctum và MySQL. Code được chia theo Model, Controller, Migration và Route để dễ đọc, dễ sửa và phù hợp khóa luận sinh viên.

## Chức năng

- Đăng ký, đăng nhập, đăng xuất
- Phân quyền người dùng và Admin
- Quản lý sân và bảo trì sân
- Quản lý thiết bị, phiếu sửa chữa
- Kiểm tra lịch trống, đặt sân, hủy lịch
- Hồ sơ khách hàng
- Dashboard và báo cáo doanh thu
- Tạo dữ liệu nhắc lịch trước 30 phút
- Gợi ý sân và khung giờ bằng thuật toán chấm điểm

## Cách chạy với XAMPP MySQL

1. Bật MySQL trong XAMPP.
2. Tạo database tên `kltn_badminton`.
3. Sao chép `.env.example` thành `.env`.
4. Kiểm tra thông tin database trong `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kltn_badminton
DB_USERNAME=root
DB_PASSWORD=
```

5. Chạy lệnh:

```bash
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

API chạy tại `http://127.0.0.1:8000/api`.

## Tài khoản mẫu

```text
Admin: admin@sanminhtien.vn / 123456
User:  minhanh@example.com / 123456
```

## API chính

- `POST /api/register`
- `POST /api/login`
- `GET /api/courts`
- `GET /api/bookings/available-times`
- `POST /api/bookings`
- `GET /api/my-bookings`
- `GET /api/recommendations`
- `GET /api/admin/dashboard`
- `GET /api/admin/customers`
- `GET /api/admin/bookings`
- `GET /api/admin/equipment`

API cần đăng nhập phải gửi header:

```text
Authorization: Bearer TOKEN
Accept: application/json
```

Phần gợi ý hiện dùng thuật toán chấm điểm dựa trên lịch trống, đánh giá, giá và giờ phổ biến. Sau này có thể thay bằng mô hình AI thật mà không cần thay đổi giao diện.
