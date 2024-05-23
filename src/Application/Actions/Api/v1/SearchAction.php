<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Domain\AbstractException;
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
            $index_type = $this->getParam('type', $this->getParam('t', ''));
            $limit = (int) $this->getParam('limit', $this->parameter('search_limit', 10));
            $result = collect();

            if ($query) {
                /** @var \TeamTNT\TNTSearch\TNTSearch $tnt */
                $tnt = $this->container->get(\TeamTNT\TNTSearch\TNTSearch::class);
                $indexes = [
                    'page' => $this->container->get(PageService::class),
                    'publication' => $this->container->get(PublicationService::class),
                    'catalog_product' => $this->container->get(CatalogProductService::class),
                ];

                foreach ($indexes as $type => $service) {
                    if (!$index_type || $index_type === $type) {
                        $tnt->selectIndex("{$type}.index");

                        $found = $tnt->search($query, $limit);

                        if ($found['ids']) {
                            $scores = $found['docScores'];
                            $models = $service
                                ->read([
                                    'uuid' => $found['ids'],
                                    'status' => 'work',
                                    'limit' => $limit,
                                ])
                                ->sortByDesc(function ($model) use ($scores) {
                                    return $scores[$model->uuid] ?? -1;
                                });

                            $result = $result->merge($models);
                        }
                    }
                }

                return $this
                    ->respondWithJson([
                        'status' => 200,
                        'data' => [
                            'query' => $query,
                            'index_type' => $index_type,
                            'count' => count($result),
                            'limit' => $limit,
                            'result' => $result,
                        ],
                    ]);
            }

            return $this
                ->respondWithJson(['status' => 400, 'data' => 'Wrong query string'])
                ->withStatus(400);
        } catch (AbstractException|ContainerExceptionInterface $exception) {
            return $this
                ->respondWithJson(['status' => 503, 'data' => $exception->getTitle()])
                ->withStatus(503);
        }
    }
}
