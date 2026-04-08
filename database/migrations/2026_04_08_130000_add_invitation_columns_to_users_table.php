<?php

declare(strict_types=1);

use App\Enums\UserInvitationStatus;
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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('invitation_status')->nullable()->after('token_count');
            $table->string('invitation_token_hash', 64)->nullable()->after('invitation_status');
            $table->timestamp('invited_at')->nullable()->after('invitation_token_hash');
            $table->timestamp('invitation_expires_at')->nullable()->after('invited_at');
            $table->timestamp('invitation_accepted_at')->nullable()->after('invitation_expires_at');
        });

        DB::table('users')->update([
            'invitation_status' => UserInvitationStatus::Active->value,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'invitation_status',
                'invitation_token_hash',
                'invited_at',
                'invitation_expires_at',
                'invitation_accepted_at',
            ]);
        });
    }
};
