<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogCategory;
use App\Domain\Repository\Catalog\CategoryRepository;
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
            'parent_uuid' => null,
            'children' => false,
            'hidden' => false,
            'title' => '',
            'description' => '',
            'address' => '',
            'status' => \App\Domain\Casts\Catalog\Status::WORK,
            'pagination' => 10,
            'order' => 1,
            'sort' => [],
            'meta' => [],
            'template' => [],
            'external_id' => '',
            'export' => 'manual',
            'system' => '',

            'attributes' => [],
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        $category = new CatalogCategory;
        $category->fill($data);

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $category->address = implode('/', array_filter([$category->parent->address ?? '', $category->address ?? $category->title ?? uniqid()], fn ($el) => (bool) $el));
        }

        // todo attributes
//        // retrieve category by uuid
//        if (!is_a($data['parent'], CatalogCategory::class) && $data['parent_uuid']) {
//            $data['parent'] = $this->read(['uuid' => $data['parent_uuid']]);
//
//            // copy attributes from parent
//            if ($data['parent']->hasAttributes()) {
//                $data['attributes'] = array_merge(
//                    from_service_to_array($data['parent']->getAttributes()),
//                    $data['attributes']
//                );
//            }
//        }

        // check unique
        $found = CatalogCategory::firstWhere([
            'parent_uuid' => $category->parent_uuid,
            'address' => $category->address,
            'external_id' => $category->external_id
        ]);
        if ($found) {
            throw new AddressAlreadyExistsException();
        }

        $category->save();

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
            'children' => null,
            'hidden' => null,
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
        if ($data['children'] !== null) {
            $criteria['children'] = $data['children'];
        }
        if ($data['hidden'] !== null) {
            $criteria['hidden'] = $data['hidden'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['status'] !== null && in_array($data['status'], \App\Domain\Casts\Catalog\Status::LIST, true)) {
            $criteria['status'] = $data['status'];
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
                $query = CatalogCategory::where($criteria);
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
            $default = [
                'parent' => null,
                'parent_uuid' => null,
                'children' => null,
                'hidden' => null,
                'title' => null,
                'description' => null,
                'address' => null,
                'attributes' => null,
                'status' => null,
                'pagination' => null,
                'order' => null,
                'sort' => null,
                'meta' => null,
                'template' => null,
                'external_id' => null,
                'export' => null,
                'system' => null,
            ];
            $data = array_filter(array_merge($default, $data), fn ($v) => $v !== null);

            if ($data !== $default) {
                $entity->fill($data);

                // if address generation is enabled
                if ($entity->isDirty('address') && $this->parameter('common_auto_generate_address', 'no') === 'yes') {
                    $entity->address = implode('/', array_filter([$entity->parent->address ?? '', $entity->address ?? $entity->title ?? uniqid()], fn ($el) => (bool) $el));
                }

                if ($entity->isDirty('parent_uuid') || $entity->isDirty('address') || $entity->isDirty('external_id')) {
                    // check unique
                    $found = CatalogCategory::firstWhere([
                        'parent_uuid' => $entity->parent_uuid,
                        'address' => $entity->address,
                        'external_id' => $entity->external_id
                    ]);
                    if ($found && $found->uuid !== $entity->uuid) {
                        throw new AddressAlreadyExistsException();
                    }
                }

                $entity->save();
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
