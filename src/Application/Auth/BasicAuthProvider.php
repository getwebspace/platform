<?php declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\Exception\UserNotConfirmedException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;

class BasicAuthProvider extends AbstractAuthProvider
{
    /** seconds the replaced refresh token stays valid, for requests that were already on their way */
    private const ROTATION_GRACE = 30;

    /**
     * @throws WrongPasswordException
     * @throws UserNotFoundException
     * @throws UserNotConfirmedException
     */
    public function login(array $credentials, array $params): ?User
    {
        $default = [
            'identifier' => null,
            'username' => null,
            'email' => null,
            'phone' => null,
            'password' => null,
            // the status is checked below, so that an account waiting for its
            // e-mail confirmation can be told apart from one that does not exist
            'status' => null,
        ];
        $credentials = array_merge($default, $credentials);
        $user = $this->userService->read($credentials);

        if (is_a($user, User::class) && $user->status === UserStatus::CONFIRMATION) {
            throw new UserNotConfirmedException();
        }

        if (is_a($user, User::class) && $user->status === UserStatus::WORK) {
            if (!password_verify((string) $credentials['password'], (string) $user->password)) {
                throw new WrongPasswordException();
            }

            return $user;
        }

        throw new UserNotFoundException();
    }

    public function register(array $data): ?User
    {
        return $this->userService->create($data);
    }

    public function logout(string $token): void
    {
        try {
            $this->userTokenService->delete(
                $this->userTokenService->read(['unique' => $token])
            );
        } catch (TokenNotFoundException $e) {
            // nothing
        }
    }

    /**
     * @throws TokenNotFoundException
     */
    public function refresh(string $token, array $params): ?UserToken
    {
        try {
            $presented = $token;
            $token = $this->userTokenService->read([
                'unique' => $presented,
                'agent' => $params['agent'],
            ]);
        } catch (TokenNotFoundException $e) {
            // the value that was replaced by a refresh seconds ago still counts
            $token = $presented === '' ? null : UserToken::query()
                ->where('previous', $presented)
                ->where('agent', $params['agent'])
                ->where('date', '>=', \Illuminate\Support\Carbon::now()->subSeconds(self::ROTATION_GRACE))
                ->first();

            if (!$token) {
                throw $e;
            }
        }

        // a session of an account that was blocked or deleted meanwhile must not live on
        if ($token->user->status !== UserStatus::WORK) {
            return null;
        }

        $expired = $token->date->getTimestamp() + \App\Domain\References\Date::MONTH;

        if ($expired >= time()) {
            return $token;
        }

        return null;
    }

    public function revoke(string $token, ?string $uuid): void
    {
        try {
            $current = $this->userTokenService->read(['unique' => $token]);
        } catch (TokenNotFoundException $e) {
            return;
        }

        // scoped to the owner of the presented token, otherwise anyone could
        // hand in a uuid and drop somebody else's session
        $query = $current->user->tokens();

        if ($uuid) {
            $query->where('uuid', $uuid);
        } else {
            $query->where('unique', '!=', $token);
        }

        /** @var UserToken $item */
        foreach ($query->get() as $item) {
            $this->userTokenService->delete($item);
        }
    }
}
