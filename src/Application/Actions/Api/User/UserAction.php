<?php declare(strict_types=1);

namespace App\Application\Actions\Api\User;

use App\Domain\AbstractAction;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use Psr\Container\ContainerInterface;

abstract class UserAction extends AbstractAction
{
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

        $this->userSubscriberService = UserSubscriberService::getWithContainer($container);
    }
}
