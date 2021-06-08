<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\AbstractAction;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\SessionService as UserSessionService;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class UserAction extends AbstractAction
{
    protected UserService $userService;

    protected UserSessionService $userSessionService;

    protected UserGroupService $userGroupService;

    protected UserSubscriberService $userSubscriberService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = UserService::getWithContainer($container);
        $this->userSessionService = UserSessionService::getWithContainer($container);
        $this->userGroupService = UserGroupService::getWithContainer($container);
        $this->userSubscriberService = UserSubscriberService::getWithContainer($container);
    }
}
