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

        $this->userService = UserService::getWithContainer($container);
        $this->userGroupService = UserGroupService::getWithContainer($container);
        $this->userIntegrationService = UserIntegrationService::getWithContainer($container);
    }

    abstract public function getAuthUrl(): string;

    abstract public function getToken($data): array;

    abstract public function getInfo($data): array;

    abstract public function callback(array $token, ?User $current_user = null): ?UserIntegration;
}
