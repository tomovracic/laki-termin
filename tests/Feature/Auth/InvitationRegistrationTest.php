<?php

declare(strict_types=1);

use App\Enums\UserInvitationStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('invited user can open registration form with valid invitation token', function () {
    $token = 'valid-invitation-token';
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'invitation_status' => UserInvitationStatus::Pending->value,
        'invitation_token_hash' => hash('sha256', $token),
        'invitation_expires_at' => now()->addDays(3),
    ]);

    $this->get(route('invitation.accept', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/invitation-register')
            ->where('email', $user->email)
            ->where('is_valid_invitation', true),
        );
});

test('expired invitation token renders invitation expired state', function () {
    $token = 'expired-invitation-token';

    User::factory()->create([
        'email' => 'expired@example.com',
        'invitation_status' => UserInvitationStatus::Pending->value,
        'invitation_token_hash' => hash('sha256', $token),
        'invitation_expires_at' => now()->subMinute(),
    ]);

    $this->get(route('invitation.accept', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/invitation-register')
            ->where('email', null)
            ->where('is_valid_invitation', false),
        );
});

test('invited user can complete registration and access application', function () {
    $token = 'complete-invitation-token';
    $user = User::factory()->create([
        'email' => 'new.member@example.com',
        'first_name' => '',
        'last_name' => '',
        'phone' => '',
        'invitation_status' => UserInvitationStatus::Pending->value,
        'invitation_token_hash' => hash('sha256', $token),
        'invitation_expires_at' => now()->addDay(),
    ]);

    $response = $this->post(route('invitation.register', ['token' => $token]), [
        'first_name' => 'Novi',
        'last_name' => 'Korisnik',
        'phone' => '0911234567',
        'email' => 'new.member@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user->fresh());

    $user->refresh();
    expect($user->first_name)->toBe('Novi');
    expect($user->last_name)->toBe('Korisnik');
    expect($user->phone)->toBe('0911234567');
    expect($user->isPendingInvitation())->toBeFalse();
    expect($user->invitation_token_hash)->toBeNull();
    expect($user->invitation_expires_at)->toBeNull();
    expect($user->invitation_accepted_at)->not()->toBeNull();
});

test('expired invitation cannot be used for registration', function () {
    $token = 'cannot-use-expired-token';

    User::factory()->create([
        'email' => 'cannot.use@example.com',
        'invitation_status' => UserInvitationStatus::Pending->value,
        'invitation_token_hash' => hash('sha256', $token),
        'invitation_expires_at' => now()->subMinute(),
    ]);

    $response = $this->post(route('invitation.register', ['token' => $token]), [
        'first_name' => 'Nije',
        'last_name' => 'Bitno',
        'phone' => '0919999999',
        'email' => 'cannot.use@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertRedirect(route('invitation.accept', ['token' => $token]))
        ->assertSessionHas('status', 'invitation_expired');

    $this->assertGuest();
});
