<?php

namespace App\Application\Actions\Common\File;

use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

abstract class FileAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $fileRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);
    }
}
