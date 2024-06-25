<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogCategory;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class CategoryService extends AbstractService
{
    /**
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): CatalogCategory
    {
        $default = [
            'attributes' => [],
        ];
        $data = array_merge($default, $data);

        $category = new CatalogCategory();
        $category->fill($data);

        if (!$category->title) {
            throw new MissingTitleValueException();
        }

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && blank($data['address'])) {
            $category->address = implode('/', array_filter([$category->parent->address ?? '', $category->title ?? uniqid()], fn ($el) => (bool) $el));
        }

        // check unique
        $found = CatalogCategory::firstWhere([
            'parent_uuid' => $category->parent_uuid,
            'address' => $category->address,
            'external_id' => $category->external_id,
        ]);
        if ($found) {
            throw new AddressAlreadyExistsException();
        }

        $category->save();

        // get attributes from parent
        if (($attributes = $category->parent?->attributes)) {
            $data['attributes'] = $attributes->pluck('uuid')->merge($data['attributes'] ?? [])->unique();
        }

        // sync attributes
        if ($data['attributes']) {
            $category->attributes()->sync($data['attributes']);
        }

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     *
     * @return CatalogCategory|Collection
     */
    public function read(array $data = [])
    {
        $default = [
            'parent_uuid' => '',
            'uuid' => null,
            'is_allow_nested' => null,
            'is_hidden' => null,
            'title' => null,
            'address' => null,
            'status' => null,
            'external_id' => null,
            'export' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['parent_uuid'] !== '') {
            $criteria['parent_uuid'] = $data['parent_uuid'];
        }
        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['is_allow_nested'] !== null) {
            $criteria['is_allow_nested'] = $data['is_allow_nested'];
        }
        if ($data['is_hidden'] !== null) {
            $criteria['is_hidden'] = $data['is_hidden'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['status'] !== null) {
            if (is_array($data['status'])) {
                $statuses = array_intersect($data['status'], \App\Domain\Casts\Catalog\Status::LIST);
            } else {
                $statuses = in_array($data['status'], \App\Domain\Casts\Catalog\Status::LIST, true) ? [$data['status']] : [];
            }

            if ($statuses) {
                $criteria['status'] = $statuses;
            }
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }
        if ($data['export'] !== null) {
            $criteria['export'] = $data['export'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                /** @var CatalogCategory $catalogCategory */
                $catalogCategory = CatalogCategory::firstWhere($criteria);

                return $catalogCategory ?: throw new CategoryNotFoundException();

            default:
                $query = CatalogCategory::query();
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
     * @param CatalogCategory|string|Uuid $entity
     *
     * @throws AddressAlreadyExistsException
     * @throws CategoryNotFoundException
     */
    public function update($entity, array $data = []): CatalogCategory
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, CatalogCategory::class)) {
            $entity->fill($data);

            // if address generation is enabled
            if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && blank($data['address'])) {
                $entity->address = implode('/', array_filter([$entity->parent->address ?? '', $entity->title ?? uniqid()], fn ($el) => (bool) $el));
            }

            if ($entity->isDirty('parent_uuid') || $entity->isDirty('address') || $entity->isDirty('external_id')) {
                // check unique
                $found = CatalogCategory::firstWhere([
                    'parent_uuid' => $entity->parent_uuid,
                    'address' => $entity->address,
                    'external_id' => $entity->external_id,
                ]);
                if ($found && $found->uuid !== $entity->uuid) {
                    throw new AddressAlreadyExistsException();
                }
            }

            $entity->save();

            // sync attributes
            if (isset($data['attributes'])) {
                $entity->attributes()->sync($data['attributes']);
            }

            return $entity;
        }

        throw new CategoryNotFoundException();
    }

    /**
     * @param CatalogCategory|string|Uuid $entity
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

        if (is_object($entity) && is_a($entity, CatalogCategory::class)) {
            $entity->files()->detach();
            $entity->delete();

            return true;
        }

        throw new CategoryNotFoundException();
    }
}
