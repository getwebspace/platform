<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

use App\Domain\AbstractAction;
use Psr\Container\ContainerInterface;

abstract class GuestBookAction extends AbstractAction
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $gbookRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->gbookRepository = $this->entityManager->getRepository(\App\Domain\Entities\GuestBook::class);
    }
}
