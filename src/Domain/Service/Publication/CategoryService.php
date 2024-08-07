<?php declare(strict_types=1);

namespace App\Domain\Service\Publication;

use App\Domain\AbstractService;
use App\Domain\Models\PublicationCategory;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\CategoryNotFoundException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class CategoryService extends AbstractService
{
    /**
     * @throws MissingTitleValueException
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): PublicationCategory
    {
        $category = new PublicationCategory();
        $category->fill($data);

        if (!$category->title) {
            throw new MissingTitleValueException();
        }

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && (!isset($data['address']) || blank($data['address']))) {
            $category->address = implode('/', array_filter([$category->parent->address ?? '', $category->title ?? uniqid()], fn ($el) => (bool) $el));
        }

        if (PublicationCategory::firstWhere(['title' => $category->title]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        if (PublicationCategory::firstWhere(['address' => $category->address]) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $category->save();

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     *
     * @return Collection|PublicationCategory
     */
    public function read(array $data = [])
    {
        $default = [
            'parent_uuid' => '',
            'uuid' => null,
            'title' => null,
            'address' => null,
            'parent' => null,
            'is_public' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['parent_uuid'] !== '') {
            $criteria['parent_uuid'] = $data['parent_uuid'];
        }
        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['parent'] !== null) {
            $criteria['parent'] = $data['parent'];
        }
        if ($data['is_public'] !== null) {
            $criteria['is_public'] = (bool) $data['is_public'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
                /** @var PublicationCategory $publicationCategory */
                $publicationCategory = PublicationCategory::firstWhere($criteria);

                return $publicationCategory ?: throw new CategoryNotFoundException();

            default:
                $query = PublicationCategory::query();
                /** @var Builder $query */
                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
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
     * @param PublicationCategory|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     * @throws CategoryNotFoundException
     */
    public function update($entity, array $data = []): PublicationCategory
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, PublicationCategory::class)) {
            $entity->fill($data);

            // if address generation is enabled
            if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && (!isset($data['address']) || blank($data['address']))) {
                $entity->address = implode('/', array_filter([$entity->parent->address ?? '', $entity->title ?? uniqid()], fn ($el) => (bool) $el));
            }

            if ($entity->isDirty('title')) {
                $found = PublicationCategory::firstWhere(['title' => $entity->title]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new TitleAlreadyExistsException();
                }
            }

            if ($entity->isDirty('address')) {
                $found = PublicationCategory::firstWhere(['address' => $entity->address]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new AddressAlreadyExistsException();
                }
            }

            $entity->save();

            return $entity;
        }

        throw new CategoryNotFoundException();
    }

    /**
     * @param PublicationCategory|string|Uuid $entity
     *
     * @throws CategoryNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, PublicationCategory::class)) {
            $entity->files()->detach();
            $entity->delete();

            return true;
        }

        throw new CategoryNotFoundException();
    }
}
