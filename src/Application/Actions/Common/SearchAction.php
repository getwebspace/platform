<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\PublicationService;

class SearchAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $query = trim(str_escape(strip_tags($this->getParam('query', $this->getParam('q', '')))));
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

            // fix product address ..
            $result = $result->each(function ($model) {
                if ($model instanceof CatalogProduct) {
                    $model->address = 'catalog/' . $model->address;
                }

                return $model;
            });
        }

        return $this
            ->respond($this->parameter('search_template', 'search.twig'), [
                'query' => $query,
                'index_type' => $index_type,
                'result' => $result,
                'count' => $result->count(),
                'limit' => $limit,
            ])
            ->withAddedHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
