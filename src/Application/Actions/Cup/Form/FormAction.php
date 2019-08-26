<?php

namespace App\Application\Actions\Cup\Form;

use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

abstract class FormAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $formRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $dataRepository;

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

        $this->formRepository = $this->entityManager->getRepository(\App\Domain\Entities\Form::class);
        $this->dataRepository = $this->entityManager->getRepository(\App\Domain\Entities\Form\Data::class);
        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);
    }
}
