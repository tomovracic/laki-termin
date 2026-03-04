<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('terrain_settings')) {
            return;
        }

        Schema::table('terrain_settings', function (Blueprint $table): void {
            $table->json('availability_periods')->nullable()->after('max_advance_days');
        });

        DB::table('terrain_settings')
            ->whereNull('availability_periods')
            ->update([
                'availability_periods' => json_encode([
                    [
                        'from' => '08:00',
                        'to' => '22:00',
                        'slot_duration_minutes' => 60,
                        'is_default' => true,
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        Schema::table('terrain_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'reservation_window_from',
                'reservation_window_to',
                'slot_duration_minutes',
                'slot_buffer_minutes',
                'min_lead_time_minutes',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('terrain_settings')) {
            return;
        }

        Schema::table('terrain_settings', function (Blueprint $table): void {
            $table->time('reservation_window_from')->nullable()->after('is_global');
            $table->time('reservation_window_to')->nullable()->after('reservation_window_from');
            $table->unsignedSmallInteger('slot_duration_minutes')->nullable()->after('max_advance_days');
            $table->unsignedSmallInteger('slot_buffer_minutes')->nullable()->after('slot_duration_minutes');
            $table->unsignedSmallInteger('min_lead_time_minutes')->nullable()->after('slot_buffer_minutes');
        });

        DB::table('terrain_settings')->update([
            'reservation_window_from' => '08:00',
            'reservation_window_to' => '22:00',
            'slot_duration_minutes' => 60,
            'slot_buffer_minutes' => 0,
            'min_lead_time_minutes' => 0,
        ]);

        Schema::table('terrain_settings', function (Blueprint $table): void {
            $table->dropColumn('availability_periods');
        });
    }
};
