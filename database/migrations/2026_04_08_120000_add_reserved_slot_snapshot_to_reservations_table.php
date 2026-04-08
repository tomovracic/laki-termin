<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->date('reserved_for_date')->nullable()->after('status');
            $table->time('reserved_from_time')->nullable()->after('reserved_for_date');
            $table->time('reserved_to_time')->nullable()->after('reserved_from_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn([
                'reserved_for_date',
                'reserved_from_time',
                'reserved_to_time',
            ]);
        });
    }
};
