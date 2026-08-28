<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\User;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Traits\UseConfirmation;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * E-Mail confirmation
 *
 * Reached either from the link in the letter, or without a token to have the
 * letter sent again - a link that expired would otherwise leave the account
 * stuck: it cannot log in and the address is taken, so it cannot register again
 *
 * The link carries a JWT bound to the address it was issued for, so changing
 * the address invalidates it, as does confirming (the status stops matching)
 */
class UserConfirmationAction extends UserAction
{
    use UseConfirmation;

    protected function action(): \Slim\Psr7\Response
    {
        $token = (string) $this->getParam('token', '');

        return $token !== '' ? $this->confirm($token) : $this->resend();
    }

    private function confirm(string $token): \Slim\Psr7\Response
    {
        $user = $this->resolveToken($token);

        if ($user === null) {
            return $this->respondWithConfirmationTemplate('expired');
        }

        $this->userService->update($user, ['status' => UserStatus::WORK]);

        $this->container->get(\App\Application\PubSub::class)->publish('common:user:confirmed', $user);

        return $this->respondWithRedirect('/user/login', 302);
    }

    /**
     * Send the letter again
     */
    private function resend(): \Slim\Psr7\Response
    {
        if (!$this->isPost()) {
            return $this->respondWithConfirmationTemplate('resend');
        }

        if (!$this->isRecaptchaChecked()) {
            $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');

            return $this->respondWithConfirmationTemplate('resend');
        }

        $identifier = trim((string) $this->getParam('identifier', ''));

        if ($identifier === '') {
            $this->addError('identifier', 'EXCEPTION_UNIQUE_MISSING');

            return $this->respondWithConfirmationTemplate('resend');
        }

        try {
            /** @var User $user */
            $user = $this->userService->read([
                'identifier' => $identifier,
                'status' => UserStatus::CONFIRMATION,
            ]);

            // read() by identifier ignores the status criteria, so check it here
            if ($user->status === UserStatus::CONFIRMATION && $user->email) {
                $this->container->get(\App\Application\PubSub::class)->publish(
                    'common:user:confirmation',
                    ['user' => $user, 'token' => $this->issueConfirmationToken($user)]
                );
            }
        } catch (UserNotFoundException $e) {
            // deliberately ignored, the answer must not reveal who is registered
        }

        return $this->respondWithConfirmationTemplate('sent');
    }

    private function resolveToken(string $token): ?User
    {
        try {
            $payload = $this->decodeJWT($token);
        } catch (\DomainException|ExpiredException|SignatureInvalidException|\UnexpectedValueException $e) {
            return null;
        }

        if (($payload['sub'] ?? '') !== self::CONFIRMATION_SUBJECT || blank($payload['uuid'] ?? null)) {
            return null;
        }

        try {
            /** @var User $user */
            $user = $this->userService->read(['uuid' => $payload['uuid']]);
        } catch (UserNotFoundException $e) {
            return null;
        }

        if ($user->status !== UserStatus::CONFIRMATION) {
            return null;
        }

        $data = (array) ($payload['data'] ?? []);

        return hash_equals($this->confirmationFingerprint($user), (string) ($data['fingerprint'] ?? '')) ? $user : null;
    }

    private function respondWithConfirmationTemplate(string $stage): \Slim\Psr7\Response
    {
        return $this->respond(
            $this->parameter('user_confirmation_template', 'user.confirmation.twig'),
            ['stage' => $stage]
        );
    }
}
