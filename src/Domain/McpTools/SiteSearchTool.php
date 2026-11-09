<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\PublicationService;
use Illuminate\Support\Collection;

class SiteSearchTool extends AbstractMcpTool
{
    public const NAME = 'site_search';
    public const TITLE = 'Search on the site';
    public const DESCRIPTION = 'Full text search over products, pages and publications with the same index as the search of the site (finds forms of words and typos). Returns what was found with the kind, title and link. '
        . 'If it finds nothing at all, the index may be empty or old: site_reindex builds it again. Requires an API key that can read products, pages and publications.';
    public const PRIVATE = true;

    private const KINDS = [
        'catalog_product' => ['entity' => 'catalog/product', 'path' => '/catalog/'],
        'publication' => ['entity' => 'publication', 'path' => '/'],
        'page' => ['entity' => 'page', 'path' => '/'],
    ];

    /**
     * Any valid key, the kinds are filtered by its scopes in execute()
     */
    public function isAllowed(?\App\Domain\Models\ApiKey $apiKey): bool
    {
        return $apiKey !== null;
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'kind' => ['type' => 'string', 'enum' => array_keys(self::KINDS), 'description' => 'Only this kind of records'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => static::LIMIT_MAX, 'default' => 10, 'description' => 'Per kind'],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $query = trim((string) ($args['query'] ?? ''));

        if ($query === '') {
            throw new \InvalidArgumentException('Field query is required');
        }
        if (isset($args['kind']) && !isset(self::KINDS[$args['kind']])) {
            throw new \InvalidArgumentException('Field kind must be one of: ' . implode(', ', array_keys(self::KINDS)));
        }

        $limit = $this->intArgument($args, 'limit', 10, 1, static::LIMIT_MAX);

        /** @var \TeamTNT\TNTSearch\TNTSearch $tnt */
        $tnt = $this->container->get(\TeamTNT\TNTSearch\TNTSearch::class);
        $services = [
            'catalog_product' => ProductService::class,
            'publication' => PublicationService::class,
            'page' => PageService::class,
        ];

        $homepage = rtrim((string) $this->parameter('common_homepage', ''), '/');
        $items = [];
        $skipped = [];

        foreach (self::KINDS as $kind => $config) {
            if ((isset($args['kind']) && $args['kind'] !== $kind) || !$this->canRead($config['entity'])) {
                continue;
            }

            try {
                $tnt->selectIndex("{$kind}.index");
                $found = $tnt->search($query, $limit);
            } catch (\Throwable $e) {
                // no index of this kind yet
                $skipped[] = $kind;

                continue;
            }

            if (!$found['ids']) {
                continue;
            }

            /** @var Collection $records */
            $records = $this->container->get($services[$kind])->read(['uuid' => $found['ids']] + ($kind === 'catalog_product' ? ['status' => CatalogStatus::WORK] : []));

            foreach ($records->sortByDesc(fn ($record) => $found['docScores'][(string) $record->uuid] ?? -1) as $record) {
                if ($kind === 'publication' && datetime($record->date)->isAfter(datetime())) {
                    continue; // drafts and scheduled ones are not on the site yet
                }

                $items[] = [
                    'kind' => $kind,
                    'uuid' => (string) $record->uuid,
                    'title' => $record->title,
                    'url' => $homepage . $config['path'] . ltrim((string) $record->address, '/'),
                ];
            }
        }

        return ['query' => $query, 'count' => count($items), 'items' => $items] + ($skipped ? ['no_index_for' => $skipped] : []);
    }
}
