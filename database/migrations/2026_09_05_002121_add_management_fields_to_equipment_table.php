<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->date('purchase_date')
                ->nullable()
                ->after('category');

            $table->text('note')
                ->nullable()
                ->after('purchase_date');

            $table->boolean('is_active')
                ->default(true)
                ->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_date',
                'note',
                'is_active',
            ]);
        });
    }
};