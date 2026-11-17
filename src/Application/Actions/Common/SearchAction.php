<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Service\Search\SearchService;

class SearchAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $query = mb_trim(str_escape(strip_tags((string) $this->getParam('query', $this->getParam('q', '')))));
        [$types] = SearchService::parseTypes($this->getParam('type', $this->getParam('t', '')));
        $limit = max(1, min(100, (int) $this->getParam('limit', $this->parameter('search_limit', 10))));
        $page = max(1, (int) $this->getParam('page', 1));

        $found = $this->container->get(SearchService::class)->search($query, $types, $limit, ($page - 1) * $limit);
        $items = collect($found['items']);

        // links of the catalog start with its prefix, the themes build them from the address
        $result = $items->map(function (array $item) {
            if (str_starts_with($item['type'], 'catalog_')) {
                $item['model']->address = 'catalog/' . $item['model']->address;
            }

            return $item['model'];
        });

        return $this
            ->respond($this->parameter('search_template', 'search.twig'), [
                'query' => $query,
                'index_type' => implode(',', $types),
                'types' => $types,
                'available_types' => SearchService::types(),
                'result' => $result,
                'items' => $items,
                'count' => $found['total'],
                'counts' => $found['counts'],
                'limit' => $limit,
                'page' => $page,
                'pages' => (int) ceil($found['total'] / $limit),
            ])
            ->withAddedHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
