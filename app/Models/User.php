<?php

namespace App\Models;

use App\Enums\UserInvitationStatus;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'name',
        'email',
        'password',
        'token_count',
        'invitation_status',
        'invitation_token_hash',
        'invited_at',
        'invitation_expires_at',
        'invitation_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'token_count' => 'integer',
            'invited_at' => 'immutable_datetime',
            'invitation_expires_at' => 'immutable_datetime',
            'invitation_accepted_at' => 'immutable_datetime',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(sprintf('%s %s', $this->first_name ?? '', $this->last_name ?? '')),
            set: function (string $value): array {
                $parts = preg_split('/\s+/', trim($value), limit: -1, flags: PREG_SPLIT_NO_EMPTY) ?: [];

                return [
                    'first_name' => $parts[0] ?? '',
                    'last_name' => count($parts) > 1
                        ? trim(implode(' ', array_slice($parts, 1)))
                        : '',
                ];
            },
        );
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()
            ->where('name', $roleName)
            ->exists();
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permissionName))
            ->exists();
    }

    public function invitationStatus(): UserInvitationStatus
    {
        return UserInvitationStatus::tryFrom((string) $this->invitation_status)
            ?? UserInvitationStatus::Active;
    }

    public function isPendingInvitation(): bool
    {
        return $this->invitationStatus() === UserInvitationStatus::Pending;
    }

    public function hasValidInvitationToken(string $token): bool
    {
        if (! $this->isPendingInvitation()) {
            return false;
        }

        if ($this->invitation_token_hash === null || $this->invitation_expires_at === null) {
            return false;
        }

        return hash_equals($this->invitation_token_hash, hash('sha256', $token))
            && $this->invitation_expires_at->isFuture();
    }
}
