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
        Schema::create('terrains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('terrain_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terrain_id')->nullable()->constrained('terrains')->nullOnDelete();
            $table->boolean('is_global');
            $table->time('reservation_window_from');
            $table->time('reservation_window_to');
            $table->unsignedSmallInteger('max_advance_days');
            $table->unsignedSmallInteger('slot_duration_minutes');
            $table->unsignedSmallInteger('slot_buffer_minutes');
            $table->unsignedSmallInteger('min_lead_time_minutes');
            $table->timestamps();

            $table->unique(['terrain_id']);
            $table->index(['is_global']);
        });

        Schema::create('terrain_inactive_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terrain_id')->nullable()->constrained('terrains')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('from_at');
            $table->timestamp('to_at');
            $table->string('reason', 255);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['terrain_id', 'from_at', 'to_at'], 'inactive_period_terrain_window_idx');
            $table->index(['from_at', 'to_at'], 'inactive_period_window_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terrain_inactive_periods');
        Schema::dropIfExists('terrain_settings');
        Schema::dropIfExists('terrains');
    }
};
