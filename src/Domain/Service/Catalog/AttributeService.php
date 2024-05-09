<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogAttribute;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class AttributeService extends AbstractService
{
    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): CatalogAttribute
    {
        $attribute = new CatalogAttribute();
        $attribute->fill($data);

        if (!$attribute->title) {
            throw new MissingTitleValueException();
        }

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $attribute->address = implode('-', array_filter([$attribute->group ?? '', $attribute->title ?? uniqid()], fn ($el) => (bool) $el));
        }

        if (CatalogAttribute::firstWhere(['title' => $attribute->title]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        if (CatalogAttribute::firstWhere(['address' => $attribute->address]) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $attribute->save();

        return $attribute;
    }

    /**
     * @throws AttributeNotFoundException
     */
    public function read(array $data = []): CatalogAttribute|Collection
    {
        $default = [
            'uuid' => null,
            'title' => null,
            'address' => null,
            'type' => null,
            'group' => null,
            'is_filter' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['type'] !== null) {
            $criteria['type'] = $data['type'];
        }
        if ($data['group'] !== null) {
            $criteria['group'] = $data['group'];
        }
        if ($data['is_filter'] !== null) {
            $criteria['is_filter'] = $data['is_filter'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
                /** @var CatalogAttribute $attribute */
                $attribute = CatalogAttribute::firstWhere($criteria);

                return $attribute ?: throw new AttributeNotFoundException();

            default:
                $query = CatalogAttribute::query();
                /** @var Builder $query */
                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $query->orWhereIn($key, $value);
                    } else {
                        $query->orWhere($key, $value);
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
     * @param CatalogAttribute|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws AttributeNotFoundException
     * @throws AddressAlreadyExistsException
     */
    public function update($entity, array $data = []): CatalogAttribute
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, CatalogAttribute::class)) {
            $entity->fill($data);

            // if address generation is enabled
            if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
                $entity->address = implode('-', array_filter([$entity->group ?? '', $entity->title ?? uniqid()], fn ($el) => (bool) $el));
            }

            if ($entity->isDirty('title')) {
                $found = CatalogAttribute::firstWhere(['title' => $entity->title]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new TitleAlreadyExistsException();
                }
            }

            if ($entity->isDirty('address')) {
                $found = CatalogAttribute::firstWhere(['address' => $entity->address]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new AddressAlreadyExistsException();
                }
            }

            $entity->save();

            return $entity;
        }

        throw new AttributeNotFoundException();
    }

    /**
     * @param CatalogAttribute|string|Uuid $entity
     *
     * @throws AttributeNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, CatalogAttribute::class)) {
            $entity->delete();

            return true;
        }

        throw new AttributeNotFoundException();
    }
}
