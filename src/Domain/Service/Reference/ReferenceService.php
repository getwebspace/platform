<?php declare(strict_types=1);

namespace App\Domain\Service\Reference;

use App\Domain\AbstractService;
use App\Domain\Models\Reference;
use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\MissingTypeValueException;
use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class ReferenceService extends AbstractService
{
    protected static array $search_columns = ['title'];

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     */
    public function create(array $data = []): Reference
    {
        $reference = new Reference();
        $reference->fill($data);

        if (!$reference->title) {
            throw new MissingTitleValueException();
        }

        if (!$reference->type) {
            throw new MissingTypeValueException();
        }

        if (Reference::firstWhere(['title' => $reference->title, 'type' => $reference->type]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        $reference->save();

        return $reference;
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
        if ($data['type'] !== null && ($types = $this->limitByList($data['type'], \App\Domain\Casts\Reference\Type::LIST))) {
            $criteria['type'] = $types;
        }
        if ($data['status'] !== null) {
            $criteria['status'] = (bool) $data['status'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
                /** @var Reference $reference */
                $reference = $this->buildQuery(Reference::query(), $criteria, $data)->first();

                return $reference ?: throw new ReferenceNotFoundException();

            default:
                return $this->buildQuery(Reference::query(), $criteria, $data)->get();
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
            $entity->fill($data);

            if ($entity->isDirty('title') || $entity->isDirty('type')) {
                $found = Reference::firstWhere(['title' => $entity->title, 'type' => $entity->type]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new TitleAlreadyExistsException();
                }
            }

            $entity->save();

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
