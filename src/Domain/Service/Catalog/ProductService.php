<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingCategoryValueException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class ProductService extends AbstractService
{
    protected static array $eager = ['category', 'attributes', 'files', 'relations', 'relations.category', 'relations.attributes', 'relations.files'];

    protected static array $search_columns = ['title'];

    /**
     * A value of an attribute that does not exist would be written to the table as it is: a row nobody can read or delete
     *
     * @throws AttributeNotFoundException
     */
    private function assertAttributesExist(mixed $attributes): void
    {
        $used = array_keys(array_filter((array) $attributes, static fn (mixed $value): bool => !blank($value)));

        if ($used && \App\Domain\Models\CatalogAttribute::query()->whereIn('uuid', $used)->count() !== count(array_unique($used))) {
            throw new AttributeNotFoundException();
        }
    }

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
        // a field the form did not send is null, which would replace the default of the record
        $data = array_merge($default, array_filter($data, static fn (mixed $value): bool => $value !== null));

        $product = new CatalogProduct();
        $product->fill($data);

        if (!$product->title) {
            throw new MissingTitleValueException();
        }
        if (!$product->category_uuid || !\App\Domain\Models\CatalogCategory::query()->whereKey($product->category_uuid)->exists()) {
            throw new MissingCategoryValueException();
        }

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && (!isset($data['address']) || blank($data['address']))) {
            $product->address = $this->uniqueAddress(implode('/', array_filter([$product->category->address ?? '', $product->title ?? uniqid()], fn ($el) => (bool) $el)), $product);
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

        $this->normalizeStockStatus($product);
        $this->assertAttributesExist($data['attributes']);

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

    protected function buildCriteria(array $data = []): array
    {
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
        if ($data['status'] !== null && ($statuses = $this->limitByList($data['status'], \App\Domain\Casts\Catalog\Status::LIST))) {
            $criteria['status'] = $statuses;
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }
        if ($data['export'] !== null) {
            $criteria['export'] = $data['export'];
        }

        return $criteria;
    }

    /**
     * @return int
     */
    public function count(array $data = []): int
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
        $criteria = $this->buildCriteria($data);

        $query = CatalogProduct::query();
        $this->applyCriteria($query, $criteria);
        $this->applySearch($query, $data['search'], static::$search_columns);

        return $query->count();
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
        $criteria = $this->buildCriteria($data);

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
            case !is_array($data['vendorcode']) && $data['vendorcode'] !== null:
            case !is_array($data['barcode']) && $data['barcode'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                /** @var null|CatalogProduct $catalogProduct */
                $catalogProduct = $this->buildQuery(CatalogProduct::query(), $criteria, $data)->first();

                return $catalogProduct ?: throw new ProductNotFoundException();

            default:
                $products = $this->buildQuery(CatalogProduct::query(), $criteria, $data)->get();

                // the groups of variants of the whole list in one query
                CatalogProduct::primeVariants($products);

                return $products;
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

            // as when it was made: a title, and a category that exists
            if (!$entity->title) {
                throw new MissingTitleValueException();
            }
            if (!$entity->category_uuid || !\App\Domain\Models\CatalogCategory::query()->whereKey($entity->category_uuid)->exists()) {
                throw new MissingCategoryValueException();
            }

            // if address generation is enabled
            if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && (!isset($data['address']) || blank($data['address']))) {
                $entity->address = $this->uniqueAddress(implode('/', array_filter([$entity->category->address ?? '', $entity->title ?? uniqid()], fn ($el) => (bool) $el)), $entity);
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

            $this->normalizeStockStatus($entity);
            $this->assertAttributesExist($data['attributes'] ?? []);

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

    /**
     * Products that customers bought together with the given one: those found in the same
     * orders (not in a canceled status: a status of kind `canceled`), the most frequent first. Only what the site shows (a product on sale, of a shown
     * category, not a service) and never the product itself. Each product carries `together`,
     * the number of orders it shares with the given one.
     *
     * @param CatalogProduct|string $product a product or its uuid
     *
     * @return Collection<int, CatalogProduct>
     */
    public function boughtTogether(CatalogProduct|string $product, int $limit = 4): Collection
    {
        $uuid = $product instanceof CatalogProduct ? (string) $product->uuid : $product;

        if ($limit < 1 || !\Ramsey\Uuid\Uuid::isValid($uuid)) {
            return new Collection();
        }

        // a variant of the same product is no recommendation: it is on the same page
        $address = $product instanceof CatalogProduct ? (string) $product->address : (string) CatalogProduct::query()->whereKey($uuid)->value('address');

        $ranked = CatalogProduct::query()
            ->toBase()
            ->from('catalog_order_product as a')
            ->join('catalog_order_product as b', 'a.order_uuid', '=', 'b.order_uuid')
            ->join('catalog_product as p', 'p.uuid', '=', 'b.product_uuid')
            ->join('catalog_category as c', 'c.uuid', '=', 'p.category_uuid')
            ->join('catalog_order as o', 'o.uuid', '=', 'a.order_uuid')
            ->leftJoin('reference as r', 'r.uuid', '=', 'o.status_uuid')
            ->where('a.product_uuid', $uuid)
            ->where(fn ($q) => $q->whereNull('r.kind')->orWhere('r.kind', '!=', \App\Domain\References\OrderStatus::KIND_CANCELED))
            ->where('b.product_uuid', '!=', $uuid)
            ->when($address !== '', fn ($q) => $q->where('p.address', '!=', $address))
            ->where('p.status', \App\Domain\Casts\Catalog\Status::WORK)
            ->where('p.type', \App\Domain\Casts\Catalog\Product\Type::PRODUCT)
            ->where('c.status', \App\Domain\Casts\Catalog\Status::WORK)
            ->where('c.is_hidden', false)
            ->groupBy('b.product_uuid')
            ->selectRaw('b.product_uuid as uuid, count(distinct b.order_uuid) as together')
            ->orderByDesc('together')
            ->orderBy('b.product_uuid')
            ->limit($limit)
            ->pluck('together', 'uuid');

        /** @var Collection<int, CatalogProduct> $found */
        $found = CatalogProduct::query()->whereIn('uuid', $ranked->keys())->get();
        CatalogProduct::primeVariants($found);

        return $found
            ->each(fn (CatalogProduct $p) => $p->setAttribute('together', (int) $ranked[(string) $p->uuid]))
            ->sortBy(fn (CatalogProduct $p) => array_search((string) $p->uuid, $ranked->keys()->all(), true))
            ->values();
    }

    /**
     * A stock status is a record of the stock status reference; anything else is no status
     */
    private function normalizeStockStatus(CatalogProduct $product): void
    {
        if (blank($product->stock_status_uuid) || !\App\Domain\Models\Reference::query()
            ->whereKey($product->stock_status_uuid)
            ->where('type', \App\Domain\Casts\Reference\Type::STOCK_STATUS)
            ->exists()
        ) {
            $product->stock_status_uuid = null;
        }
    }

    /**
     * An address a product is found by must belong to one product only: a generated one that
     * is taken by a different product gets the vendor code (or external id) as a suffix, then a
     * counter. The same title in the same category keeps the address: that is a variant.
     */
    private function uniqueAddress(string $address, CatalogProduct $product): string
    {
        // products of one category with one title are variants of a product that share its page
        // (the page lists every product of its address), so they are not "another" owner
        $taken = fn (string $candidate): bool => CatalogProduct::query()
            ->where('address', $candidate)
            ->when($product->exists, fn ($q) => $q->where('uuid', '!=', $product->uuid))
            ->where(fn ($q) => $q
                ->where('title', '!=', $product->title)
                ->orWhere('category_uuid', '!=', $product->getAttributes()['category_uuid'] ?? ''))
            ->exists();

        // what the cast of the model makes of it is what ends up stored, and what must be compared
        $normalize = fn (string $value): string => (new \App\Domain\Casts\AddressUrl())->set($product, 'address', $value, []);
        $address = $normalize($address);

        if (!$taken($address)) {
            return $address;
        }

        $suffix = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', (string) ($product->vendorcode ?: $product->external_id)), '-');
        $base = $suffix !== '' ? $normalize($address . '-' . $suffix) : $address;
        $candidate = $base;

        for ($i = 2; $taken($candidate); ++$i) {
            $candidate = $base . '-' . $i;
        }

        return $candidate;
    }
}
