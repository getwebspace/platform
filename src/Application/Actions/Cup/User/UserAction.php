<?php

namespace Application\Actions\Cup\User;

use Application\Actions\Action;
use DateTime;
use Exception;
use Psr\Container\ContainerInterface;

abstract class UserAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $userRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userRepository = $this->entityManager->getRepository(\Domain\Entities\User::class);
    }
}
