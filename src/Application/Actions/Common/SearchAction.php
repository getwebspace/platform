<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Application\Search;
use App\Domain\AbstractAction;
use App\Domain\AbstractService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\PublicationService;

class SearchAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $query = trim(str_escape($this->getParam('query', $this->getParam('q', ''))));
        $query_type = $this->getParam('type', $this->getParam('t', false));
        $query_strong = !!$this->getParam('query_strong', $this->getParam('qs', false));
        $limit = (int) $this->getParam('limit', $this->parameter('search_limit', 10));
        $result = collect();

        if ($query && Search::isPossible()) {
            $services = [
                'page' => $this->container->get(PageService::class),
                'publication' => $this->container->get(PublicationService::class),
                'catalog_product' => $this->container->get(CatalogProductService::class),
            ];
            $search = Search::search($query, $query_strong);

            foreach ($services as $type => $service) {
                if (!empty($search[$type]) && (!$query_type || (in_array($query_type, array_keys($services)) && $type === $query_type))) {
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
                                $entity['address'] = $this->parameter('catalog_address', 'catalog') . '/' . $entity['address'];
                            }

                            $result[] = $entity;
                        }
                    }
                }
            }
        }

        return $this->respond($this->parameter('search_template', 'search.twig'), [
            'query' => $query,
            'type' => $query_type,
            'strong' => $query_strong,
            'count' => count($result),
            'result' => $result,
        ]);
    }
}
