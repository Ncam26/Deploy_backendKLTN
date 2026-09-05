<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_method', 30)
                ->nullable()
                ->after('payment_status');

            $table->timestamp('payment_expires_at')
                ->nullable()
                ->after('payment_method');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('order_id')->unique();
            $table->string('request_id')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 30)->default('pending');
            $table->string('trans_id')->nullable();
            $table->text('pay_url')->nullable();
            $table->integer('result_code')->nullable();
            $table->string('message')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_expires_at',
            ]);
        });
    }
};
