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
            $table->dropForeign(['reservation_slot_id']);
            $table->dropUnique('reservations_reservation_slot_id_unique');
            $table->index('reservation_slot_id');
            $table->foreign('reservation_slot_id')
                ->references('id')
                ->on('reservation_slots')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['reservation_slot_id']);
            $table->dropIndex('reservations_reservation_slot_id_index');
            $table->unique('reservation_slot_id');
            $table->foreign('reservation_slot_id')
                ->references('id')
                ->on('reservation_slots')
                ->restrictOnDelete();
        });
    }
};
