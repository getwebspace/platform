<?php

namespace App\Application\Actions\Cup\GuestBook;

use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

abstract class GuestBookAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $gbookRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->gbookRepository = $this->entityManager->getRepository(\App\Domain\Entities\GuestBook::class);
    }
}
