<?php

namespace App\Application\Actions\Api\Publication;

use App\Application\Actions\Api\ActionApi;
use Psr\Container\ContainerInterface;

abstract class PublicationAction extends ActionApi
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $publicationRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $categoryRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->publicationRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication::class);
        $this->categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication\Category::class);
    }
}
