<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 30);
            $table->string('reason')->nullable();

            $table->foreignId('old_court_id')
                ->nullable()
                ->constrained('courts')
                ->nullOnDelete();

            $table->foreignId('new_court_id')
                ->nullable()
                ->constrained('courts')
                ->nullOnDelete();

            $table->date('old_booking_date')->nullable();
            $table->date('new_booking_date')->nullable();

            $table->time('old_start_time')->nullable();
            $table->time('new_start_time')->nullable();

            $table->time('old_end_time')->nullable();
            $table->time('new_end_time')->nullable();

            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_histories');
    }
};