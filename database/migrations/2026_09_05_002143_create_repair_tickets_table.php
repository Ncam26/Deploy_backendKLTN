<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('equipment_id')
                ->constrained('equipment')
                ->cascadeOnDelete();

            /*
             * Lưu sân tại thời điểm sửa.
             * Sau này thiết bị chuyển sân vẫn xem được lịch sử cũ.
             */
            $table->foreignId('court_id')
                ->nullable()
                ->constrained('courts')
                ->nullOnDelete();

            $table->string('reason', 200);
            $table->text('description')->nullable();

            $table->date('start_date');
            $table->date('expected_end_date');

            $table->string('technician', 100)
                ->nullable();

            $table->unsignedBigInteger(
                'estimated_cost'
            )->default(0);

            $table->unsignedBigInteger(
                'actual_cost'
            )->nullable();

            $table->boolean('blocks_court')
                ->default(false);

            $table->enum('status', [
                'in_progress',
                'completed',
                'cancelled',
            ])->default('in_progress');

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'equipment_id',
                'status',
            ]);

            $table->index([
                'court_id',
                'start_date',
                'expected_end_date',
            ]);
        });

        /*
         * Chuyển phiếu sửa cũ sang bảng lịch sử.
         */
        $oldRepairs = DB::table('equipment')
            ->whereNotNull('repair_reason')
            ->get();

        foreach ($oldRepairs as $equipment) {
            DB::table('repair_tickets')->insert([
                'equipment_id' => $equipment->id,
                'court_id' => $equipment->court_id,
                'reason' => $equipment->repair_reason,
                'description' =>
                    $equipment->repair_description,
                'start_date' =>
                    $equipment->repair_start ?? now(),
                'expected_end_date' =>
                    $equipment->repair_end ?? now(),
                'technician' => $equipment->technician,
                'estimated_cost' =>
                    $equipment->repair_cost ?? 0,
                'actual_cost' => null,
                'blocks_court' =>
                    $equipment->blocks_court ?? false,
                'status' => 'in_progress',
                'completed_at' => null,
                'created_at' =>
                    $equipment->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_tickets');
    }
};