<?php declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;

class BasicAuthProvider extends AbstractAuthProvider
{
    /**
     * @throws WrongPasswordException
     * @throws UserNotFoundException
     */
    public function login(array $credentials, array $params): ?User
    {
        $user = $this->userService->read($credentials);

        if (is_a($user, User::class)) {
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

    /** @throws TokenNotFoundException */
    public function refresh(string $token, array $params): ?UserToken
    {
        $token = $this->userTokenService->read([
            'unique' => $token,
            'agent' => $params['agent'],
        ]);
        $expired = $token->date->getTimestamp() + \App\Domain\References\Date::MONTH;

        if ($expired >= time()) {
            return $token;
        }

        return null;
    }

    public function revoke(string $token, ?string $uuid): void
    {
        try {
            if ($uuid) {
                $this->userTokenService->delete(
                    $this->userTokenService->read(['uuid' => $uuid])
                );
            } else {
                /** @var UserToken $item */
                foreach ($this->userTokenService->read(['unique' => $token])->user->tokens()->where('unique', '!=', $token)->get() as $item) {
                    $this->userTokenService->delete($item);
                }
            }
        } catch (TokenNotFoundException $e) {
            // nothing
        }
    }
}
