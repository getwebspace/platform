<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Models\User;
use App\Domain\References\Date;

/**
 * Signed e-mail confirmation links
 *
 * Nothing is stored: the link is a JWT bound to the address it was issued for,
 * so it dies when the address changes, and the account status tells whether it
 * has already been used
 */
trait UseConfirmation
{
    use UseSecurity;

    protected const CONFIRMATION_SUBJECT = 'user-confirmation';

    protected const CONFIRMATION_TTL = Date::DAY;

    protected function issueConfirmationToken(User $user): string
    {
        return $this->encodeJWT(
            self::CONFIRMATION_SUBJECT,
            $user->uuid,
            ['fingerprint' => $this->confirmationFingerprint($user)],
            self::CONFIRMATION_TTL
        );
    }

    protected function confirmationFingerprint(User $user): string
    {
        return hash('sha256', $user->uuid . '|' . mb_strtolower((string) $user->email));
    }

    /**
     * Whether a freshly registered account has to confirm its address first
     */
    protected function isConfirmationRequired(mixed $email): bool
    {
        return $this->parameter('user_confirmation', 'off') === 'on' && !blank($email);
    }
}
