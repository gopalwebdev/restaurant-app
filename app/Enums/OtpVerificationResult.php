<?php

namespace App\Enums;

/**
 * The outcome of checking a sign-in code.
 *
 * Every failure carries the message shown to the person signing in. The
 * messages deliberately never reveal whether an account exists for the address
 * that was typed.
 */
enum OtpVerificationResult: string
{
    case Verified = 'verified';

    /** No code is outstanding, so there is nothing to check against. */
    case NotFound = 'not-found';

    case Incorrect = 'incorrect';
    case Expired = 'expired';
    case TooManyAttempts = 'too-many-attempts';

    /**
     * Whether the code checked out and the user may be signed in.
     */
    public function isVerified(): bool
    {
        return $this === self::Verified;
    }

    /**
     * The message to show for this outcome.
     */
    public function message(): string
    {
        return match ($this) {
            self::Verified => 'That code is correct.',
            self::NotFound => 'That code is no longer valid. Request a new one.',
            self::Incorrect => 'That code is not correct.',
            self::Expired => 'That code has expired. Request a new one.',
            self::TooManyAttempts => 'Too many incorrect attempts. Request a new code.',
        };
    }
}
