<?php

declare(strict_types=1);

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
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $name = trim((string) ($user->name ?? ''));
                    $parts = preg_split('/\s+/', $name, limit: -1, flags: PREG_SPLIT_NO_EMPTY) ?: [];

                    $firstName = $parts[0] ?? '';
                    $lastName = count($parts) > 1
                        ? trim(implode(' ', array_slice($parts, 1)))
                        : '';

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                        ]);
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('users')
            ->select(['id', 'first_name', 'last_name'])
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $fullName = trim(sprintf(
                        '%s %s',
                        (string) ($user->first_name ?? ''),
                        (string) ($user->last_name ?? ''),
                    ));

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'name' => $fullName,
                        ]);
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name', 'phone']);
        });
    }
};
