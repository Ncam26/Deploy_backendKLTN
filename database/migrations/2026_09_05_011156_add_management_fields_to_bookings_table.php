<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chuyển status sang string để dễ bổ sung trạng thái mới.
        DB::statement("
            ALTER TABLE bookings
            MODIFY status VARCHAR(30)
            NOT NULL DEFAULT 'pending'
        ");

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_status', 30)
                ->default('unpaid')
                ->after('status');

            $table->string('action_reason')
                ->nullable()
                ->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'action_reason',
            ]);
        });

        DB::statement("
            ALTER TABLE bookings
            MODIFY status ENUM(
                'pending',
                'confirmed',
                'completed',
                'cancelled'
            )
            NOT NULL DEFAULT 'pending'
        ");
    }
};