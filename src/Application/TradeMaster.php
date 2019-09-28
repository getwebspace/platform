<?php

namespace App\Application;

use AEngine\Entity\Collection;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class TradeMaster
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Collection
     */
    private $params;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $categoryRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $productRepository;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get('monolog');
        $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);

        $this->categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);
        $this->productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);

        // получение параметров интеграции

        /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $parametersRepository */
        $parametersRepository = $this->entityManager->getRepository(\App\Domain\Entities\Parameter::class);

        $this->params = collect(
            $parametersRepository->findBy([
                'key' => [
                    'integration_trademaster_cache_folder', 'integration_trademaster_cache_host',
                    'integration_trademaster_checkout', 'integration_trademaster_contractor',
                    'integration_trademaster_currency', 'integration_trademaster_host',
                    'integration_trademaster_key', 'integration_trademaster_legal',
                    'integration_trademaster_scheme', 'integration_trademaster_storage',
                    'integration_trademaster_struct', 'integration_trademaster_user',
                    'integration_trademaster_version',
                    'catalog_category_template', 'catalog_product_template',
                ],
            ])
        )
            ->map(function ($obj) {
                /** @var \App\Domain\Entities\Parameter $obj */
                $obj->key = str_replace('integration_trademaster_', '', $obj->key);

                return $obj;
            })
            ->pluck('value', 'key');
    }

    /**
     * Загружает категории из TradeMaster, проверяет и настраивает связи
     *
     * @return Collection|array
     * @throws \Exception
     */
    protected function catalog_get_category()
    {
        $this->logger->info('TradeMaster: get catalog list');
        \RunTracy\Helpers\Profiler\Profiler::start('tm:catalog_get_category');

        $list = collect();

        // параметры отображения категорий и товаров
        $template = [
            'category' => $this->params->get('catalog_category_template', 'catalog.category.twig'),
            'product' => $this->params->get('catalog_product_template', 'catalog.product.twig'),
        ];

        // идентификаторы категорий получивших обновление
        $noRemove = [];

        // получаем данные
        $result = $this->api(['endpoint' => 'catalog/list']);

        // полученные данные проверяем и записываем в модели категорий
        foreach ($result as $data) {
            $noRemove[] = $data['idZvena'];
            $data = [
                'external_id' => $data['idZvena'],
                'parent' => $data['idParent'],
                'title' => $data['nameZvena'],
                'order' => $data['poryadok'],
                'description' => $data['opisanie'],
                'address' => $data['link'],
                'field1' => $data['ind1'],
                'field2' => $data['ind2'],
                'field3' => $data['ind3'],
                'template' => $template,
                'children' => true,
                'meta' => [
                    'title' => $data['nameZvena'],
                    'description' => $data['opisanie'],
                ],
                'pagination' => 10,
                'export' => 'trademaster',
            ];

            $result = \App\Domain\Filters\Catalog\Category::check($data);

            if ($result === true) {
                $list[] = $model = new \App\Domain\Entities\Catalog\Category($data);
                $this->entityManager->persist($model);
            } else {
                $this->logger->info('TradeMaster: invalid category data', $result);
            }
        }

        // обрабатываем связи
        foreach ($list as $model) {
            /** @var \App\Domain\Entities\Catalog\Category $model */
            if (+$model->parent) {
                $model->set('parent', $list->firstWhere('external_id', $model->parent)->get('uuid'));
            } else {
                $model->set('parent', \Ramsey\Uuid\Uuid::fromString(\Ramsey\Uuid\Uuid::NIL));
            }
        }

        $this->entityManager->flush();

        // удаляем категории которые не получили обновления
        $query = $this->entityManager->createQueryBuilder()
            ->update(\App\Domain\Entities\Catalog\Category::class, 'c')
            ->set('c.status', '?1')
            ->where('c.external_id NOT IN (?2)')
            ->andWhere('c.export = ?3')
            ->setParameter(1, \App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE)
            ->setParameter(2, $noRemove)
            ->setParameter(3, 'trademaster');

        $query->getQuery()->execute();

        \RunTracy\Helpers\Profiler\Profiler::finish('tm:catalog_get_category');

        return $list;
    }

    /**
     * Загружает товары из TradeMaster, проверяет и настраивает связи
     *
     * @param Collection $categories
     *
     * @return Collection|array
     * @throws \Exception
     */
    protected function catalog_get_product($categories)
    {
        $this->logger->info('TradeMaster: get catalog item');
        \RunTracy\Helpers\Profiler\Profiler::start('tm:catalog_get_product');

        $list = collect();

        // идентификаторы товаров получивших обновление
        $noRemove = [];

        $result = $this->api(['endpoint' => 'item/count']);

        if ($result) {
            $count = (int)$result['count'];
            $i = 0;
            $step = 250;
            $go = true;

            // получаем данные
            while ($go) {
                $result = $this->api([
                    'endpoint' => 'item/list',
                    'params' => [
                        'sklad' => $this->params->get('storage', 0),
                        'offset' => $i * $step,
                        'limit' => $step,
                    ],
                ]);

                // полученные данные проверяем и записываем в модели товара
                foreach ($result as $data) {
                    $noRemove[] = $data['idTovar'];
                    $data = [
                        'external_id' => $data['idTovar'],
                        'category' => $data['vStrukture'],
                        'title' => $data['name'],
                        'order' => $data['poryadok'],
                        'description' => $data['opisanie'],
                        'extra' => $data['opisanieDop'],
                        'address' => $data['link'],
                        'field1' => $data['ind1'],
                        'field2' => $data['ind2'],
                        'field3' => $data['ind3'],
                        'field4' => $data['ind3'],
                        'field5' => $data['ind3'],
                        'vendorcode' => $data['artikul'],
                        'barcode' => $data['strihKod'],
                        'priceFirst' => $data['sebestomost'],
                        'price' => $data['price'],
                        'priceWholesale' => $data['opt_price'],
                        'unit' => $data['edIzmer'],
                        'volume' => $data['ves'],
                        'country' => $data['strana'],
                        'manufacturer' => $data['proizv'],
                        'tags' => $data['tags'],
                        'date' => new \DateTime($data['changeDate']),
                        'meta' => [
                            'title' => $data['name'],
                            'description' => $data['opisanie'],
                        ],
                        'stock' => $data['kolvo'],
                        'export' => 'trademaster',
                    ];

                    $result = \App\Domain\Filters\Catalog\Product::check($data);

                    if ($result === true) {
                        $list[] = $model = new \App\Domain\Entities\Catalog\Product($data);
                        $this->entityManager->persist($model);
                    } else {
                        $this->logger->info('TradeMaster: invalid product data', $result);
                    }
                }

                $go = $step * ++$i <= $count;
            }

            // обрабатываем связи
            foreach ($list as $model) {
                /** @var \App\Domain\Entities\Catalog\Product $model */
                if (+$model->category) {
                    $category = $categories->firstWhere('external_id', $model->category);

                    $model->set('category',
                        $category ?
                            $category->get('uuid') :
                            \Ramsey\Uuid\Uuid::fromString(\Ramsey\Uuid\Uuid::NIL)
                    );
                } else {
                    $model->set('category', \Ramsey\Uuid\Uuid::fromString(\Ramsey\Uuid\Uuid::NIL));
                }
            }

            $this->entityManager->flush();

            // удаляем категории которые не получили обновления
            $query = $this->entityManager->createQueryBuilder()
                ->update(\App\Domain\Entities\Catalog\Product::class, 'p')
                ->set('p.status', '?1')
                ->where('p.external_id NOT IN (?2)')
                ->andWhere('p.export = ?3')
                ->setParameter(1, \App\Domain\Types\Catalog\ProductStatusType::STATUS_DELETE)
                ->setParameter(2, $noRemove)
                ->setParameter(3, 'trademaster');

            $query->getQuery()->execute();
        };

        \RunTracy\Helpers\Profiler\Profiler::finish('tm:catalog_get_product');

        return $list;
    }

    /**
     * @throws \Exception
     */
    public function catalog_update()
    {
        \RunTracy\Helpers\Profiler\Profiler::start('tm:catalog_update');

        $this->entityManager->clear();

        $categories = $this->catalog_get_category();
        $products = $this->catalog_get_product($categories);

        \RunTracy\Helpers\Profiler\Profiler::finish('tm:catalog_update');

        return [
            'categories' => $categories,
            'products' => $products,
        ];
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function api(array $data = [])
    {
        $default = [
            'endpoint' => '',
            'params' => [],
            'method' => 'GET',
        ];
        $data = array_merge($default, $data);
        $data['method'] = strtoupper($data['method']);

        if (($key = $this->params->get('key', null)) != null) {
            $pathParts = [$this->params->get('host'), 'v' . $this->params->get('version'), $data['endpoint']];

            if ($data['method'] == "GET") {
                $data['params']['apikey'] = $key;
                $path = implode('/', $pathParts) . '?' . http_build_query($data['params']);

                $result = file_get_contents($path);
            } else {
                $path = implode('/', $pathParts) . '?' . http_build_query(['apikey' => $key]);

                $result = file_get_contents($path, false, stream_context_create([
                    'http' =>
                        [
                            'method' => 'POST',
                            'header' => 'Content-type: application/x-www-form-urlencoded',
                            'content' => http_build_query($data['params']),
                            'timeout' => 60,
                        ],
                ]));
            }

            return json_decode($result, true);
        }

        return [];
    }
}
