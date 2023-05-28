<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use Illuminate\Support\Collection;
use Slim\Psr7\Response;

class ListAction extends CatalogAction
{
    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function action(): \Slim\Psr7\Response
    {
        $params = $this->parsePath();
        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            'hidden' => false,
        ]);

        // Catalog main
        if ($buf = $this->prepareMain($params, $categories)) {
            return $buf;
        }

        // Category
        try {
            if ($buf = $this->prepareCategory($params, $categories)) {
                return $buf;
            }
        } catch (CategoryNotFoundException $e) {
            // 404
            return $this->respond('p404.twig')->withStatus(404);
        }

        // Product
        try {
            if ($buf = $this->prepareProduct($params, $categories)) {
                return $buf;
            }
        } catch (ProductNotFoundException $e) {
            // 404
            return $this->respond('p404.twig')->withStatus(404);
        }

        // 404
        return $this->respond('p404.twig')->withStatus(404);
    }

    /**
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function prepareMain(array $params, ?Collection $categories): ?Response
    {
        if ($params['address'] === '') {
            $pagination = $this->parameter('catalog_category_pagination', 10);

            $qb = $this->entityManager->createQueryBuilder();
            $query = $qb
                ->from(\App\Domain\Entities\Catalog\Product::class, 'p')
                ->where('p.status = :status')
                ->setParameter('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK, \App\Domain\Types\Catalog\ProductStatusType::NAME);

            $products = collect($query->select('p')->getQuery()->getResult());

            for ($i = 1; $i <= 5; ++$i) {
                if (($field = $this->getParam('field' . $i, false)) !== false) {
                    $params['field'][$i] = $field;
                    $query
                        ->andWhere('p.field' . $i . ' = :field' . $i . '')
                        ->setParameter('field' . $i, str_escape($field), \Doctrine\DBAL\ParameterType::STRING);
                }
            }
            $attributes = [];
            foreach ($this->getParams() as $key => $value) {
                if (
                    (
                        !in_array($key, ['price', 'country', 'manufacturer', 'order', 'direction'], true)
                        && !str_starts_with($key, 'field')
                        && $key !== 'format' // kostil
                    ) && $value
                ) {
                    $attributes[$key] = $value;
                }
            }
            if ($attributes) {
                $buf = ['address' => [], 'value' => []];
                foreach ($attributes as $key => $value) {
                    $buf['address'][] = $key;

                    foreach ((array) $value as $val) {
                        $buf['value'][] = $val;
                    }
                }

                $query
                    ->join('p.attributes', 'ap')
                    ->join('ap.attribute', 'a')
                    ->andWhere('a.address IN (:address)')
                    ->andWhere('ap.value IN (:value)')
                    ->setParameter('address', $buf['address'])
                    ->setParameter('value', $buf['value'])
                    ->groupBy('p.uuid')
                    ->having('(count(distinct a.address) = ' . count($attributes) . ')');

                $params['attributes'] = $attributes;
            }
            if (($price = $this->getParam('price', false)) !== false) {
                $price = array_merge(['min' => 0, 'max' => 0], (array) $price);

                if ($price['min']) {
                    $params['price']['min'] = (float) $price['min'];
                    $query
                        ->andWhere('p.price >= :minPrice')
                        ->setParameter('minPrice', $params['price']['min'], \Doctrine\DBAL\ParameterType::INTEGER);
                }
                if ($price['max']) {
                    $params['price']['max'] = (float) $price['max'];
                    $query
                        ->andWhere('p.price <= :maxPrice')
                        ->setParameter('maxPrice', $params['price']['max'], \Doctrine\DBAL\ParameterType::INTEGER);
                }
            }
            if (($country = $this->getParam('country', false)) !== false) {
                $query
                    ->andWhere('p.country = :country')
                    ->setParameter('country', str_escape($country), \Doctrine\DBAL\ParameterType::STRING);
            }
            if (($manufacturer = $this->getParam('manufacturer', false)) !== false) {
                $query
                    ->andWhere('p.manufacturer = :manufacturer')
                    ->setParameter('manufacturer', str_escape($manufacturer), \Doctrine\DBAL\ParameterType::STRING);
            }
            if (($order = $this->getParam('order', false)) !== false) {
                $direction = mb_strtolower($this->getParam('direction', 'asc'));
                $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'ASC';

                if (in_array($order, ['title', 'price', 'field1', 'field2', 'field3', 'field4', 'field5'], true)) {
                    $query->orderBy('p.' . $order, $direction);
                    $params['order'][$order] = $direction;
                }
            } else {
                $sortBy = [
                    'by' => $this->parameter('catalog_sort_by', 'title'),
                    'direction' => $this->parameter('catalog_sort_direction', 'ASC'),
                ];

                $query->orderBy('p.' . $sortBy['by'], $sortBy['direction']);
                $params['order'][$sortBy['by']] = mb_strtolower($sortBy['direction']);
            }

            $filtered = collect(
                $query
                    ->select('p')
                    ->addOrderBy('p.order', 'ASC')
                    ->setMaxResults($pagination)
                    ->setFirstResult($params['offset'] * $pagination)
                    ->getQuery()
                    ->getResult()
            );

            $count = +$this->entityManager->createQueryBuilder()
                ->select('COUNT(x)')
                ->from(\App\Domain\Entities\Catalog\Product::class, 'x')
                ->where($qb->expr()->in('x.uuid', $query->getDQL()))
                ->setParameters($query->getParameters())
                ->getQuery()->getSingleScalarResult();

            return $this->respond($this->parameter('catalog_category_template', 'catalog.category.twig'), [
                'categories' => $categories,
                'products' => [
                    'all' => $products,
                    'filtered' => $filtered,
                    'count' => $count,
                    'params' => $params,
                ],
                'pagination' => [
                    'count' => $count,
                    'page' => $pagination,
                    'offset' => $params['offset'],
                ],
            ]);
        }

        return null;
    }

    /**
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function prepareCategory(array $params, ?Collection $categories): ?Response
    {
        /**
         * @var \App\Domain\Entities\Catalog\Category $category
         */
        $category = $categories->firstWhere('address', $params['address']);

        if ($category) {
            $categoryUUIDs = $category->getNested($categories)->pluck('uuid')->all();

            $qb = $this->entityManager->createQueryBuilder();
            $query = $qb
                ->from(\App\Domain\Entities\Catalog\Product::class, 'p')
                ->where('p.status = :status')
                ->andWhere('p.category IN (:category)')
                ->setParameter('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK, \App\Domain\Types\Catalog\ProductStatusType::NAME)
                ->setParameter('category', $categoryUUIDs);

            $products = collect($query->select('p')->getQuery()->getResult());

            for ($i = 1; $i <= 5; ++$i) {
                if (($field = $this->getParam('field' . $i, false)) !== false) {
                    $params['field'][$i] = $field;
                    $query
                        ->andWhere('p.field' . $i . ' = :field' . $i . '')
                        ->setParameter('field' . $i, str_escape($field), \Doctrine\DBAL\ParameterType::STRING);
                }
            }
            $attributes = [];
            foreach ($this->getParams() as $key => $value) {
                if (
                    (
                        !in_array($key, ['price', 'country', 'manufacturer', 'order', 'direction'], true)
                        && !str_starts_with($key, 'field')
                        && $key !== 'format' // kostil
                    ) && $value
                ) {
                    $attributes[$key] = $value;
                }
            }
            if ($attributes) {
                $buf = ['address' => [], 'value' => []];
                foreach ($attributes as $key => $value) {
                    $buf['address'][] = $key;

                    foreach ((array) $value as $val) {
                        $buf['value'][] = $val;
                    }
                }

                $query
                    ->join('p.attributes', 'ap')
                    ->join('ap.attribute', 'a')
                    ->andWhere('a.address IN (:address)')
                    ->andWhere('ap.value IN (:value)')
                    ->setParameter('address', $buf['address'])
                    ->setParameter('value', $buf['value'])
                    ->groupBy('p.uuid')
                    ->having('(count(distinct a.address) = ' . count($attributes) . ')');

                $params['attributes'] = $attributes;
            }
            if (($price = $this->getParam('price', false)) !== false) {
                $price = array_merge(['min' => 0, 'max' => 0], (array) $price);

                if ($price['min']) {
                    $params['price']['min'] = (float) $price['min'];
                    $query
                        ->andWhere('p.price >= :minPrice')
                        ->setParameter('minPrice', $params['price']['min'], \Doctrine\DBAL\ParameterType::INTEGER);
                }
                if ($price['max']) {
                    $params['price']['max'] = (float) $price['max'];
                    $query
                        ->andWhere('p.price <= :maxPrice')
                        ->setParameter('maxPrice', $params['price']['max'], \Doctrine\DBAL\ParameterType::INTEGER);
                }
            }
            if (($country = $this->getParam('country', false)) !== false) {
                $query
                    ->andWhere('p.country = :country')
                    ->setParameter('country', str_escape($country), \Doctrine\DBAL\ParameterType::STRING);
            }
            if (($manufacturer = $this->getParam('manufacturer', false)) !== false) {
                $query
                    ->andWhere('p.manufacturer = :manufacturer')
                    ->setParameter('manufacturer', str_escape($manufacturer), \Doctrine\DBAL\ParameterType::STRING);
            }
            if (($order = $this->getParam('order', false)) !== false) {
                $direction = mb_strtolower($this->getParam('direction', 'asc'));
                $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'ASC';

                if (in_array($order, ['title', 'price', 'field1', 'field2', 'field3', 'field4', 'field5'], true)) {
                    $query->orderBy('p.' . $order, $direction);
                    $params['order'][$order] = $direction;
                }
            } else {
                $sortBy = $category->getSort();

                $query->orderBy('p.' . $sortBy['by'], $sortBy['direction']);
                $params['order'][$sortBy['by']] = mb_strtolower($sortBy['direction']);
            }

            $filtered = collect(
                $query
                    ->select('p')
                    ->addOrderBy('p.order', 'ASC')
                    ->setMaxResults($category->getPagination())
                    ->setFirstResult($params['offset'] * $category->getPagination())
                    ->getQuery()
                    ->getResult()
            );

            $count = +$this->entityManager->createQueryBuilder()
                ->select('COUNT(x)')
                ->from(\App\Domain\Entities\Catalog\Product::class, 'x')
                ->where($qb->expr()->in('x.uuid', $query->getDQL()))
                ->setParameters($query->getParameters())
                ->getQuery()->getSingleScalarResult();

            return $this->respond($category->getTemplate()['category'], [
                'categories' => $categories,
                'category' => $category,
                'products' => [
                    'all' => $products,
                    'filtered' => $filtered,
                    'count' => $count,
                    'params' => $params,
                ],
                'pagination' => [
                    'count' => $count,
                    'page' => $category->getPagination(),
                    'offset' => $params['offset'],
                ],
            ]);
        }

        return null;
    }

    /**
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function prepareProduct(array $params, ?Collection $categories): ?Response
    {
        $products = $this->catalogProductService->read([
            'address' => [$params['address']],
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
        ]);

        if ($products->count()) {
            /**
             * @var \App\Domain\Entities\Catalog\Product $product
             */
            $product = $products->first();
            $category = $categories->firstWhere('uuid', $product->getCategory());

            return $this->respond($category->template['product'] ?? '', [
                'categories' => $categories,
                'category' => $category,
                'product' => $product,
                'products' => $products,
                'params' => $params,
            ]);
        }

        return null;
    }

    protected function parsePath(): array
    {
        $pathCatalog = $this->parameter('catalog_address', 'catalog');
        $parts = explode('/', ltrim(str_replace("/{$pathCatalog}", '', $this->request->getUri()->getPath()), '/'));
        $offset = 0;

        if (($buf = $parts[count($parts) - 1]) && ctype_digit($buf)) {
            $offset = +$buf;
            unset($parts[count($parts) - 1]);
        }

        return ['address' => implode('/', $parts), 'offset' => $offset];
    }
}
