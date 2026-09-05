<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ForgotPasswordController;

Route::get('/health', function () {
    return response()->json(['message' => 'Laravel API đang hoạt động.']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password/send-otp',[ForgotPasswordController::class, 'sendOtp'])->middleware('throttle:5,1');
Route::post( '/forgot-password/verify-otp',  [ForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post( '/forgot-password/reset',  [ForgotPasswordController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::get('/courts', [CourtController::class, 'index']);
Route::get('/courts/{court}', [CourtController::class, 'show']);
Route::get('/bookings/available-times', [BookingController::class, 'availableTimes']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::put('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::put(
        '/bookings/{booking}/transfer-submitted',
        [BookingController::class, 'submitBankTransfer']
    );
    Route::get('/recommendations', [RecommendationController::class, 'index']);

    Route::middleware('admin')
    ->prefix('admin')
    ->group(function () {
        Route::get(
            '/customers',
            [AdminController::class, 'customers']
        );

        Route::get(
            '/dashboard',
            [AdminController::class, 'dashboard']
        );

        Route::get(
            '/customers/{user}',
            [AdminController::class, 'customerDetail']
        );

        Route::put(
            '/customers/{user}/status',
            [AdminController::class, 'updateCustomerStatus']
        );

        Route::get(
            '/reports/summary',
            [AdminController::class, 'reportSummary']
        );
        
        Route::get(
            '/reports/revenue',
            [AdminController::class, 'revenue']
        );
        Route::get(
            '/reports/courts',
            [AdminController::class, 'courtReport']
        );

        Route::get(
            '/reports/peak-hours',
            [AdminController::class, 'peakHours']
        );

        Route::get(
            '/reports/export',
            [AdminController::class, 'exportReport']
        );
                /*
         * Quản lý sân
         */
        Route::post(
            '/courts',
            [CourtController::class, 'store']
        );

        Route::put(
            '/courts/{court}',
            [CourtController::class, 'update']
        );

        Route::put(
            '/courts/{court}/maintenance',
            [CourtController::class, 'maintenance']
        );

        Route::put(
            '/courts/{court}/reopen',
            [CourtController::class, 'reopen']
        );

        Route::delete(
            '/courts/{court}',
            [CourtController::class, 'destroy']
        );

        /*
         * Quản lý lịch đặt
         */
        Route::get(
        '/bookings',
            [BookingController::class, 'index']
        );

        Route::get(
            '/bookings/{booking}',
            [BookingController::class, 'show']
        );

        Route::put(
            '/bookings/{booking}/process',
            [BookingController::class, 'process']
        );
        /*
         * Quản lý thiết bị
         */
        Route::get(
            '/equipment',
            [EquipmentController::class, 'index']
        );

        Route::post(
            '/equipment',
            [EquipmentController::class, 'store']
        );

        Route::put(
            '/equipment/{equipment}',
            [EquipmentController::class, 'update']
        );

        Route::put(
            '/equipment/{equipment}/repair',
            [EquipmentController::class, 'repair']
        );

        Route::put(
            '/equipment/{equipment}/complete',
            [EquipmentController::class, 'complete']
        );

        Route::get(
            '/equipment/{equipment}/repairs',
            [EquipmentController::class, 'history']
        );

        Route::put(
            '/equipment/{equipment}/retire',
            [EquipmentController::class, 'retire']
        );
    });
});
