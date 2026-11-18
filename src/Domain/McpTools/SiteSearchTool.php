<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Service\Search\SearchService;

class SiteSearchTool extends AbstractMcpTool
{
    public const NAME = 'site_search';
    public const TITLE = 'Search on the site';
    public const DESCRIPTION = 'Full text search over products, catalog categories, publications, publication categories and pages with the same index as the search of the site (finds forms of words and typos). Returns what was found with the kind, title, link and a piece of the text. '
        . 'If it finds nothing at all, the index may be empty or old: site_reindex builds it again. Requires an API key, it finds only the kinds the key can read.';
    public const PRIVATE = true;

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
                'kind' => ['type' => 'string', 'enum' => SearchService::types(), 'description' => 'Only this kind of records'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => static::LIMIT_MAX, 'default' => 10],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $query = $this->textArgument($args, 'query');

        if ($query === '') {
            throw new \InvalidArgumentException('Field query is required');
        }
        if (isset($args['kind']) && !in_array($args['kind'], SearchService::types(), true)) {
            throw new \InvalidArgumentException('Field kind must be one of: ' . implode(', ', SearchService::types()));
        }

        $limit = $this->intArgument($args, 'limit', 10, 1, static::LIMIT_MAX);
        $offset = $this->intArgument($args, 'offset', 0, 0, PHP_INT_MAX);

        $types = array_values(array_filter(
            isset($args['kind']) ? [$args['kind']] : SearchService::types(),
            fn (string $type) => $this->canRead((string) SearchService::entity($type))
        ));

        if (!$types) {
            return ['query' => $query, 'count' => 0, 'items' => []];
        }

        $found = $this->container->get(SearchService::class)->search($query, $types, $limit, $offset);
        $homepage = mb_rtrim((string) $this->parameter('common_homepage', ''), '/');

        return [
            'query' => $query,
            'count' => $found['total'],
            'items' => array_map(fn (array $item) => [
                'kind' => $item['type'],
                'uuid' => $item['uuid'],
                'title' => $item['title'],
                'url' => $homepage . $item['url'],
                'excerpt' => $item['excerpt'],
            ], $found['items']),
        ] + ($found['missing'] ? ['no_index_for' => $found['missing']] : []);
    }
}
