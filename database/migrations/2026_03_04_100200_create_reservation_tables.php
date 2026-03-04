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
        Schema::create('reservation_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terrain_id')->constrained('terrains')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 32);
            $table->timestamps();

            $table->unique(['terrain_id', 'starts_at', 'ends_at']);
            $table->index(['terrain_id', 'status', 'starts_at']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reservation_slot_id')->constrained('reservation_slots')->restrictOnDelete();
            $table->string('status', 32);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['reservation_slot_id']);
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('reservation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->string('type', 32);
            $table->string('token_hash', 128)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'type']);
            $table->index(['type', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_tokens');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('reservation_slots');
    }
};
