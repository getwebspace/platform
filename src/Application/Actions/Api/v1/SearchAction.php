<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Application\Search;
use App\Domain\AbstractException;
use App\Domain\AbstractService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\PublicationService;
use Psr\Container\ContainerExceptionInterface;

class SearchAction extends ActionApi
{
    protected function action(): \Slim\Psr7\Response
    {
        try {
            $query = trim(str_escape($this->getParam('query', $this->getParam('q', ''))));
            $query_type = $this->getParam('type', $this->getParam('t', false));
            $query_strong = (bool) $this->getParam('query_strong', $this->getParam('qs', false));
            $limit = (int) $this->getParam('limit', $this->parameter('search_limit', 10));

            if ($query && Search::isPossible()) {
                $services = [
                    'page' => $this->container->get(PageService::class),
                    'publication' => $this->container->get(PublicationService::class),
                    'catalog_product' => $this->container->get(CatalogProductService::class),
                ];
                $search = Search::search($query, $query_strong);

                $result = collect();

                foreach ($services as $type => $service) {
                    if (!empty($search[$type]) && (!$query_type || (in_array($query_type, array_keys($services), true) && $type === $query_type))) {
                        $sliced = array_slice($search[$type], 0, $limit);

                        /** @var AbstractService $service */
                        $entities = $service->read([
                            'uuid' => $sliced,
                            'status' => 'work',
                            'limit' => $limit,
                        ]);

                        foreach ($sliced as $uuid) {
                            if (($entity = $entities->firstWhere('uuid', $uuid)) !== null) {
                                $entity = $entity->toArray();
                                $entity['entity'] = $type;

                                if (str_starts_with($type, 'catalog_')) {
                                    $entity['address'] = 'catalog/' . $entity['address'];
                                }

                                $result[] = $entity;
                            }
                        }
                    }
                }

                return $this
                    ->respondWithJson([
                        'status' => 200,
                        'data' => [
                            'query' => $query,
                            'query_strong' => $query_strong,
                            'query_type' => $query_type,
                            'count' => count($result),
                            'limit' => $limit,
                            'result' => $result,
                        ],
                    ]);
            }

            return $this
                ->respondWithJson(['status' => 400, 'data' => 'Wrong query string'])
                ->withStatus(400);
        } catch (ContainerExceptionInterface|AbstractException $exception) {
            return $this
                ->respondWithJson(['status' => 503, 'data' => $exception->getTitle()])
                ->withStatus(503);
        }
    }
}
