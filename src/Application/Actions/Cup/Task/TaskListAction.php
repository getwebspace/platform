<?php

namespace App\Application\Actions\Cup\Task;

use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

class TaskListAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $taskRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->taskRepository = $this->entityManager->getRepository(\App\Domain\Entities\Task::class);
    }

    protected function action(): \Slim\Http\Response
    {
        return $this->respondWithData(
            $this->taskRepository->findBy([], ['status', 'desc'], 25)
        );
    }
}
