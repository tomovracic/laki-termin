<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeagueMatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMatch extends Model
{
    /** @use HasFactory<\Database\Factories\LeagueMatchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'league_id',
        'league_group_id',
        'player_one_id',
        'player_one_first_name',
        'player_one_last_name',
        'player_one_partner_id',
        'player_one_participant_id',
        'player_two_id',
        'player_two_first_name',
        'player_two_last_name',
        'player_two_partner_id',
        'player_two_participant_id',
        'round',
        'bracket_round',
        'bracket_position',
        'next_match_id',
        'next_match_slot',
        'is_bye',
        'status',
        'set1_player_one_games',
        'set1_player_two_games',
        'set2_player_one_games',
        'set2_player_two_games',
        'set3_player_one_games',
        'set3_player_two_games',
        'set4_player_one_games',
        'set4_player_two_games',
        'set5_player_one_games',
        'set5_player_two_games',
        'played_at',
        'entered_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeagueMatchStatus::class,
            'played_at' => 'immutable_datetime',
            'is_bye' => 'boolean',
            'bracket_round' => 'integer',
            'bracket_position' => 'integer',
            'next_match_slot' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(LeagueGroup::class, 'league_group_id');
    }

    public function playerOneParticipant(): BelongsTo
    {
        return $this->belongsTo(LeagueParticipant::class, 'player_one_participant_id');
    }

    public function playerTwoParticipant(): BelongsTo
    {
        return $this->belongsTo(LeagueParticipant::class, 'player_two_participant_id');
    }

    public function playerOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_one_id');
    }

    public function playerTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_two_id');
    }

    public function playerOnePartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_one_partner_id');
    }

    public function playerTwoPartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_two_partner_id');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function playerOneDisplayName(): string
    {
        $name = $this->playerOne !== null
            ? $this->playerOne->name
            : trim(($this->player_one_first_name ?? '').' '.($this->player_one_last_name ?? ''));

        return $this->appendPartnerName($name, $this->playerOnePartner?->name);
    }

    public function playerTwoDisplayName(): string
    {
        $name = $this->playerTwo !== null
            ? $this->playerTwo->name
            : trim(($this->player_two_first_name ?? '').' '.($this->player_two_last_name ?? ''));

        return $this->appendPartnerName($name, $this->playerTwoPartner?->name);
    }

    public function hasPlayerOne(): bool
    {
        return $this->player_one_id !== null
            || ($this->player_one_first_name !== null && $this->player_one_last_name !== null);
    }

    public function hasPlayerTwo(): bool
    {
        return $this->player_two_id !== null
            || ($this->player_two_first_name !== null && $this->player_two_last_name !== null);
    }

    /**
     * Empty first-round bracket slot (both sides vacant), not a player bye.
     */
    public function isEmptyBracketSlot(): bool
    {
        return ! $this->is_bye
            && ! $this->hasPlayerOne()
            && ! $this->hasPlayerTwo()
            && $this->status === LeagueMatchStatus::Played;
    }

    /**
     * @return array{user_id: int|null, first_name: string|null, last_name: string|null}
     */
    public function playerOneIdentity(): array
    {
        return [
            'user_id' => $this->player_one_id,
            'first_name' => $this->player_one_id === null ? $this->player_one_first_name : null,
            'last_name' => $this->player_one_id === null ? $this->player_one_last_name : null,
        ];
    }

    /**
     * @return array{user_id: int|null, first_name: string|null, last_name: string|null}
     */
    public function playerTwoIdentity(): array
    {
        return [
            'user_id' => $this->player_two_id,
            'first_name' => $this->player_two_id === null ? $this->player_two_first_name : null,
            'last_name' => $this->player_two_id === null ? $this->player_two_last_name : null,
        ];
    }

    public function scopePlayed(Builder $query): Builder
    {
        return $query->where('status', LeagueMatchStatus::Played->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeagueMatchStatus::Pending->value);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('player_one_id', $userId)
                ->orWhere('player_two_id', $userId)
                ->orWhere('player_one_partner_id', $userId)
                ->orWhere('player_two_partner_id', $userId);
        });
    }

    /**
     * @return array{player_one: int, player_two: int}
     */
    public function setCounts(): array
    {
        return $this->scoreCounts(true);
    }

    /**
     * @return array{player_one: int, player_two: int}
     */
    public function gameCounts(): array
    {
        return $this->scoreCounts(false);
    }

    public function winnerParticipantId(): ?int
    {
        $setCounts = $this->setCounts();

        if ($setCounts['player_one'] === $setCounts['player_two']) {
            return null;
        }

        return $setCounts['player_one'] > $setCounts['player_two']
            ? $this->player_one_participant_id
            : $this->player_two_participant_id;
    }

    /**
     * @return array{player_one: int, player_two: int}
     */
    private function scoreCounts(bool $sets): array
    {
        $playerOne = 0;
        $playerTwo = 0;

        $scoreSets = [
            [$this->set1_player_one_games, $this->set1_player_two_games],
            [$this->set2_player_one_games, $this->set2_player_two_games],
            [$this->set3_player_one_games, $this->set3_player_two_games],
            [$this->set4_player_one_games, $this->set4_player_two_games],
            [$this->set5_player_one_games, $this->set5_player_two_games],
        ];

        foreach ($scoreSets as [$one, $two]) {
            if ($one === null || $two === null) {
                continue;
            }

            if ($sets) {
                if ($one > $two) {
                    $playerOne++;
                } elseif ($two > $one) {
                    $playerTwo++;
                }

                continue;
            }

            $playerOne += $one;
            $playerTwo += $two;
        }

        return ['player_one' => $playerOne, 'player_two' => $playerTwo];
    }

    private function appendPartnerName(string $name, ?string $partnerName): string
    {
        if ($partnerName === null || $partnerName === '') {
            return $name;
        }

        return $name === '' ? $partnerName : $name.' / '.$partnerName;
    }
}
