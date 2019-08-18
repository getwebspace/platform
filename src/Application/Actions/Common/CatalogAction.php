<?php

namespace Application\Actions\Common;

use AEngine\Entity\Collection;
use Application\Actions\Action;
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

        $this->categoryRepository = $this->entityManager->getRepository(\Domain\Entities\Catalog\Category::class);
        $this->productRepository = $this->entityManager->getRepository(\Domain\Entities\Catalog\Product::class);
        $this->fileRepository = $this->entityManager->getRepository(\Domain\Entities\File::class);
    }

    /**
     * @return Response
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Domain\Exceptions\HttpBadRequestException
     */
    protected function action(): \Slim\Http\Response
    {
        $params = $this->parsePath();
        //$item = $this->getItem(...array_values($params));

        /**
         * @var Collection[\Domain\Entities\Catalog\Category] $categories
         * @var \Domain\Entities\Catalog\Category $category
         */
        $categories = collect($this->categoryRepository->findAll());
        $category = $categories->firstWhere('address', $params['address']);

        // Category
        if (is_null($category) === false) {
            /** @var \Domain\Entities\Catalog\Product[] $products */
            $products = collect(
                $this
                    ->productRepository
                    ->findBy(['category' => $category->uuid], null, $category->pagination, $params['offset'])
            );

            return $this->respondRender($category->template['category'], [
                'categories' => $categories,
                'category' => $category,
                'products' => $products,
            ]);
        }

        // product
        /** @var \Domain\Entities\Catalog\Product $product */
        $product = $this->productRepository->findOneBy(['address' => $params['address']]);

        if (is_null($product) === false) {
            $category = $categories->firstWhere('uuid', $product->category);

            return $this->respondRender($category->template['product'], [
                'categories' => $categories,
                'category' => $category,
                'product' => $product,
            ]);
        }

        // 404
        return $this->respondRender('p404.twig')->withStatus(404);
    }

    /**
     * @return array
     */
    protected function parsePath()
    {
        $parts = explode('/', str_replace('/catalog', '', $this->request->getUri()->getPath()));

        $index = 1;
        $offset = 0;

        if (($buf = $parts[count($parts) - $index]) && ctype_digit($buf)) {
            $offset = +$buf;
            $index++;
        }

        $address = $parts[count($parts) - $index];

        return ['address' => $address, 'offset' => $offset];
    }
}
