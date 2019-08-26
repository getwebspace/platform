<?php

namespace App\Application\Actions\Cup\Page;

use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

abstract class PageAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $pageRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->pageRepository = $this->entityManager->getRepository(\App\Domain\Entities\Page::class);
    }
}
