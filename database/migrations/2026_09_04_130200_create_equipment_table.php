<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('court_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->enum('status', ['Tốt', 'Cần bảo dưỡng', 'Hỏng', 'Đang sửa'])->default('Tốt');
            $table->string('repair_reason')->nullable();
            $table->text('repair_description')->nullable();
            $table->date('repair_start')->nullable();
            $table->date('repair_end')->nullable();
            $table->unsignedBigInteger('repair_cost')->default(0);
            $table->string('technician')->nullable();
            $table->boolean('blocks_court')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
