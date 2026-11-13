<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use App\Domain\Casts\Catalog\Attribute\Type as AttributeType;
use App\Domain\Casts\Catalog\Product\Type as ProductType;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Models\CatalogAttribute;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Reference;
use App\Domain\Service\Reference\ReferenceService;
use Illuminate\Database\Connection;

/**
 * Fields of a product as the tools take them, shared by the create and the update tool: all the fields of the model,
 * the attribute values and the related products
 */
trait ReadsProductFields
{
    use SanitizesHtml;

    private const PRODUCT_SCALARS = ['title', 'vendorcode', 'barcode', 'country', 'manufacturer'];

    private const PRODUCT_NUMBERS = ['price', 'priceFirst', 'priceWholesale', 'priceWholesaleFrom', 'discount', 'quantity', 'quantityMin', 'stock', 'tax'];

    private const MAX_TAGS = 50;

    private const MAX_LINKS = 100;

    /**
     * JSON Schema properties of the fields (no uuid, category, status)
     */
    protected function productFieldsSchema(): array
    {
        $string = ['type' => 'string'];
        $number = ['type' => 'number', 'minimum' => 0];

        return [
            'title' => $string,
            'type' => ['type' => 'string', 'enum' => ProductType::LIST, 'description' => 'product or service'],
            'description' => ['type' => 'string', 'description' => 'Main description, HTML (scripts and event handlers are removed)'],
            'extra' => ['type' => 'string', 'description' => 'Extra description, HTML'],
            'price' => $number + ['description' => 'Retail price'],
            'priceFirst' => $number + ['description' => 'The first (purchase) price, it is not shown on the site'],
            'priceWholesale' => $number,
            'priceWholesaleFrom' => $number + ['description' => 'Quantity from which the wholesale price works'],
            'discount' => $number + ['description' => 'Discount amount from the price'],
            'tax' => $number + ['description' => 'Tax rate, percent'],
            'tax_included' => ['type' => 'boolean', 'description' => 'The tax is already in the price'],
            'quantity' => $number + ['description' => 'Quantity of one sales unit (admin panel: "Quantity", 1 by default)'],
            'quantityMin' => $number + ['description' => 'Minimum quantity to order (admin panel: "Minimum Quantity")'],
            'stock' => $number + ['description' => 'Stock: how many units are in stock (admin panel: "In stock")'],
            'special' => ['type' => 'boolean', 'description' => 'Special offer'],
            'vendorcode' => $string,
            'barcode' => $string,
            'country' => $string,
            'manufacturer' => $string,
            'tags' => ['type' => 'array', 'items' => $string, 'description' => 'All the tags of the product (replaces the current ones); [] clears them'],
            'order' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Sort order in the lists'],
            'date' => $string + ['description' => 'Date of the product, YYYY-MM-DD or YYYY-MM-DD HH:MM (used for sorting by date)'],
            'external_id' => $string + ['description' => 'Id in an outside system (accounting, import)'],
            'export' => $string + ['description' => 'Where the product came from: manual, import, form...'],
            'dimension' => [
                'type' => 'object',
                'description' => 'Size and weight; only the passed keys change',
                'properties' => [
                    'length' => $number, 'width' => $number, 'height' => $number, 'weight' => $number,
                    'length_class' => $string + ['description' => 'Unit of the size, see reference_list type=length_class (ex: cm)'],
                    'weight_class' => $string + ['description' => 'Unit of the weight, see reference_list type=weight_class (ex: kg)'],
                ],
            ],
            'attributes' => [
                'type' => 'object',
                'description' => 'Values of attributes (characteristics, filters) by the attribute address or uuid, see catalog_attribute_list: {"color": "red", "weight": 2.5, "waterproof": true}. '
                    . 'Only the passed attributes change, "" or null removes a value',
            ],
            'relations' => [
                'type' => 'object',
                'description' => 'Related products: {"<product uuid>": <count>}. Only the passed ones change, 0 removes a relation',
            ],
            'meta_title' => $string,
            'meta_description' => $string,
            'meta_keywords' => $string,
        ];
    }

    /**
     * Fields of the model out of the arguments; only what was passed
     *
     * @param null|CatalogProduct $product the product being changed: SEO fields, dimension and links are merged into what it has
     *
     * @throws \InvalidArgumentException
     */
    protected function productData(array $args, ?CatalogProduct $product = null): array
    {
        $data = [];

        foreach (self::PRODUCT_SCALARS as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = trim((string) $args[$key]);
            }
        }
        if (array_key_exists('title', $data) && $data['title'] === '') {
            throw new \InvalidArgumentException('Field title cannot be empty');
        }
        foreach (self::PRODUCT_NUMBERS as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = $this->nonNegative($args, $key);
            }
        }
        foreach (['description', 'extra'] as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = $this->sanitizeHtml((string) $args[$key]);
            }
        }
        foreach (['special', 'tax_included'] as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = (bool) $args[$key];
            }
        }
        if (array_key_exists('type', $args)) {
            if (!in_array($args['type'], ProductType::LIST, true)) {
                throw new \InvalidArgumentException('Field type must be one of: ' . implode(', ', ProductType::LIST));
            }

            $data['type'] = $args['type'];
        }
        if (array_key_exists('order', $args)) {
            $data['order'] = $this->intArgument($args, 'order', 1, 0, 1_000_000);
        }
        foreach (['external_id' => 255, 'export' => 50] as $key => $max) {
            if (array_key_exists($key, $args)) {
                $data[$key] = $this->shortText($args, $key, $max);
            }
        }
        if (array_key_exists('date', $args)) {
            $data['date'] = $this->dateTime($args['date'], 'date');
        }
        if (array_key_exists('tags', $args)) {
            $data['tags'] = $this->tags($args['tags']);
        }
        if (array_key_exists('dimension', $args)) {
            $data['dimension'] = array_merge((array) $product?->dimension, $this->dimension($args['dimension']));
        }

        $meta = array_filter([
            'title' => $args['meta_title'] ?? null,
            'description' => $args['meta_description'] ?? null,
            'keywords' => $args['meta_keywords'] ?? null,
        ], fn ($value) => $value !== null);

        if ($meta) {
            $data['meta'] = array_merge((array) $product?->meta, array_map('trim', $meta));
        }

        return $data + $this->productLinks($args, $product);
    }

    /**
     * Attribute values and related products as the service wants them: the WHOLE lists (it replaces what a product has),
     * built from what the product has now and what was passed
     *
     * @return array{attributes?: array<string, string>, relations?: array<string, float>}
     */
    private function productLinks(array $args, ?CatalogProduct $product): array
    {
        $links = [];

        if (array_key_exists('attributes', $args)) {
            $links['attributes'] = $this->attributeValues($args['attributes'], $product);
        }
        if (array_key_exists('relations', $args)) {
            $links['relations'] = $this->relatedProducts($args['relations'], $product);
        }

        return $links;
    }

    /**
     * @return array<string, string> attribute uuid => value
     */
    private function attributeValues(mixed $passed, ?CatalogProduct $product): array
    {
        if (!is_array($passed) || array_is_list($passed) && $passed !== []) {
            throw new \InvalidArgumentException('Field attributes must be an object: {"attribute address or uuid": value}');
        }

        $known = CatalogAttribute::all();
        $values = $product ? $this->db()->table('catalog_attribute_product')->where('product_uuid', (string) $product->uuid)->pluck('value', 'attribute_uuid')->all() : [];

        foreach ($passed as $key => $value) {
            /** @var null|CatalogAttribute $attribute */
            $attribute = $known->first(fn (CatalogAttribute $item) => (string) $item->uuid === (string) $key || $item->address === (string) $key);

            if ($attribute === null) {
                throw new \InvalidArgumentException("Unknown attribute \"{$key}\", see catalog_attribute_list (the address or uuid of an attribute)");
            }

            if ($value === null || (is_string($value) && trim($value) === '')) {
                unset($values[(string) $attribute->uuid]);

                continue;
            }

            $values[(string) $attribute->uuid] = $this->attributeValue($attribute, $value);
        }

        return array_map('strval', $values);
    }

    /**
     * How a value is kept depends on the type of the attribute: boolean is "yes" / "no", numbers are numbers
     */
    private function attributeValue(CatalogAttribute $attribute, mixed $value): string
    {
        $fail = fn (string $need) => new \InvalidArgumentException("Attribute \"{$attribute->address}\" ({$attribute->type}) needs {$need}");

        if (is_array($value) || is_object($value)) {
            throw $fail('a single value');
        }

        switch ($attribute->type) {
            case AttributeType::BOOLEAN:
                $bool = is_bool($value) ? $value : match (mb_strtolower(trim((string) $value))) {
                    'yes', 'true', '1' => true,
                    'no', 'false', '0' => false,
                    default => throw $fail('true or false'),
                };

                return $bool ? 'yes' : 'no';

            case AttributeType::INTEGER:
                if (!is_numeric($value) || (int) $value != $value) {
                    throw $fail('an integer');
                }

                return (string) (int) $value;

            case AttributeType::FLOAT:
                if (!is_numeric($value)) {
                    throw $fail('a number');
                }

                return (string) (float) $value;

            default:
                $text = trim((string) $value);

                if (mb_strlen($text) > 1000) {
                    throw $fail('a text up to 1000 characters');
                }

                return $text;
        }
    }

    /**
     * @return array<string, float> product uuid => count
     */
    private function relatedProducts(mixed $passed, ?CatalogProduct $product): array
    {
        if (!is_array($passed) || array_is_list($passed) && $passed !== []) {
            throw new \InvalidArgumentException('Field relations must be an object: {"product uuid": count}');
        }

        // all of them, also the hidden products: they are not shown on the site, but they are related and stay so
        $relations = $product ? array_map('floatval', $this->db()->table('catalog_product_related')->where('product_uuid', (string) $product->uuid)->pluck('count', 'related_uuid')->all()) : [];

        foreach ($passed as $uuid => $count) {
            if (!is_string($uuid) || !\Ramsey\Uuid\Uuid::isValid($uuid) || ($product && $uuid === (string) $product->uuid)) {
                throw new \InvalidArgumentException('A related product is a uuid of ANOTHER product, see catalog_product_search');
            }
            if (!is_numeric($count) || $count < 0) {
                throw new \InvalidArgumentException("The count of the related product {$uuid} must be a number, zero or more (0 removes the relation)");
            }
            if ((float) $count === 0.0) {
                unset($relations[$uuid]);

                continue;
            }
            if (CatalogProduct::query()->where('uuid', $uuid)->doesntExist()) {
                throw new \InvalidArgumentException("No product {$uuid} to relate");
            }

            $relations[$uuid] = round((float) $count, 2);
        }

        if (count($relations) > self::MAX_LINKS) {
            throw new \InvalidArgumentException('At most ' . self::MAX_LINKS . ' related products');
        }

        return $relations;
    }

    /**
     * @return array<int, string>
     */
    private function tags(mixed $value): array
    {
        $tags = is_string($value) ? explode(',', $value) : $value;

        if (!is_array($tags)) {
            throw new \InvalidArgumentException('Field tags must be a list of words');
        }

        $result = [];

        foreach ($tags as $tag) {
            if (!is_scalar($tag)) {
                throw new \InvalidArgumentException('Field tags must be a list of words');
            }
            if (($tag = trim((string) $tag)) !== '') {
                if (mb_strlen($tag) > 100) {
                    throw new \InvalidArgumentException('A tag is up to 100 characters');
                }

                $result[mb_strtolower($tag)] = $tag;
            }
        }

        if (count($result) > self::MAX_TAGS) {
            throw new \InvalidArgumentException('At most ' . self::MAX_TAGS . ' tags');
        }

        return array_values($result);
    }

    /**
     * @return array<string, float|string>
     */
    private function dimension(mixed $value): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException('Field dimension must be an object: {"length": 10, "width": 5, "height": 2, "weight": 0.3, "length_class": "cm", "weight_class": "kg"}');
        }
        if ($unknown = array_diff(array_keys($value), ['length', 'width', 'height', 'weight', 'length_class', 'weight_class'])) {
            throw new \InvalidArgumentException('Unknown keys of dimension: ' . implode(', ', $unknown));
        }

        $result = [];

        foreach (['length', 'width', 'height', 'weight'] as $key) {
            if (array_key_exists($key, $value)) {
                $result[$key] = $this->nonNegative($value, $key, 'dimension.');
            }
        }
        foreach (['length_class' => ReferenceType::LENGTH_CLASS, 'weight_class' => ReferenceType::WEIGHT_CLASS] as $key => $type) {
            if (array_key_exists($key, $value)) {
                $result[$key] = $this->unit(trim((string) $value[$key]), $type, $key);
            }
        }

        return $result;
    }

    /**
     * A unit has to be one of the reference book, when the site has one ("" clears it)
     */
    private function unit(string $unit, string $type, string $field): string
    {
        if ($unit === '') {
            return '';
        }

        $units = $this->container->get(ReferenceService::class)->read(['type' => $type, 'status' => true])
            ->map(fn (Reference $reference) => (string) ($reference->value['unit'] ?? ''))
            ->filter()
            ->values();

        if ($units->isEmpty()) {
            return mb_substr($unit, 0, 20);
        }

        $found = $units->first(fn (string $item) => mb_strtolower($item) === mb_strtolower($unit));

        if ($found === null) {
            throw new \InvalidArgumentException("Unknown unit \"{$unit}\" of {$field}, allowed: " . $units->implode(', '));
        }

        return $found;
    }

    private function nonNegative(array $args, string $key, string $prefix = ''): float
    {
        if (!is_numeric($args[$key]) || $args[$key] < 0) {
            throw new \InvalidArgumentException("Field {$prefix}{$key} must be a number, zero or more");
        }

        return round((float) $args[$key], 2);
    }

    private function shortText(array $args, string $key, int $max): string
    {
        $value = trim((string) $args[$key]);

        if (mb_strlen($value) > $max) {
            throw new \InvalidArgumentException("Field {$key} is up to {$max} characters");
        }

        return $value;
    }

    private function dateTime(mixed $value, string $field): string
    {
        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) !== 1 || strtotime($value) === false) {
            throw new \InvalidArgumentException("Field {$field} must be YYYY-MM-DD or YYYY-MM-DD HH:MM");
        }

        return datetime($value)->format('Y-m-d H:i:s');
    }

    private function db(): Connection
    {
        return $this->container->get(Connection::class);
    }
}
