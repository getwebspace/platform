<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingCategoryValueException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class ProductService extends AbstractService
{
    /**
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): CatalogProduct
    {
        $default = [
            'attributes' => [],
            'relations' => [],
        ];
        $data = array_merge($default, $data);

        $product = new CatalogProduct();
        $product->fill($data);

        if (!$product->title) {
            throw new MissingTitleValueException();
        }
        if (!$product->category_uuid) {
            throw new MissingCategoryValueException();
        }

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $product->address = implode('/', array_filter([$product->category->address ?? '', $product->title ?? uniqid()], fn ($el) => (bool) $el));
        }

        // check unique
        $found = CatalogProduct::firstWhere([
            'category_uuid' => $product->getAttributes()['category_uuid'],
            'address' => $product->getAttributes()['address'],
            'dimension' => $product->getAttributes()['dimension'],
            'external_id' => $product->getAttributes()['external_id'],
        ]);
        if ($found) {
            throw new AddressAlreadyExistsException();
        }

        $product->save();

        // sync attributes
        if ($data['attributes']) {
            $product->attributes()->sync(
                collect($data['attributes'])->map(fn ($value) => ['value' => $value])->filter(fn ($item) => !blank($item['value']))
            );
        }

        // sync relations
        if ($data['relations']) {
            $product->relations()->sync(
                collect($data['relations'])->map(fn ($count) => ['count' => floatval($count)])->filter(fn ($item) => $item['count'] > 0)
            );
        }

        return $product;
    }

    /**
     * @throws ProductNotFoundException
     *
     * @return CatalogProduct|Collection
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'category_uuid' => null,
            'title' => null,
            'type' => null,
            'address' => null,
            'vendorcode' => null,
            'barcode' => null,
            'special' => null,
            'status' => null,
            'external_id' => null,
            'export' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['category_uuid'] !== null) {
            $criteria['category_uuid'] = $data['category_uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['type'] !== null) {
            $criteria['type'] = $data['type'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['vendorcode'] !== null) {
            $criteria['vendorcode'] = $data['vendorcode'];
        }
        if ($data['barcode'] !== null) {
            $criteria['barcode'] = $data['barcode'];
        }
        if ($data['special'] !== null) {
            $criteria['special'] = (bool) $data['special'];
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
            case !is_array($data['address']) && $data['address'] !== null:
            case !is_array($data['vendorcode']) && $data['vendorcode'] !== null:
            case !is_array($data['barcode']) && $data['barcode'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                /** @var CatalogProduct $catalogProduct */
                $catalogProduct = CatalogProduct::firstWhere($criteria);

                return $catalogProduct ?: throw new ProductNotFoundException();

            case !is_array($data['title']) && $data['title'] !== null:
                $query = CatalogProduct::query();
                /** @var Builder $query */
                $query->where('title', 'like', '%' . $data['title'] . '%');

                if ($data['limit']) {
                    $query = $query->limit($data['limit']);
                }
                if ($data['offset']) {
                    $query = $query->offset($data['offset']);
                }

                return $query->get();

            default:
                $query = CatalogProduct::query();
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
     * @param CatalogProduct|string|Uuid $entity
     *
     * @throws AddressAlreadyExistsException
     * @throws ProductNotFoundException
     */
    public function update($entity, array $data = []): CatalogProduct
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, CatalogProduct::class)) {
            $entity->fill($data);

            // if address generation is enabled
            if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
                $entity->address = implode('/', array_filter([$entity->category->address ?? '', $entity->title ?? uniqid()], fn ($el) => (bool) $el));
            }

            if ($entity->isDirty('category_uuid') || $entity->isDirty('address') || $entity->isDirty('dimension') || $entity->isDirty('external_id')) {
                // check unique
                $found = CatalogProduct::firstWhere([
                    'category_uuid' => $entity->getAttributes()['category_uuid'],
                    'address' => $entity->getAttributes()['address'],
                    'dimension' => $entity->getAttributes()['dimension'],
                    'external_id' => $entity->getAttributes()['external_id'],
                ]);
                if ($found && $found->uuid !== $entity->uuid) {
                    throw new AddressAlreadyExistsException();
                }
            }

            $entity->save();

            // sync attributes
            if (isset($data['attributes'])) {
                $entity->attributes()->sync(
                    collect($data['attributes'])->map(fn ($value) => ['value' => $value])->filter(fn ($item) => !blank($item['value']))
                );
            }

            // sync relations
            if (isset($data['relations'])) {
                $entity->relations()->sync(
                    collect($data['relations'])->map(fn ($count) => ['count' => floatval($count)])->filter(fn ($item) => $item['count'] > 0)
                );
            }

            return $entity;
        }

        throw new ProductNotFoundException();
    }

    /**
     * @param CatalogProduct|string|Uuid $entity
     *
     * @throws ProductNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, CatalogProduct::class)) {
            $entity->files()->detach();
            $entity->delete();

            return true;
        }

        throw new ProductNotFoundException();
    }
}
