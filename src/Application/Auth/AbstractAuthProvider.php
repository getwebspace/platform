<?php declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Exceptions\HttpRedirectException;
use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\TokenService as UserTokenService;
use App\Domain\Service\User\UserService;
use App\Domain\Traits\HasParameters;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter as Cache;

abstract class AbstractAuthProvider
{
    use HasParameters;

    protected ContainerInterface $container;

    protected Cache $cache;

    protected UserService $userService;

    protected UserTokenService $userTokenService;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->cache = $container->get(Cache::class);
        $this->userService = $container->get(UserService::class);
        $this->userTokenService = $container->get(UserTokenService::class);
    }

    /** @throws HttpRedirectException */
    abstract public function login(array $credentials, array $params): ?User;

    abstract public function register(array $data): ?User;

    abstract public function logout(string $token): void;

    /** @throws TokenNotFoundException */
    abstract public function refresh(string $token, array $params): ?UserToken;

    abstract public function revoke(string $token, ?string $uuid): void;
}
