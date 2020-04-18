<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File;

use App\Domain\AbstractAction;
use Psr\Container\ContainerInterface;

abstract class FileAction extends AbstractAction
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $fileRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);
    }
}
