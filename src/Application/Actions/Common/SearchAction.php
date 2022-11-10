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
    protected function action(): \Slim\Psr7\Response
    {
        $type = $this->getParam('type', $this->getParam('t', ''));
        $query = trim(str_escape($this->getParam('query', $this->getParam('q', ''))));
        $data = Search::isPossible() ? $this->advanced($query) : $this->primitive($query);

        return $this->respond($this->parameter('search_template', 'search.twig'), [
            'count' => $data['count'],
            'result' => $type ? [$type => ($data['result'][$type] ?? [])] : $data['result'],
        ]);
    }

    private function primitive(string $query)
    {
        if ($query && !$this->getParam('query_strong', $this->getParam('qs'))) {
            $query = '%' . $query . '%';
        }
        $limit = (int) $this->getParam('limit', $this->parameter('search_limit', 10));

        $count = 0;
        $result = [];

        if ($query) {
            $entities = [
                'page' => $this->container->get(PageService::class),
                'publication' => $this->container->get(PublicationService::class),
                'catalog_product' => $this->container->get(CatalogProductService::class),
            ];

            foreach ($entities as $type => $service) {
                /** @var AbstractService $service */
                $qb = $service->createQueryBuilder('e');

                if (!str_start_with($query, '%')) {
                    $qb->andWhere('e.title = :title');
                } else {
                    $qb->andWhere('LOWER(e.title) LIKE LOWER(:title)');
                }

                $qb->setParameter('title', $query, \Doctrine\DBAL\ParameterType::STRING);

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
                                'title', 'description', 'extra', 'content',
                                'address',
                                'vendorcode', 'barcode',
                                'field1', 'field2', 'field3', 'field4', 'field5',
                                'priceFirst', 'price', 'priceWholesale',
                                'volume', 'unit', 'meta', 'external_id',
                            ])
                        );

                        if (method_exists($entity, 'hasFiles')) {
                            $files = [];

                            /** @var \App\Domain\Entities\File $item */
                            foreach ($entity->getFiles()->sortBy('order') as $item) {
                                $files[] = [
                                    'filename' => implode('.', [$item->file->name, $item->file->ext]),
                                    'full' => $item->getPublicPath('full'),
                                    'middle' => $item->getPublicPath('middle'),
                                    'small' => $item->getPublicPath('small'),
                                    'type' => $item->file->type,
                                    'salt' => $item->file->salt,
                                    'hash' => $item->file->hash,
                                    'size' => $item->file->size,
                                    'comment' => $item->comment,
                                    'order' => $item->order,
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
        }

        return ['count' => $count, 'result' => $result];
    }

    private function advanced(string $query)
    {
        if ($query && !$this->getParam('query_strong', $this->getParam('qs'))) {
            $query = implode(' ', array_map(fn ($word) => (mb_strlen($word) > 3 ? $word . '*' : $word), explode(' ', $query)));
        }
        $limit = (int) $this->getParam('limit', $this->parameter('search_limit', 10));

        $count = 0;
        $result = [];

        if ($query) {
            $entities = [
                'page' => $this->container->get(PageService::class),
                'publication' => $this->container->get(PublicationService::class),
                'catalog_product' => $this->container->get(CatalogProductService::class),
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
                                'title', 'description', 'extra', 'content',
                                'address',
                                'vendorcode', 'barcode',
                                'field1', 'field2', 'field3', 'field4', 'field5',
                                'priceFirst', 'price', 'priceWholesale',
                                'volume', 'unit', 'meta', 'external_id',
                            ])
                        );

                        if (method_exists($item, 'hasFiles')) {
                            $files = [];

                            /** @var \App\Domain\Entities\File $file */
                            foreach ($item->getFiles() as $file) {
                                $files[] = [
                                    'filename' => implode('.', [$item->file->name, $item->file->ext]),
                                    'full' => $item->getPublicPath('full'),
                                    'middle' => $item->getPublicPath('middle'),
                                    'small' => $item->getPublicPath('small'),
                                    'type' => $item->file->type,
                                    'salt' => $item->file->salt,
                                    'hash' => $item->file->hash,
                                    'size' => $item->file->size,
                                    'comment' => $item->comment,
                                    'order' => $item->order,
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
        }

        return ['count' => $count, 'result' => $result];
    }
}
