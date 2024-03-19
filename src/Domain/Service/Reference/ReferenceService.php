<?php declare(strict_types=1);

namespace App\Domain\Service\Reference;

use App\Domain\AbstractService;
use App\Domain\Models\Page;
use App\Domain\Models\Reference;
use App\Domain\Repository\ReferenceRepository;
use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\MissingTypeValueException;
use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class ReferenceService extends AbstractService
{
    protected function init(): void
    {
    }

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     */
    public function create(array $data = []): Reference
    {
        $default = [
            'type' => '',
            'title' => '',
            'value' => [],
            'order' => 1,
            'status' => true,
        ];
        $data = array_merge($default, $data);

        if (!$data['type']) {
            throw new MissingTypeValueException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if ($data['title'] && Reference::firstWhere(['title' => $data['title']]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        return Reference::create($data);
    }

    /**
     * @throws ReferenceNotFoundException
     *
     * @return Collection|Reference
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'title' => null,
            'type' => null,
            'status' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['type'] !== null && in_array($data['type'], \App\Domain\Casts\Reference\Type::LIST, true)) {
            $criteria['type'] = $data['type'];
        }
        if ($data['status'] !== null) {
            $criteria['status'] = (bool) $data['status'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null && $data['type'] !== null:
                /** @var Reference $reference */
                $reference = Reference::firstWhere($criteria);

                return $reference ?: throw new ReferenceNotFoundException();

            default:
                $query = Reference::where($criteria);
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
     * @param Reference|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws ReferenceNotFoundException
     */
    public function update($entity, array $data = []): Reference
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Reference::class)) {
            $default = [
                'type' => null,
                'title' => null,
                'value' => null,
                'order' => null,
                'status' => null,
            ];
            $data = array_filter(array_merge($default, $data), fn ($v) => $v !== null);

            if ($data !== $default) {
                $entity->update($data);
            }

            return $entity;
        }

        throw new ReferenceNotFoundException();
    }

    /**
     * @param Reference|string|Uuid $entity
     *
     * @throws ReferenceNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Reference::class)) {
            $entity->delete();

            return true;
        }

        throw new ReferenceNotFoundException();
    }
}
