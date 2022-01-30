<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Entities\User;
use App\Domain\Entities\User\Integration as UserIntegration;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\IntegrationService as UserIntegrationService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class AbstractOAuthProvider extends AbstractComponent
{
    protected UserService $userService;

    protected UserGroupService $userGroupService;

    protected UserIntegrationService $userIntegrationService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = $container->get(UserService::class);
        $this->userGroupService = $container->get(UserGroupService::class);
        $this->userIntegrationService = $container->get(UserIntegrationService::class);
    }

    abstract public function getAuthUrl(): string;

    abstract public function getToken($data): array;

    abstract public function getInfo($data): array;

    abstract public function callback(array $token, ?User $current_user = null): ?UserIntegration;
}
