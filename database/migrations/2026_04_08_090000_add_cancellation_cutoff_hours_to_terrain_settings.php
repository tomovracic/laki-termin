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
            $table->unsignedSmallInteger('cancellation_cutoff_hours')
                ->nullable()
                ->after('max_advance_days');
        });

        DB::table('terrain_settings')
            ->whereNull('cancellation_cutoff_hours')
            ->update([
                'cancellation_cutoff_hours' => 0,
            ]);
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
            $table->dropColumn('cancellation_cutoff_hours');
        });
    }
};
