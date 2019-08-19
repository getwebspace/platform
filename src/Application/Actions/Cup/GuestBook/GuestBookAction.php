<?php

namespace Application\Actions\Cup\GuestBook;

use Application\Actions\Action;
use DateTime;
use Exception;
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

        $this->gbookRepository = $this->entityManager->getRepository(\Domain\Entities\GuestBook::class);
    }
}
