<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Users\FindInvitedUserByTokenAction;
use App\Actions\Users\RegisterInvitedUserAction;
use App\DTO\Users\RegisterInvitedUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreInvitationRegistrationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationRegistrationController extends Controller
{
    public function show(Request $request, string $token, FindInvitedUserByTokenAction $action): Response
    {
        $user = $action->execute($token);

        return Inertia::render('auth/invitation-register', [
            'token' => $token,
            'email' => $user?->email,
            'is_valid_invitation' => $user !== null,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(
        StoreInvitationRegistrationRequest $request,
        string $token,
        FindInvitedUserByTokenAction $findInvitedUserByTokenAction,
        RegisterInvitedUserAction $registerInvitedUserAction,
    ): RedirectResponse {
        $user = $findInvitedUserByTokenAction->execute($token);

        if ($user === null) {
            return redirect()
                ->route('invitation.accept', ['token' => $token])
                ->with('status', 'invitation_expired');
        }

        $validated = $request->validated();
        $submittedEmail = Str::lower(trim((string) $validated['email']));

        if (! hash_equals(Str::lower($user->email), $submittedEmail)) {
            throw ValidationException::withMessages([
                'email' => ['Email adresa ne odgovara ovoj pozivnici.'],
            ]);
        }

        $registeredUser = $registerInvitedUserAction->execute(
            $user,
            new RegisterInvitedUserData(
                firstName: (string) $validated['first_name'],
                lastName: (string) $validated['last_name'],
                phone: (string) $validated['phone'],
                password: (string) $validated['password'],
            ),
        );

        Auth::login($registeredUser);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
