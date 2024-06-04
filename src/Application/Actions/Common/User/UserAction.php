<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Application\Auth;
use App\Domain\AbstractAction;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class UserAction extends AbstractAction
{
    protected Auth $auth;

    protected UserService $userService;

    protected UserSubscriberService $userSubscriberService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->auth = $container->get(Auth::class);
        $this->userService = $container->get(UserService::class);
        $this->userSubscriberService = $container->get(UserSubscriberService::class);
    }
}
