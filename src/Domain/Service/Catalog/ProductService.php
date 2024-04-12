<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogAttribute;
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
            'relation' => [],
        ];
        $data = array_merge($default, $data);

        $product = new CatalogProduct;
        $product->fill($data);

        if (!$product->title) {
            throw new MissingTitleValueException();
        }
        if (!$product->category_uuid) {
            throw new MissingCategoryValueException();
        }

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $product->address = implode('/', array_filter([$product->category->address ?? '', $product->address ?? $product->title ?? uniqid()], fn ($el) => (bool) $el));
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

        // get attributes from parent
        if (($attributes = $product->category?->attributes)) {
            $data['attributes'] = $attributes->pluck('uuid')->merge($data['attributes'] ?? [])->unique();
        }

        // sync attributes
        if (isset($data['attributes'])) {

            foreach ($data['attributes'] as $uuid => $value) {
                $product->attributes()->attach($uuid, ['value' => $value]);
            }
        }

        // add relation products
        //$this->catalogProductRelationService->process($product, $data['relation']);

        return $product;
    }

    /**
     * @throws ProductNotFoundException
     *
     * @return Collection|CatalogProduct
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
            case !is_array($data['vendorcode']) && $data['vendorcode'] !== null:
            case !is_array($data['barcode']) && $data['barcode'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                /** @var CatalogProduct $catalogProduct */
                $catalogProduct = CatalogProduct::firstWhere($criteria);

                return $catalogProduct ?: throw new ProductNotFoundException();

            default:
                $query = CatalogProduct::where($criteria);
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
            if ($entity->isDirty('address') && $this->parameter('common_auto_generate_address', 'no') === 'yes') {
                $entity->address = implode('/', array_filter([$entity->category->address ?? '', $entity->address ?? $entity->title ?? uniqid()], fn ($el) => (bool) $el));
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

            // sync attributes
            if (isset($data['attributes'])) {
                $entity->attributes()->detach();

                foreach ($data['attributes'] as $uuid => $value) {
                    $entity->attributes()->attach($uuid, ['value' => $value]);
                }
            }

//                if ($data['relation'] !== null) {
//                    // update relation products
//                    $this->catalogProductRelationService->process($entity, $data['relation']);
//                }

            $entity->save();

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
