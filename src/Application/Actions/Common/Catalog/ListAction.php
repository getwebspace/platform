<?php

namespace App\Application\Actions\Common\Catalog;

use Alksily\Entity\Collection;
use Slim\Http\Response;

class ListAction extends CatalogAction
{
    /**
     * @return Response
     * @throws \Doctrine\DBAL\DBALException
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function action(): \Slim\Http\Response
    {
        $params = $this->parsePath();
        $categories = collect($this->categoryRepository->findBy([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]));
        $files = collect(
            $this->fileRepository->findBy([
                'item' => \App\Domain\Types\FileItemType::ITEM_CATALOG_CATEGORY,
                'item_uuid' => array_map('strval', $categories->pluck('uuid')->all()),
            ])
        );

        // Catalog main
        if ($buf = $this->prepareMain($params, $categories, $files)) {
            return $buf;
        }

        // Category
        if ($buf = $this->prepareCategory($params, $categories, $files)) {
            return $buf;
        }

        // Product
        if ($buf = $this->prepareProduct($params, $categories, $files)) {
            return $buf;
        }

        // 404
        return $this->respondRender('p404.twig')->withStatus(404);
    }

    /**
     * @param array      $params
     * @param Collection $categories
     * @param Collection $files
     *
     * @return Response
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function prepareMain(array &$params, &$categories, &$files)
    {
        if ($params['address'] == '') {
            $pagination = $this->getParameter('catalog_category_pagination', 10);

            $qb = $this->entityManager->createQueryBuilder();
            $query = $qb
                ->from(\App\Domain\Entities\Catalog\Product::class, 'p')
                ->where('p.status = :status')
                ->orderBy('p.order', 'ASC')
                ->setParameter('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK, \App\Domain\Types\Catalog\ProductStatusType::NAME);

            $products = collect($query->select('p')->getQuery()->getResult());

            for($i = 1; $i <= 5; $i++) {
                if (($field = $this->request->getParam('field' . $i, false)) !== false) {
                    $params['field'][$i] = $field;
                    $query
                        ->andWhere('p.field'.$i.' = :field'.$i.'')
                        ->setParameter('field'.$i, str_escape($field), \Doctrine\DBAL\Types\Type::STRING);
                }
            }
            if (($price = $this->request->getParam('price', false)) !== false) {
                $price = array_merge(['min' => 0, 'max' => 0], (array)$price);

                if ($price['min']) {
                    $params['price']['min'] = $price['min'];
                    $query
                        ->andWhere('p.price >= :minPrice')
                        ->setParameter('minPrice', (int)$price['min'], \Doctrine\DBAL\Types\Type::INTEGER);
                }
                if ($price['max']) {
                    $params['price']['max'] = $price['max'];
                    $query
                        ->andWhere('p.price <= :maxPrice')
                        ->setParameter('maxPrice', (int)$price['max'], \Doctrine\DBAL\Types\Type::INTEGER);
                }
            }
            if (($order = $this->request->getParam('order', false)) !== false) {
                $direction = $this->request->getParam('direction', 'asc');

                if (in_array($order, ['title', 'price', 'field1', 'field2', 'field3', 'field4', 'field5'])) {
                    $query->addOrderBy('p.' . $order, in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'ASC');
                }
            } else {
                $query->addOrderBy('p.title', 'ASC');
            }

            $filtered = collect(
                $query
                    ->select('p')
                    ->setMaxResults($pagination)
                    ->setFirstResult($params['offset'] * $pagination)
                    ->getQuery()
                    ->getResult()
            );
            $count = $query->select('count(p)')->setMaxResults(null)->setFirstResult(null)->getQuery()->getSingleScalarResult();

            $files = $files->merge(
                $this->fileRepository->findBy([
                    'item' => \App\Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT,
                    'item_uuid' => array_map('strval', $filtered->pluck('uuid')->all()),
                ])
            );

            return $this->respondRender($this->getParameter('catalog_category_template', 'catalog.category.twig'), [
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
                ],
                'files' => $files,
            ]);
        }

        return null;
    }

    /**
     * @param array      $params
     * @param Collection $categories
     * @param Collection $files
     *
     * @return Response
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function prepareCategory(array &$params, &$categories, &$files)
    {
        /**
         * @var \App\Domain\Entities\Catalog\Category $category
         */
        $category = $categories->firstWhere('address', $params['address']);

        if (is_null($category) === false) {
            $categoryUUIDs = $this->getCategoryChildrenUUID($categories, $category);

            $qb = $this->entityManager->createQueryBuilder();
            $query = $qb
                ->from(\App\Domain\Entities\Catalog\Product::class, 'p')
                ->where('p.status = :status')
                ->andWhere('p.category IN (:category)')
                ->orderBy('p.order', 'ASC')
                ->setParameter('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK, \App\Domain\Types\Catalog\ProductStatusType::NAME)
                ->setParameter('category', $categoryUUIDs);

            $products = collect($query->select('p')->getQuery()->getResult());

            for($i = 1; $i <= 5; $i++) {
                if (($field = $this->request->getParam('field' . $i, false)) !== false) {
                    $params['field'][$i] = $field;
                    $query
                        ->andWhere('p.field'.$i.' = :field'.$i.'')
                        ->setParameter('field'.$i, str_escape($field), \Doctrine\DBAL\Types\Type::STRING);
                }
            }
            if (($price = $this->request->getParam('price', false)) !== false) {
                $price = array_merge(['min' => 0, 'max' => 0], (array)$price);

                if ($price['min']) {
                    $params['price']['min'] = $price['min'];
                    $query
                        ->andWhere('p.price >= :minPrice')
                        ->setParameter('minPrice', (int)$price['min'], \Doctrine\DBAL\Types\Type::INTEGER);
                }
                if ($price['max']) {
                    $params['price']['max'] = $price['max'];
                    $query
                        ->andWhere('p.price <= :maxPrice')
                        ->setParameter('maxPrice', (int)$price['max'], \Doctrine\DBAL\Types\Type::INTEGER);
                }
            }
            if (($order = $this->request->getParam('order', false)) !== false) {
                $direction = $this->request->getParam('direction', 'asc');

                if (in_array($order, ['title', 'price', 'field1', 'field2', 'field3', 'field4', 'field5'])) {
                    $query->addOrderBy('p.' . $order, in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'ASC');
                }
            } else {
                $query->addOrderBy('p.title', 'ASC');
            }

            $filtered = collect(
                $query
                    ->select('p')
                    ->setMaxResults($category->pagination)
                    ->setFirstResult($params['offset'] * $category->pagination)
                    ->getQuery()
                    ->getResult()
            );
            $count = $query->select('count(p)')->setMaxResults(null)->setFirstResult(null)->getQuery()->getSingleScalarResult();

            $files = $files->merge(
                $this->fileRepository->findBy([
                    'item' => \App\Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT,
                    'item_uuid' => array_map('strval', $filtered->pluck('uuid')->all()),
                ])
            );

            return $this->respondRender($category->template['category'], [
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
                    'page' => $category->pagination,
                ],
                'files' => $files,
            ]);
        }

        return null;
    }

    /**
     * @param array      $params
     * @param Collection $categories
     * @param Collection $files
     *
     * @return Response
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function prepareProduct(array &$params, &$categories, &$files)
    {
        /** @var \App\Domain\Entities\Catalog\Product $product */
        $product = $this->productRepository->findOneBy([
            'address' => $params['address'],
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
        ]);

        if (is_null($product) === false) {
            $category = $categories->firstWhere('uuid', $product->category);
            $files = $files->merge(
                $this->fileRepository->findBy([
                    'item' => \App\Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT,
                    'item_uuid' => $product->uuid,
                ])
            );

            return $this->respondRender($category->template['product'], [
                'categories' => $categories,
                'category' => $category,
                'product' => $product,
                'params' => $params,
                'files' => $files,
            ]);
        }

        return null;
    }

    /**
     * @return array
     */
    protected function parsePath()
    {
        $pathCatalog = $this->getParameter('catalog_address', 'catalog');
        $parts = explode('/', ltrim(str_replace("/{$pathCatalog}", '', $this->request->getUri()->getPath()), '/'));
        $offset = 0;

        if (($buf = $parts[count($parts) - 1]) && ctype_digit($buf)) {
            $offset = +$buf;
            unset($parts[count($parts) - 1]);
        }

        return ['address' => implode('/', $parts), 'offset' => $offset];
    }

    /**
     * @param \Alksily\Entity\Collection                 $categories
     * @param \App\Domain\Entities\Catalog\Category|null $curCategory
     *
     * @return array
     */
    protected function getCategoryChildrenUUID(\Alksily\Entity\Collection $categories, \App\Domain\Entities\Catalog\Category $curCategory = null)
    {
        $result = [$curCategory->uuid->toString()];

        if ($curCategory->children) {
            /** @var \App\Domain\Entities\Catalog\Category $category */
            foreach ($categories->where('parent', $curCategory->uuid) as $childCategory) {
                $result = array_merge($result, $this->getCategoryChildrenUUID($categories, $childCategory));
            }
        }

        return $result;
    }
}
