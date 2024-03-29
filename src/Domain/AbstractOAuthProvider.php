<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Entities\User;
use App\Domain\Entities\User\Integration as UserIntegration;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\IntegrationService as UserIntegrationService;
use App\Domain\Service\User\UserService;
use App\Domain\Traits\ParameterTrait;
use Psr\Container\ContainerInterface;

abstract class AbstractOAuthProvider
{
    use ParameterTrait;

    protected ContainerInterface $container;

    protected UserService $userService;

    protected UserGroupService $userGroupService;

    protected UserIntegrationService $userIntegrationService;

    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->userService = $container->get(UserService::class);
        $this->userGroupService = $container->get(UserGroupService::class);
        $this->userIntegrationService = $container->get(UserIntegrationService::class);
    }

    abstract public function getAuthUrl(): string;

    abstract public function getToken($data): array;

    abstract public function getInfo($data): array;

    abstract public function callback(array $token, ?User $current_user = null): ?UserIntegration;
}
