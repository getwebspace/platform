<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Application\Middlewares\AccessCheckerMiddleware;
use App\Domain\AbstractAction;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use App\Domain\Service\User\UserService;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;

abstract class UserAction extends AbstractAction
{
    /**
     * @var UserService
     */
    protected UserService $userService;

    /**
     * @var UserGroupService
     */
    protected UserGroupService $userGroupService;

    /**
     * @var UserSubscriberService
     */
    protected UserSubscriberService $userSubscriberService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = UserService::getWithContainer($container);
        $this->userGroupService = UserGroupService::getWithContainer($container);
        $this->userSubscriberService = UserSubscriberService::getWithContainer($container);
    }

    /**
     * @return Collection
     */
    protected function getRoutes(): Collection
    {
        return collect($this->container->get('router')->getRoutes())
            ->flatten()
            ->map(fn ($item) => $item->getName())
            ->filter(fn ($item) => !str_start_with($item, AccessCheckerMiddleware::PUBLIC));
    }
}
