<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Application\Search;
use App\Domain\AbstractAction;
use App\Domain\AbstractService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Types\Catalog\CategoryStatusType;
use App\Domain\Types\Catalog\ProductStatusType;

class SearchAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        $type = $this->request->getParam('type', $this->request->getParam('t', ''));
        $query = trim(str_escape($this->request->getParam('query', $this->request->getParam('q', ''))));
        $data = Search::isPossible() ? $this->advanced($query) : $this->primitive($query);

        return $this->respond($this->parameter('search_template', 'search.twig'), [
            'count' => $data['count'],
            'result' => $type ? [$type => $data['result'][$type] ?? []] : $data['result'],
        ]);
    }

    private function primitive(string $query)
    {
        if ($query && !$this->request->getParam('query_strong', $this->request->getParam('qs'))) {
            $query = '%' . $query . '%';
        }
        $limit = (int) $this->request->getParam('limit', $this->parameter('search_limit', 10));

        $count = 0;
        $result = [];

        if ($query) {
            \RunTracy\Helpers\Profiler\Profiler::start('search', ['index' => false]);

            $entities = [
                'page' => PageService::getWithContainer($this->container),
                'publication' => PublicationService::getWithContainer($this->container),
                'catalog_product' => CatalogProductService::getWithContainer($this->container),
            ];

            foreach ($entities as $type => $service) {
                /** @var AbstractService $service */
                $qb = $service->createQueryBuilder('e');

                if (!str_start_with($query, '%')) {
                    $qb->andWhere('e.title = :title');
                } else {
                    $qb->andWhere('LOWER(e.title) LIKE LOWER(:title)');
                }

                $qb->setParameter('title', $query, \Doctrine\DBAL\Types\Type::STRING);

                switch (true) {
                    case $type === 'catalog_category':
                        $qb->andWhere('e.status = :status')->setParameter('status', CategoryStatusType::STATUS_WORK);

                        break;

                    case $type === 'catalog_product':
                        $qb->andWhere('e.status = :status')->setParameter('status', ProductStatusType::STATUS_WORK);

                        break;
                }

                $qb->setMaxResults($limit);

                if (($buf = $qb->getQuery()->getResult()) !== []) {
                    foreach ($buf as $index => $entity) {
                        $result[$type][$index] = array_intersect_key(
                            $entity->toArray(),
                            array_flip([
                                'uuid', 'category',
                                'title', 'description', 'content',
                                'address',
                                'priceFirst', 'price', 'priceWholesale',
                                'volume', 'unit', 'meta', 'external_id',
                            ])
                        );

                        if (method_exists($entity, 'hasFiles')) {
                            $files = [];

                            /** @var \App\Domain\Entities\File $file */
                            foreach ($entity->getFiles() as $file) {
                                $files[] = [
                                    'full' => $file->getPublicPath('full'),
                                    'middle' => $file->getPublicPath('middle'),
                                    'small' => $file->getPublicPath('small'),
                                ];
                            }

                            $result[$type][$index]['files'] = $files;
                        }

                        if (str_start_with($type, 'catalog_')) {
                            $result[$type][$index]['address'] = $this->parameter('catalog_address', 'catalog') . '/' . $result[$type][$index]['address'];
                        }
                    }

                    $count += count($result[$type]);
                }
            }

            \RunTracy\Helpers\Profiler\Profiler::finish('search', ['index' => false]);
        }

        return ['count' => $count, 'result' => $result];
    }

    private function advanced(string $query)
    {
        if ($query && !$this->request->getParam('query_strong', $this->request->getParam('qs'))) {
            $query = implode(' ', array_map(fn ($word) => (mb_strlen($word) > 3 ? $word . '*' : $word), explode(' ', $query)));
        }
        $limit = (int) $this->request->getParam('limit', $this->parameter('search_limit', 10));

        $count = 0;
        $result = [];

        if ($query) {
            \RunTracy\Helpers\Profiler\Profiler::start('search', ['index' => true]);

            $entities = [
                'page' => PageService::getWithContainer($this->container),
                'publication' => PublicationService::getWithContainer($this->container),
                'catalog_product' => CatalogProductService::getWithContainer($this->container),
            ];
            $search_by_index = \App\Application\Search::search($query);

            foreach ($entities as $type => $service) {
                if (!empty($search_by_index[$type])) {
                    /** @var AbstractService $service */
                    foreach ($service->read(['uuid' => $search_by_index[$type], 'status' => 'work', 'limit' => $limit]) as $index => $item) {
                        $result[$type][$index] = array_intersect_key(
                            $item->toArray(),
                            array_flip([
                                'uuid', 'category',
                                'title', 'description', 'content',
                                'address',
                                'priceFirst', 'price', 'priceWholesale',
                                'volume', 'unit', 'meta', 'external_id',
                            ])
                        );

                        if (method_exists($item, 'hasFiles')) {
                            $files = [];

                            /** @var \App\Domain\Entities\File $file */
                            foreach ($item->getFiles() as $file) {
                                $files[] = [
                                    'full' => $file->getPublicPath('full'),
                                    'middle' => $file->getPublicPath('middle'),
                                    'small' => $file->getPublicPath('small'),
                                ];
                            }

                            $result[$type][$index]['files'] = $files;
                        }

                        if (str_start_with($type, 'catalog_')) {
                            $result[$type][$index]['address'] = $this->parameter('catalog_address', 'catalog') . '/' . $result[$type][$index]['address'];
                        }
                    }

                    $count += count($result[$type]);
                }
            }

            \RunTracy\Helpers\Profiler\Profiler::finish('search', ['index' => true]);
        }

        return ['count' => $count, 'result' => $result];
    }
}
