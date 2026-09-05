<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('area');
            $table->unsignedBigInteger('price');
            $table->decimal('rating', 2, 1)->default(0);
            $table->text('image')->nullable();
            $table->enum('status', ['Hoạt động', 'Bảo trì'])->default('Hoạt động');
            $table->string('maintenance_reason')->nullable();
            $table->text('maintenance_description')->nullable();
            $table->date('maintenance_start')->nullable();
            $table->date('maintenance_end')->nullable();
            $table->string('maintenance_source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
