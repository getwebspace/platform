<?php

namespace Application\Actions\Cup\Form;

use Application\Actions\Action;
use DateTime;
use Exception;
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

        $this->formRepository = $this->entityManager->getRepository(\Domain\Entities\Form::class);
        $this->dataRepository = $this->entityManager->getRepository(\Domain\Entities\Form\Data::class);
        $this->fileRepository = $this->entityManager->getRepository(\Domain\Entities\File::class);
    }
}
