<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Models\User;

/**
 * The token that proves a change of the admin panel was asked for by its own pages
 *
 * Nothing is stored: the token is derived from the account and from the key of the instance, so it
 * is the same on every page of one account, differs between accounts, and stops working when the
 * password changes (which also ends the sessions). A page of another site cannot read it
 */
class Csrf
{
    public const FIELD = '_csrf';

    public const HEADER = 'X-CSRF-Token';

    public static function token(User|false|null $user): string
    {
        if (!$user instanceof User) {
            return '';
        }

        $key = @hash_file('sha256', VAR_DIR . '/private.secret.key');
        if ($key === false) {
            return '';
        }

        return hash_hmac('sha256', 'csrf|' . $user->uuid . '|' . $user->getAttributes()['password'], $key);
    }

    public static function isValid(User|false|null $user, mixed $given): bool
    {
        $expected = self::token($user);

        return $expected !== '' && is_string($given) && hash_equals($expected, $given);
    }
}
