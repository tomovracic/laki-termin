<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('can_access_match_history')->default(false)->after('can_view_all_ranking_groups');
            $table->boolean('can_view_all_match_history_groups')->default(false)->after('can_access_match_history');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn([
                'can_access_match_history',
                'can_view_all_match_history_groups',
            ]);
        });
    }
};
