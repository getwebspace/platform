<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Models\ApiKey;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use Illuminate\Database\Eloquent\Model;

class SeoAuditTool extends AbstractMcpTool
{
    public const NAME = 'seo_audit';
    public const TITLE = 'SEO audit';
    public const DESCRIPTION = 'Checks the SEO fields (title and description) of pages, published publications, visible products and categories: empty, too long (title over 70, description over 160 characters) and repeated on several records. '
        . 'Counts and examples for every problem. Note: an empty SEO title is not always a mistake - the templates may use the title of the record instead. Only the entities the API key may read are checked. '
        . 'Fix with page_update, publication_update, catalog_product_update, catalog_category_update.';
    public const PRIVATE = true;

    private const ENTITIES = [
        'page' => ['read' => 'page', 'label' => 'pages'],
        'publication' => ['read' => 'publication', 'label' => 'publications'],
        'catalog_product' => ['read' => 'catalog/product', 'label' => 'products'],
        'catalog_category' => ['read' => 'catalog/category', 'label' => 'categories'],
    ];

    private const TITLE_MAX = 70;

    private const DESCRIPTION_MAX = 160;

    /**
     * Any valid key, the entities are filtered by its scopes in execute()
     */
    public function isAllowed(?ApiKey $apiKey): bool
    {
        return $apiKey !== null;
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity' => ['type' => 'string', 'enum' => array_keys(self::ENTITIES), 'description' => 'Only this kind of records'],
                'examples' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 50, 'default' => 10, 'description' => 'How many examples per problem'],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $entity = $args['entity'] ?? null;

        if ($entity !== null && !isset(self::ENTITIES[$entity])) {
            throw new \InvalidArgumentException('Field entity must be one of: ' . implode(', ', array_keys(self::ENTITIES)));
        }

        $examples = $this->intArgument($args, 'examples', 10, 0, 50);
        $result = [];

        foreach (self::ENTITIES as $name => $config) {
            if (($entity !== null && $entity !== $name) || !$this->canRead($config['read'])) {
                continue;
            }

            $result[$config['label']] = $this->audit($this->records($name), $examples);
        }

        if (!$result) {
            throw new \DomainException('The API key cannot read pages, publications, products or categories');
        }

        return $result;
    }

    /**
     * @return iterable<Model>
     */
    private function records(string $name): iterable
    {
        return match ($name) {
            'page' => Page::query()->cursor(),
            'publication' => Publication::query()->where('date', '<=', datetime()->addYears(50)->format('Y-m-d H:i:s'))->cursor(),
            'catalog_product' => CatalogProduct::query()->where('status', CatalogStatus::WORK)->cursor(),
            default => CatalogCategory::query()->where('status', CatalogStatus::WORK)->cursor(),
        };
    }

    /**
     * @param iterable<Model> $records
     */
    private function audit(iterable $records, int $examples): array
    {
        $issues = [
            'empty_title' => [], 'empty_description' => [], 'long_title' => [], 'long_description' => [],
            'repeated_title' => [], 'repeated_description' => [],
        ];
        $seen = ['title' => [], 'description' => []];
        $total = 0;

        foreach ($records as $record) {
            ++$total;

            $meta = (array) $record->meta;
            $card = ['uuid' => (string) $record->uuid, 'title' => $record->title, 'address' => $record->address];
            $title = trim((string) ($meta['title'] ?? ''));
            $description = trim((string) ($meta['description'] ?? ''));

            foreach (['title' => $title, 'description' => $description] as $field => $value) {
                if ($value === '') {
                    $issues["empty_{$field}"][] = $card;

                    continue;
                }
                if (mb_strlen($value) > ($field === 'title' ? self::TITLE_MAX : self::DESCRIPTION_MAX)) {
                    $issues["long_{$field}"][] = $card + ['length' => mb_strlen($value)];
                }

                $seen[$field][mb_strtolower($value)][] = $card;
            }
        }

        foreach ($seen as $field => $values) {
            foreach ($values as $value => $cards) {
                if (count($cards) > 1) {
                    foreach ($cards as $card) {
                        $issues["repeated_{$field}"][] = $card + ['text' => str_truncate((string) $value, 80)];
                    }
                }
            }
        }

        return [
            'total' => $total,
            'problems' => array_map(fn (array $list) => ['count' => count($list), 'examples' => array_slice($list, 0, $examples)], $issues),
        ];
    }
}
