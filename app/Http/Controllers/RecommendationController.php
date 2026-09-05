<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', today()->toDateString());
        $timeList = ['06:00', '07:30', '09:00', '17:00', '18:30', '20:00'];
        $courts = Court::where('status', 'Hoạt động')->get();
        $bookings = Booking::whereDate('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        $suggestions = [];

        foreach ($courts as $court) {
            foreach ($timeList as $time) {
                $occupied = $bookings->contains(function ($booking) use ($court, $time) {
                    return $booking->court_id === $court->id
                        && substr($booking->start_time, 0, 5) === $time;
                });

                if (! $occupied) {
                    $score = $court->rating * 20;
                    $score += $time === '18:30' ? 12 : 0;
                    $score += $court->price <= 85000 ? 8 : 0;

                    $suggestions[] = [
                        'court_id' => $court->id,
                        'court_name' => $court->name,
                        'area' => $court->area,
                        'price' => $court->price,
                        'date' => $date,
                        'start_time' => $time,
                        'score' => round($score),
                        'reason' => 'Sân còn trống, đánh giá tốt và phù hợp mức giá phổ biến.',
                    ];
                }
            }
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return response()->json(array_slice($suggestions, 0, 5));
    }
}
