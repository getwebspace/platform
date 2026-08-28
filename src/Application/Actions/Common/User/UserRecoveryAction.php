<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Models\User;
use App\Domain\References\Date;
use App\Domain\Service\User\Exception\PasswordsNotMatchException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Traits\UseSecurity;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * Password recovery
 *
 * Runs in two steps, both on this route:
 *  - no token: ask for the identifier and mail out a signed link
 *  - with token: check it and accept a new password
 *
 * The link carries a JWT rather than a stored code, and it is bound to the
 * current password hash, so it stops working the moment the password changes
 * (which also makes a link that was already used a dead link)
 */
class UserRecoveryAction extends UserAction
{
    use UseSecurity;

    private const TOKEN_SUBJECT = 'user-recovery';

    private const TOKEN_TTL = Date::HOUR;

    protected function action(): \Slim\Psr7\Response
    {
        $token = (string) $this->getParam('token', '');

        return $token !== '' ? $this->reset($token) : $this->request();
    }

    /**
     * Step one: mail a recovery link
     */
    private function request(): \Slim\Psr7\Response
    {
        $sent = false;

        if ($this->isPost()) {
            if ($this->isRecaptchaChecked()) {
                $identifier = trim((string) $this->getParam('identifier', ''));

                if ($identifier === '') {
                    $this->addError('identifier', 'EXCEPTION_UNIQUE_MISSING');
                } else {
                    try {
                        /** @var User $user */
                        $user = $this->userService->read([
                            'identifier' => $identifier,
                            'status' => \App\Domain\Casts\User\Status::WORK,
                        ]);

                        if ($user->email) {
                            $this->container->get(\App\Application\PubSub::class)->publish(
                                'common:user:recovery',
                                ['user' => $user, 'token' => $this->issueToken($user)]
                            );
                        }
                    } catch (UserNotFoundException $e) {
                        // deliberately ignored, see below
                    }

                    // the answer is the same whether or not the account exists,
                    // otherwise this page turns into a user directory
                    $sent = true;
                }
            } else {
                $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
            }
        }

        return $this->respond($this->parameter('user_lostpassword_template', 'user.lostpassword.twig'), [
            'stage' => 'request',
            'sent' => $sent,
        ]);
    }

    /**
     * Step two: accept a new password for the account named by the token
     */
    private function reset(string $token): \Slim\Psr7\Response
    {
        $user = $this->resolveToken($token);

        if ($user === null) {
            return $this->respond($this->parameter('user_lostpassword_template', 'user.lostpassword.twig'), [
                'stage' => 'expired',
                'sent' => false,
            ]);
        }

        if ($this->isPost()) {
            $password = (string) $this->getParam('password', '');
            $password_again = (string) $this->getParam('password_again', '');

            try {
                if ($password === '') {
                    $this->addError('password', 'EXCEPTION_UNIQUE_MISSING');
                } elseif ($password !== $password_again) {
                    throw new PasswordsNotMatchException();
                } else {
                    $this->userService->update($user, ['password' => $password]);

                    // every session was opened with the old password
                    $user->tokens()->delete();
                    $this->clearAuthCookies();

                    $this->container->get(\App\Application\PubSub::class)->publish('common:user:recovery-done', $user);

                    return $this->respondWithRedirect('/user/login', 302);
                }
            } catch (PasswordsNotMatchException $e) {
                $this->addError('password_again', $e->getMessage());
            }
        }

        return $this->respond($this->parameter('user_lostpassword_template', 'user.lostpassword.twig'), [
            'stage' => 'reset',
            'sent' => false,
            'token' => $token,
        ]);
    }

    private function issueToken(User $user): string
    {
        return $this->encodeJWT(self::TOKEN_SUBJECT, $user->uuid, [
            // ties the link to the password it was issued for
            'fingerprint' => $this->fingerprint($user),
        ], self::TOKEN_TTL);
    }

    private function resolveToken(string $token): ?User
    {
        try {
            $payload = $this->decodeJWT($token);
        } catch (\DomainException|ExpiredException|SignatureInvalidException|\UnexpectedValueException $e) {
            return null;
        }

        if (($payload['sub'] ?? '') !== self::TOKEN_SUBJECT || blank($payload['uuid'] ?? null)) {
            return null;
        }

        try {
            /** @var User $user */
            $user = $this->userService->read([
                'uuid' => $payload['uuid'],
                'status' => \App\Domain\Casts\User\Status::WORK,
            ]);
        } catch (UserNotFoundException $e) {
            return null;
        }

        $data = (array) ($payload['data'] ?? []);

        return hash_equals($this->fingerprint($user), (string) ($data['fingerprint'] ?? '')) ? $user : null;
    }

    private function fingerprint(User $user): string
    {
        return hash('sha256', $user->uuid . '|' . $user->getAttributes()['password']);
    }
}
