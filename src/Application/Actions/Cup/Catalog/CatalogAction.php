<?php

namespace Application\Actions\Cup\Catalog;

use Application\Actions\Action;
use DateTime;
use Exception;
use Psr\Container\ContainerInterface;

abstract class CatalogAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $categoryRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $productRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $orderRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->categoryRepository = $this->entityManager->getRepository(\Domain\Entities\Catalog\Category::class);
        $this->productRepository = $this->entityManager->getRepository(\Domain\Entities\Catalog\Product::class);
        $this->orderRepository = null; // todo
    }
}
