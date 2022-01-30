<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\AbstractAction;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\SessionService as UserSessionService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class UserAction extends AbstractAction
{
    protected UserService $userService;

    protected UserSessionService $userSessionService;

    protected UserGroupService $userGroupService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = $this->container->get(UserService::class);
        $this->userSessionService = $this->container->get(UserSessionService::class);
        $this->userGroupService = $this->container->get(UserGroupService::class);
    }
}
