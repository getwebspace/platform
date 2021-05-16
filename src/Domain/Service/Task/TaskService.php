<?php declare(strict_types=1);

namespace App\Domain\Service\Task;

use App\Domain\AbstractService;
use App\Domain\Entities\Task;
use App\Domain\Repository\TaskRepository;
use App\Domain\Service\Task\Exception\MissingActionValueException;
use App\Domain\Service\Task\Exception\MissingTitleValueException;
use App\Domain\Service\Task\Exception\TaskNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class TaskService extends AbstractService
{
    /**
     * @var TaskRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Task::class);
    }

    /**
     * @throws MissingTitleValueException
     * @throws MissingActionValueException
     */
    public function create(array $data = []): Task
    {
        $default = [
            'title' => '',
            'action' => '',
            'progress' => 0,
            'status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE,
            'params' => [],
            'output' => '',
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if (!$data['action']) {
            throw new MissingActionValueException();
        }

        $task = (new Task())
            ->setTitle($data['title'])
            ->setAction($data['action'])
            ->setProgress($data['progress'])
            ->setStatus($data['status'])
            ->setParams($data['params'])
            ->setOutput($data['output'])
            ->setDate($data['date']);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * @throws TaskNotFoundException
     *
     * @return Collection|Task
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'action' => null,
            'status' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['action'] !== null) {
            $criteria['action'] = $data['action'];
        }
        if ($data['status'] !== null) {
            $criteria['status'] = $data['status'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                    $entry = $this->service->findOneBy($criteria);

                    if (empty($entry)) {
                        throw new TaskNotFoundException();
                    }

                    return $entry;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string|Task|Uuid $entity
     *
     * @throws TaskNotFoundException
     */
    public function update($entity, array $data = []): Task
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Task::class)) {
            $default = [
                'title' => null,
                'action' => null,
                'progress' => null,
                'status' => null,
                'params' => null,
                'output' => null,
                'date' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['title'] !== null) {
                    $entity->setTitle($data['title']);
                }
                if ($data['action'] !== null) {
                    $entity->setAction($data['action']);
                }
                if ($data['progress'] !== null) {
                    $entity->setProgress($data['progress']);
                }
                if ($data['status'] !== null) {
                    $entity->setStatus($data['status']);
                }
                if ($data['params'] !== null) {
                    $entity->setParams($data['params']);
                }
                if ($data['output'] !== null) {
                    $entity->setOutput($data['output']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new TaskNotFoundException();
    }

    /**
     * @param string|Task|Uuid $entity
     *
     * @throws TaskNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Task::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new TaskNotFoundException();
    }
}
