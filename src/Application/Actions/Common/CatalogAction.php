<?php

namespace App\Application\Actions\Common;

use AEngine\Entity\Collection;
use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class CatalogAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $categoryRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $productRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $fileRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);
        $this->productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);
        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);
    }

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
        if ($params['address']['category'] == '' && $params['address']['product'] == '') {
            $pagination = $this->getParameter('catalog_category_pagination');
            $products = collect(
                $this->productRepository->findBy(
                    [
                        'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
                    ],
                    null,
                    $pagination,
                    $params['offset'] * $pagination
                )
            );
            $productsCount = $this->productRepository->count([
                'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
            ]);
            $files = $files->merge(
                $this->fileRepository->findBy([
                    'item' => \App\Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT,
                    'item_uuid' => array_map('strval', $products->pluck('uuid')->all()),
                ])
            );

            return $this->respondRender($this->getParameter('catalog_category_template'), [
                'categories' => $categories,
                'products' => $products,
                'pagination' => [
                    'count' => $productsCount,
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
        $category = $categories->firstWhere('address', $params['address']['category']);

        if (is_null($category) === false) {
            $categoryUUIDs = $this->getCategoryChildrenUUID($categories, $category);
            $products = collect(
                $this
                    ->productRepository
                    ->findBy(
                        [
                            'category' => $categoryUUIDs,
                            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
                        ],
                        null,
                        $category->pagination,
                        $params['offset'] * $category->pagination
                    )
            );
            $productsCount = $this->productRepository->count([
                'category' => $categoryUUIDs,
                'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
            ]);
            $files = $files->merge(
                $this->fileRepository->findBy([
                    'item' => \App\Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT,
                    'item_uuid' => array_map('strval', $products->pluck('uuid')->all()),
                ])
            );

            return $this->respondRender($category->template['category'], [
                'categories' => $categories,
                'category' => $category,
                'products' => $products,
                'pagination' => [
                    'count' => $productsCount,
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
            'address' => $params['address']['product'],
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
        $parts = explode('/', ltrim(str_replace('/catalog', '', $this->request->getUri()->getPath()), '/'));
        $offset = 0;

        if (($buf = $parts[count($parts) - 1]) && ctype_digit($buf)) {
            $offset = +$buf;
            unset($parts[count($parts) - 1]);
        }

        $product = count($parts) ? $parts[count($parts) - 1] : '';
        $category = implode('/', $parts);

        return ['address' => ['category' => $category, 'product' => $product], 'offset' => $offset];
    }

    /**
     * @param \AEngine\Entity\Collection                 $categories
     * @param \App\Domain\Entities\Catalog\Category|null $curCategory
     *
     * @return array
     */
    protected function getCategoryChildrenUUID(\AEngine\Entity\Collection $categories, \App\Domain\Entities\Catalog\Category $curCategory = null)
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