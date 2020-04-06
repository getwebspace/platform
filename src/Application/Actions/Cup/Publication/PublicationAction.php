<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

abstract class PublicationAction extends Action
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
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->publicationRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication::class);
        $this->categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication\Category::class);
    }
}
