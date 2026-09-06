<?php

namespace App\Models;

use Database\Factories\OneTimePasswordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single sign-in code issued to one user.
 *
 * Only the hash of the code is kept. A row is retired either by being consumed
 * on a successful sign-in, or by a newer code being issued for the same user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code_hash', 'expires_at'])]
#[Hidden(['code_hash'])]
class OneTimePassword extends Model
{
    /** @use HasFactory<OneTimePasswordFactory> */
    use HasFactory;

    /**
     * The user this code will sign in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this code is past its lifetime.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Whether this code has already been spent or retired.
     */
    public function hasBeenConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * Whether this code has burnt through its allowance of wrong guesses.
     */
    public function hasExhaustedAttempts(): bool
    {
        return $this->attempts >= (int) config('otp.max_attempts');
    }

    /**
     * Limit the query to codes that could still sign someone in.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
