<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

use App\Domain\AbstractAction;
use Psr\Container\ContainerInterface;

abstract class PageAction extends AbstractAction
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $pageRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->pageRepository = $this->entityManager->getRepository(\App\Domain\Entities\Page::class);
    }
}
