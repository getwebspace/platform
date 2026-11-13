<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\McpTools\Concerns\BuildsAddress;
use App\Domain\McpTools\Concerns\ReadsProductFields;
use App\Domain\Models\CatalogCategory;
use App\Domain\Service\Catalog\ProductService;

class CatalogProductCreateTool extends AbstractMcpTool
{
    use BuildsAddress;
    use ReadsProductFields;

    public const NAME = 'catalog_product_create';
    public const TITLE = 'Create product';
    public const DESCRIPTION = 'Adds a product to the catalog. It is created HIDDEN (not shown on the site) unless visible=true, so it can be checked and completed first; '
        . 'make it visible later with catalog_product_update status=work. Category is required (catalog_category_list). The page address is made of the category address and the title unless "address" is passed. '
        . 'Images are added in the admin panel. Check with the operator that the product does not exist yet (catalog_product_search) and use dry_run to show the result first.';
    public const READ_ONLY = false;
    public const SCOPE = 'catalog/product';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category_uuid' => ['type' => 'string'],
                'visible' => ['type' => 'boolean', 'default' => false, 'description' => 'true - show on the site at once'],
                'address' => ['type' => 'string', 'description' => 'Page address, latin letters, digits and dashes; made of the title by default'],
            ] + $this->productFieldsSchema(),
            'required' => ['category_uuid', 'title'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $data = $this->productData($args);

        if (!isset($data['title'])) {
            throw new \InvalidArgumentException('Field title is required');
        }

        $category = is_string($args['category_uuid'] ?? null) && \Ramsey\Uuid\Uuid::isValid($args['category_uuid'])
            ? CatalogCategory::query()->where('uuid', $args['category_uuid'])->where('status', CatalogStatus::WORK)->first()
            : null;

        if ($category === null) {
            throw new \InvalidArgumentException('Category not found, see catalog_category_list');
        }

        $data['category_uuid'] = (string) $category->uuid;
        $data['address'] = $this->buildAddress((string) $category->address, $data['title'], $args['address'] ?? null);
        $data['status'] = empty($args['visible']) ? CatalogStatus::DELETE : CatalogStatus::WORK;
        $data['export'] ??= 'manual';

        $product = $this->container->get(ProductService::class)->create($data);

        $this->container->get(PubSub::class)->publish('api:catalog:product:create', $product);
        $this->logger->notice('Product created via MCP', ['uuid' => (string) $product->uuid, 'visible' => $data['status'] === CatalogStatus::WORK]);

        return [
            'uuid' => (string) $product->uuid,
            'title' => $product->title,
            'address' => $product->address,
            'category' => $category->title,
            'status' => $product->status,
            'visible' => $product->status === CatalogStatus::WORK,
            'price' => $product->price,
            'stock' => $product->stock,
            'url' => rtrim((string) $this->parameter('common_homepage', ''), '/') . '/catalog/' . ltrim((string) $product->address, '/'),
        ];
    }
}
