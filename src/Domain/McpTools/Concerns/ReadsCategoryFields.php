<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use App\Application\ThemeFiles;
use App\Domain\Models\CatalogAttribute;
use App\Domain\Models\CatalogCategory;
use App\Domain\References\Catalog as CatalogReference;
use Illuminate\Database\Connection;

/**
 * Fields of a category as the tools take them, shared by the create and the update tool: all the fields of the model
 * and the attributes (characteristics, filters) of the category
 */
trait ReadsCategoryFields
{
    use SanitizesHtml;

    private const SPECIFICS_MAX = 5000;

    /**
     * JSON Schema properties of the fields (no uuid, parent, status, address)
     */
    protected function categoryFieldsSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'title' => $string,
            'description' => ['type' => 'string', 'description' => 'HTML (scripts and event handlers are removed)'],
            'pagination' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'description' => 'Products on a page of the category'],
            'is_allow_nested' => ['type' => 'boolean', 'description' => 'The category may have nested categories'],
            'sort' => [
                'type' => 'object',
                'description' => 'How the products of the category are sorted; only the passed keys change',
                'properties' => [
                    'by' => ['type' => 'string', 'enum' => CatalogReference::ORDER_BY],
                    'direction' => ['type' => 'string', 'enum' => CatalogReference::ORDER_DIRECTION],
                ],
            ],
            'template' => [
                'type' => 'object',
                'description' => 'Templates of the theme for the category page and for the product page of this category (a file of the active theme, see theme_file_list; "" - the default); only the passed keys change',
                'properties' => ['category' => $string, 'product' => $string],
            ],
            'order' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Sort order of the category in the lists'],
            'specifics' => ['type' => 'object', 'description' => 'Own data of the category for the templates {"key": value}; only the passed keys change, null removes a key'],
            'external_id' => $string + ['description' => 'Id in an outside system (accounting, import)'],
            'export' => $string + ['description' => 'Where the category came from: manual, import...'],
            'system' => $string + ['description' => 'Service field of the site'],
            'meta_title' => $string,
            'meta_description' => $string,
            'meta_keywords' => $string,
            'attributes_add' => ['type' => 'array', 'items' => $string, 'description' => 'Attributes (address or uuid, see catalog_attribute_list) to add to the category: its characteristics; the ones with is_filter are the filters of the category'],
            'attributes_remove' => ['type' => 'array', 'items' => $string, 'description' => 'Attributes to take away from the category'],
        ];
    }

    /**
     * Fields of the model out of the arguments; only what was passed
     *
     * @param null|CatalogCategory $category the category being changed: json fields are merged into what it has
     *
     * @throws \InvalidArgumentException
     */
    protected function categoryData(array $args, ?CatalogCategory $category = null): array
    {
        $data = [];

        if (array_key_exists('title', $args)) {
            if (blank(trim((string) $args['title']))) {
                throw new \InvalidArgumentException('Field title cannot be empty');
            }

            $data['title'] = trim((string) $args['title']);
        }
        if (array_key_exists('description', $args)) {
            $data['description'] = $this->sanitizeHtml((string) $args['description']);
        }
        if (array_key_exists('pagination', $args)) {
            $data['pagination'] = $this->intArgument($args, 'pagination', 10, 1, 500);
        }
        if (array_key_exists('order', $args)) {
            $data['order'] = $this->intArgument($args, 'order', 1, 0, 1_000_000);
        }
        if (array_key_exists('is_allow_nested', $args)) {
            $data['is_allow_nested'] = (bool) $args['is_allow_nested'];
        }
        foreach (['external_id' => 255, 'export' => 50, 'system' => 255] as $key => $max) {
            if (array_key_exists($key, $args)) {
                $value = trim((string) $args[$key]);

                if (mb_strlen($value) > $max) {
                    throw new \InvalidArgumentException("Field {$key} is up to {$max} characters");
                }

                $data[$key] = $value;
            }
        }
        if (array_key_exists('sort', $args)) {
            $data['sort'] = array_merge((array) $category?->sort, $this->sort($args['sort']));
        }
        if (array_key_exists('template', $args)) {
            $data['template'] = array_merge((array) $category?->template, $this->templates($args['template']));
        }
        if (array_key_exists('specifics', $args)) {
            $data['specifics'] = $this->specifics($args['specifics'], (array) $category?->specifics);
        }

        $meta = array_filter([
            'title' => $args['meta_title'] ?? null,
            'description' => $args['meta_description'] ?? null,
            'keywords' => $args['meta_keywords'] ?? null,
        ], fn ($value) => $value !== null);

        if ($meta) {
            $data['meta'] = array_merge((array) $category?->meta, array_map('trim', $meta));
        }
        if (array_key_exists('attributes_add', $args) || array_key_exists('attributes_remove', $args)) {
            $data['attributes'] = $this->categoryAttributes($args, $category);
        }

        return $data;
    }

    /**
     * @return array{by?: string, direction?: string}
     */
    private function sort(mixed $value): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException('Field sort must be an object: {"by": "price", "direction": "ASC"}');
        }
        if ($unknown = array_diff(array_keys($value), ['by', 'direction'])) {
            throw new \InvalidArgumentException('Unknown keys of sort: ' . implode(', ', $unknown));
        }

        $result = [];

        if (array_key_exists('by', $value)) {
            if (!in_array($value['by'], CatalogReference::ORDER_BY, true)) {
                throw new \InvalidArgumentException('sort.by must be one of: ' . implode(', ', CatalogReference::ORDER_BY));
            }

            $result['by'] = $value['by'];
        }
        if (array_key_exists('direction', $value)) {
            $direction = mb_strtoupper((string) $value['direction']);

            if (!in_array($direction, CatalogReference::ORDER_DIRECTION, true)) {
                throw new \InvalidArgumentException('sort.direction must be one of: ' . implode(', ', CatalogReference::ORDER_DIRECTION));
            }

            $result['direction'] = $direction;
        }

        return $result;
    }

    /**
     * @return array{category?: string, product?: string}
     */
    private function templates(mixed $value): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException('Field template must be an object: {"category": "catalog.category.twig", "product": "catalog.product.twig"}');
        }
        if ($unknown = array_diff(array_keys($value), ['category', 'product'])) {
            throw new \InvalidArgumentException('Unknown keys of template: ' . implode(', ', $unknown));
        }

        $theme = (new ThemeFiles($this->container))->roots()['theme'] ?? null;
        $result = [];

        foreach ($value as $key => $file) {
            $file = trim((string) $file);

            // a template of the theme or nothing (the default one); a name that is not a file of the theme would break the page
            if ($file !== '' && (preg_match('/^[A-Za-z0-9._-]+\.twig$/', $file) !== 1 || $theme === null || !is_file($theme . '/' . $file))) {
                throw new \InvalidArgumentException("template.{$key}: there is no file \"{$file}\" in the active theme (theme_file_list), or \"\" for the default one");
            }

            $result[$key] = $file;
        }

        return $result;
    }

    private function specifics(mixed $value, array $current): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException('Field specifics must be an object: {"key": value}');
        }

        foreach ($value as $key => $item) {
            if ($item === null) {
                unset($current[$key]);
            } else {
                $current[$key] = $item;
            }
        }

        if (strlen((string) json_encode($current, JSON_UNESCAPED_UNICODE)) > self::SPECIFICS_MAX) {
            throw new \InvalidArgumentException('specifics is up to ' . self::SPECIFICS_MAX . ' characters');
        }

        return $current;
    }

    /**
     * The whole list of attribute uuids of the category, as the service wants it: what it has now + added - taken away
     *
     * @return array<int, string>
     */
    private function categoryAttributes(array $args, ?CatalogCategory $category): array
    {
        $known = CatalogAttribute::all();
        $resolve = function (mixed $list, string $field) use ($known): array {
            if (!is_array($list) || !array_is_list($list)) {
                throw new \InvalidArgumentException("Field {$field} must be a list of attribute addresses or uuids");
            }

            return array_map(function (mixed $key) use ($known): string {
                $attribute = $known->first(fn (CatalogAttribute $item) => (string) $item->uuid === (string) $key || $item->address === (string) $key);

                if ($attribute === null) {
                    throw new \InvalidArgumentException("Unknown attribute \"{$key}\", see catalog_attribute_list (the address or uuid of an attribute)");
                }

                return (string) $attribute->uuid;
            }, $list);
        };

        $add = $resolve($args['attributes_add'] ?? [], 'attributes_add');
        $remove = $resolve($args['attributes_remove'] ?? [], 'attributes_remove');

        if ($both = array_intersect($add, $remove)) {
            throw new \InvalidArgumentException('The same attribute is added and taken away');
        }

        $current = $category ? $this->categoryDb()->table('catalog_attribute_category')->where('category_uuid', (string) $category->uuid)->pluck('attribute_uuid')->map(fn ($uuid) => (string) $uuid)->all() : [];

        return array_values(array_diff(array_unique([...$current, ...$add]), $remove));
    }

    /**
     * The category must not become a nested one of its own descendants
     *
     * @throws \InvalidArgumentException
     */
    protected function checkParent(?string $parentUuid, ?CatalogCategory $category): ?CatalogCategory
    {
        if (blank($parentUuid)) {
            return null;
        }

        $parent = \Ramsey\Uuid\Uuid::isValid((string) $parentUuid)
            ? CatalogCategory::query()->where('uuid', $parentUuid)->first()
            : null;

        if ($parent === null) {
            throw new \InvalidArgumentException('Parent category not found, see catalog_category_list');
        }

        // walk up from the new parent: our own uuid on the way is a loop
        for ($step = $parent, $depth = 0; $category !== null && $step !== null && $depth < 100; ++$depth) {
            if ((string) $step->uuid === (string) $category->uuid) {
                throw new \InvalidArgumentException('A category cannot be moved into itself or into its own nested category');
            }

            $step = $step->parent_uuid ? CatalogCategory::query()->where('uuid', $step->parent_uuid)->first() : null;
        }

        return $parent;
    }

    private function categoryDb(): Connection
    {
        return $this->container->get(Connection::class);
    }
}
