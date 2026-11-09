<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

/**
 * Fields of a product as the tools take them, shared by the create and the update tool
 */
trait ReadsProductFields
{
    use SanitizesHtml;

    private const PRODUCT_SCALARS = ['title', 'vendorcode', 'barcode', 'country', 'manufacturer'];

    private const PRODUCT_NUMBERS = ['price', 'priceWholesale', 'discount', 'quantity', 'quantityMin', 'stock', 'tax'];

    /**
     * JSON Schema properties of the fields (no uuid, category, status)
     */
    protected function productFieldsSchema(): array
    {
        $string = ['type' => 'string'];
        $number = ['type' => 'number', 'minimum' => 0];

        return [
            'title' => $string,
            'description' => ['type' => 'string', 'description' => 'Main description, HTML (scripts and event handlers are removed)'],
            'extra' => ['type' => 'string', 'description' => 'Extra description, HTML'],
            'price' => $number + ['description' => 'Retail price'],
            'priceWholesale' => $number,
            'discount' => $number + ['description' => 'Discount amount from the price'],
            'quantity' => $number + ['description' => 'Quantity of one sales unit (admin panel: "Quantity", 1 by default)'],
            'quantityMin' => $number + ['description' => 'Minimum quantity to order (admin panel: "Minimum Quantity")'],
            'stock' => $number + ['description' => 'Stock: how many units are in stock (admin panel: "In stock")'],
            'tax' => $number + ['description' => 'Tax rate, percent'],
            'special' => ['type' => 'boolean', 'description' => 'Special offer'],
            'vendorcode' => $string,
            'barcode' => $string,
            'country' => $string,
            'manufacturer' => $string,
            'meta_title' => $string,
            'meta_description' => $string,
            'meta_keywords' => $string,
        ];
    }

    /**
     * Fields of the model out of the arguments; only what was passed
     *
     * @param array $currentMeta meta of the product being changed: the SEO fields are merged into it
     *
     * @throws \InvalidArgumentException
     */
    protected function productData(array $args, array $currentMeta = []): array
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
                if (!is_numeric($args[$key]) || $args[$key] < 0) {
                    throw new \InvalidArgumentException("Field {$key} must be a number, zero or more");
                }

                $data[$key] = round((float) $args[$key], 2);
            }
        }
        foreach (['description', 'extra'] as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = $this->sanitizeHtml((string) $args[$key]);
            }
        }
        if (array_key_exists('special', $args)) {
            $data['special'] = (bool) $args['special'];
        }

        $meta = array_filter([
            'title' => $args['meta_title'] ?? null,
            'description' => $args['meta_description'] ?? null,
            'keywords' => $args['meta_keywords'] ?? null,
        ], fn ($value) => $value !== null);

        if ($meta) {
            $data['meta'] = array_merge($currentMeta, array_map('trim', $meta));
        }

        return $data;
    }
}
