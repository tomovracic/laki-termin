<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserOverviewController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->withCount('reservations')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'token_count',
                'invitation_status',
                'created_at',
            ])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'token_count' => $user->token_count ?? 0,
                'invitation_status' => $user->invitationStatus()->value,
                'reservations_count' => $user->reservations_count,
                'created_at' => $user->created_at?->toISOString(),
            ])
            ->all();

        return Inertia::render('admin/users', [
            'users' => $users,
        ]);
    }
}
