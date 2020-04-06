<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

abstract class UserAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $userRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userRepository = $this->entityManager->getRepository(\App\Domain\Entities\User::class);
    }
}
