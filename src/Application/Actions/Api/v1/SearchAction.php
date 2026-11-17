<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Domain\AbstractException;
use App\Domain\Service\Search\SearchService;
use Psr\Container\ContainerExceptionInterface;

class SearchAction extends ActionApi
{
    protected function action(): \Slim\Psr7\Response
    {
        try {
            $query = mb_trim(str_escape(strip_tags((string) $this->getParam('query', $this->getParam('q', '')))));
            [$types, $unknown] = SearchService::parseTypes($this->getParam('type', $this->getParam('t', '')));
            $limit = max(1, min(100, (int) $this->getParam('limit', $this->parameter('search_limit', 10))));
            $page = max(1, (int) $this->getParam('page', 1));

            if ($unknown) {
                return $this
                    ->respondWithJson(['status' => 400, 'data' => 'Unknown type: ' . implode(', ', $unknown) . '. Available: ' . implode(', ', SearchService::types())])
                    ->withStatus(400);
            }

            if (mb_strlen($query) < SearchService::MIN_QUERY_LENGTH) {
                return $this
                    ->respondWithJson(['status' => 400, 'data' => 'Wrong query string'])
                    ->withStatus(400);
            }

            $found = $this->container->get(SearchService::class)->search($query, $types, $limit, ($page - 1) * $limit);

            return $this->respondWithJson([
                'status' => 200,
                'data' => [
                    'query' => $query,
                    'index_type' => implode(',', $types),
                    'count' => $found['total'],
                    'counts' => $found['counts'],
                    'limit' => $limit,
                    'page' => $page,
                    'result' => array_map(fn (array $item) => array_diff_key($item, ['model' => 1]), $found['items']),
                ],
            ]);
        } catch (AbstractException|ContainerExceptionInterface $exception) {
            return $this
                ->respondWithJson(['status' => 503, 'data' => $exception->getTitle()])
                ->withStatus(503);
        }
    }
}
