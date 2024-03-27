<?php declare(strict_types=1);

namespace App\Domain\Service\Task;

use App\Domain\AbstractService;
use App\Domain\Models\Task;
use App\Domain\Service\Task\Exception\MissingActionValueException;
use App\Domain\Service\Task\Exception\MissingTitleValueException;
use App\Domain\Service\Task\Exception\TaskNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class TaskService extends AbstractService
{
    protected function init(): void
    {
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
            'status' => \App\Domain\Casts\Task\Status::QUEUE,
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

        return Task::create($data);
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

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
                /** @var Task $task */
                $task = Task::firstWhere($criteria);

                return $task ?: throw new TaskNotFoundException();

            default:
                $query = Task::where($criteria);
                /** @var Builder $query */

                foreach ($data['order'] as $column => $direction) {
                    $query = $query->orderBy($column, $direction);
                }
                if ($data['limit']) {
                    $query = $query->limit($data['limit']);
                }
                if ($data['offset']) {
                    $query = $query->offset($data['offset']);
                }

                return $query->get();
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
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

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
            $data = array_filter(array_merge($default, $data), fn ($v) => $v !== null);

            if ($data !== $default) {
                $entity->update($data);
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
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Task::class)) {
            $entity->delete();

            return true;
        }

        throw new TaskNotFoundException();
    }
}
