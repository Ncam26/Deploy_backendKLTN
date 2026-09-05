<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->string('code', 20)
                ->nullable()
                ->unique()
                ->after('id');

            $table->time('opening_time')
                ->default('06:00:00')
                ->after('price');

            $table->time('closing_time')
                ->default('22:00:00')
                ->after('opening_time');

            $table->json('amenities')
                ->nullable()
                ->after('closing_time');
        });

        // Tạo mã cho các sân cũ
        $courts = DB::table('courts')->get();

        foreach ($courts as $court) {
            DB::table('courts')
                ->where('id', $court->id)
                ->update([
                    'code' => 'S'.str_pad(
                        $court->id,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropUnique(['code']);

            $table->dropColumn([
                'code',
                'opening_time',
                'closing_time',
                'amenities',
            ]);
        });
    }
};