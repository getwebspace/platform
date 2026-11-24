<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogCategory;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class CategoryService extends AbstractService
{
    protected static array $eager = ['parent', 'attributes', 'files'];

    protected static array $search_columns = ['title', 'address'];

    /**
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): CatalogCategory
    {
        $default = [
            'attributes' => [],
        ];
        // a field the form did not send is null, which would replace the default of the record
        $data = array_merge($default, array_filter($data, static fn (mixed $value): bool => $value !== null));

        $category = new CatalogCategory();
        $category->fill($data);

        if (!$category->title) {
            throw new MissingTitleValueException();
        }

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && (!isset($data['address']) || blank($data['address']))) {
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
            $data['attributes'] = $attributes->pluck('uuid')->merge($data['attributes'])->unique();
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
        if ($data['status'] !== null && ($statuses = $this->limitByList($data['status'], \App\Domain\Casts\Catalog\Status::LIST))) {
            $criteria['status'] = $statuses;
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
                /** @var null|CatalogCategory $catalogCategory */
                $catalogCategory = $this->buildQuery(CatalogCategory::query(), $criteria, $data)->first();

                return $catalogCategory ?: throw new CategoryNotFoundException();

            default:
                return $this->buildQuery(CatalogCategory::query(), $criteria, $data)->get();
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

            // as when it was made: a category cannot be left without a title
            if (!$entity->title) {
                throw new MissingTitleValueException();
            }

            // the tree has no loops: a category is not under itself, nor under what is under it
            if ($entity->isDirty('parent_uuid') && $entity->parent_uuid) {
                if ($entity->parent_uuid === $entity->uuid || $entity->nested(true)->contains('uuid', $entity->parent_uuid)) {
                    throw new \App\Domain\Service\Catalog\Exception\WrongParentValueException();
                }
            }

            // if address generation is enabled
            if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && (!isset($data['address']) || blank($data['address']))) {
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

    /**
     * The tree of shown categories with the number of products in each and a picture.
     *
     * A node is `['category' => CatalogCategory, 'uuid', 'title', 'address', 'image' => ?File,
     * 'own_count', 'count', 'children' => [nodes]]`. `count` is what the category page lists:
     * the products of the category and, when it allows nested ones, of everything below it.
     * A hidden or removed category takes its whole branch with it, as it does on the site.
     * Two queries for the whole tree, whatever its size.
     *
     * @param null|string $root uuid or address of the category to start under, the top level by default
     */
    public function tree(?string $root = null): array
    {
        /** @var Collection<int, CatalogCategory> $shown */
        $shown = CatalogCategory::query()
            ->with('files')
            ->where('status', \App\Domain\Casts\Catalog\Status::WORK)
            ->where('is_hidden', false)
            ->orderBy('order')
            ->orderBy('title')
            ->get();

        $own = \App\Domain\Models\CatalogProduct::query()
            ->where('status', \App\Domain\Casts\Catalog\Status::WORK)
            ->toBase()
            ->selectRaw('category_uuid, count(*) as total')
            ->groupBy('category_uuid')
            ->pluck('total', 'category_uuid');

        $byParent = [];
        foreach ($shown as $category) {
            $byParent[(string) ($category->getAttributes()['parent_uuid'] ?? '')][] = $category;
        }

        $build = function (CatalogCategory $category) use (&$build, $byParent, $own): array {
            $children = array_map($build, $byParent[$category->uuid] ?? []);
            $count = (int) ($own[$category->uuid] ?? 0);

            if ($category->is_allow_nested) {
                $count += array_sum(array_column($children, 'count'));
            }

            return [
                'category' => $category,
                'uuid' => $category->uuid,
                'title' => $category->title,
                'address' => $category->address,
                'image' => $category->files->first(fn ($file) => str_starts_with((string) $file->type, 'image/')),
                'own_count' => (int) ($own[$category->uuid] ?? 0),
                'count' => $count,
                'children' => $children,
            ];
        };

        $start = '';
        if ($root !== null && $root !== '') {
            $found = $shown->first(fn (CatalogCategory $c) => $c->uuid === $root || $c->address === $root);

            if (!$found) {
                return [];
            }
            $start = $found->uuid;
        }

        return array_map($build, $byParent[$start] ?? []);
    }
}
