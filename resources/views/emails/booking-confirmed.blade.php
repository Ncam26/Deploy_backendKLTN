<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận đặt sân</title>
</head>
<body style="margin:0;padding:24px;background:#f2f8f5;font-family:Arial,sans-serif;color:#1d3029;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dce9e3;border-radius:16px;overflow:hidden;">
        <div style="padding:22px 26px;background:#0a967a;color:#ffffff;">
            <h1 style="margin:0;font-size:22px;">SÂN MINH TIẾN</h1>
            <p style="margin:7px 0 0;">Xác nhận đặt sân thành công</p>
        </div>

        <div style="padding:26px;">
            <p>Xin chào <strong>{{ $booking->user->name }}</strong>,</p>

            <p>
                Chúng tôi đã xác nhận nhận được tiền chuyển khoản
                và lịch đặt sân của bạn đã được xác nhận thành công.
            </p>

            <div style="margin:20px 0;padding:18px;background:#f4faf7;border-radius:12px;line-height:1.8;">
                <div><strong>Mã phiếu:</strong> #{{ $booking->id }}</div>
                <div><strong>Sân:</strong> {{ $booking->court->name }}</div>
                <div><strong>Ngày chơi:</strong> {{ $booking->booking_date->format('d/m/Y') }}</div>
                <div>
                    <strong>Khung giờ:</strong>
                    {{ substr($booking->start_time, 0, 5) }} -
                    {{ substr($booking->end_time, 0, 5) }}
                </div>
                <div>
                    <strong>Số tiền:</strong>
                    {{ number_format($booking->total_price, 0, ',', '.') }}đ
                </div>
                <div><strong>Thanh toán:</strong> Chuyển khoản ACB - Đã thanh toán</div>
            </div>

            <p>Vui lòng đến sân đúng giờ và cung cấp mã phiếu khi cần đối chiếu.</p>

            <p style="margin-bottom:0;color:#687870;">
                Cảm ơn bạn đã sử dụng dịch vụ của Sân Minh Tiến.
            </p>
        </div>
    </div>
</body>
</html>
