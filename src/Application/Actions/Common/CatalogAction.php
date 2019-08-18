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
        $item = $this->getItem(...array_values($params));

        /** @var Collection[\Domain\Entities\Catalog\Category] $categories */
        $categories = collect(
            $this
                ->categoryRepository
                ->findAll()
        );

        switch (true) {
            case is_a($item, \Domain\Entities\Catalog\Category::class):
                /**
                 * @var \Domain\Entities\Catalog\Category  $item
                 * @var \Domain\Entities\Catalog\Product[] $products
                 */
                $products = collect(
                    $this
                        ->productRepository
                        ->findBy(['category' => $item->uuid], null, $item->pagination, $params['offset'])
                );

                return $this->respondRender($item->template['category'], [
                    'categories' => $categories,
                    'category' => $item,
                    'products' => $products,
                ]);

            case is_a($item, \Domain\Entities\Catalog\Product::class):
                /**
                 * @var \Domain\Entities\Catalog\Product  $item
                 * @var \Domain\Entities\Catalog\Category $category
                 */
                $category = $categories->firstWhere('uuid', $item->category);

                return $this->respondRender($category->template['product'], [
                    'categories' => $categories,
                    'category' => $category,
                    'product' => $item,
                ]);
        }

        return $this->respondRender('p404.twig')->withStatus(404);
    }

    /**
     * @return false|object|null
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getItem($address, $offset = 0)
    {
        $query = $this
            ->entityManager
            ->getConnection()
            ->query("
                 (
                    SELECT 'category' as `type`, `uuid`
                    FROM `catalog_category`
                    WHERE `address` = '" . str_escape($address) . "'
                    LIMIT 1
                    OFFSET " . (int)$offset . "
                )
                UNION
                (
                    SELECT 'product' as `type`, `uuid`
                    FROM `catalog_product`
                    WHERE `address` = '" . str_escape($address) . "'
                    LIMIT 1
                    OFFSET " . (int)$offset . "
                )
            ");

        if ($query->execute()) {
            $result = $query->fetch();

            switch ($result['type']) {
                case 'category':
                    return $this->categoryRepository->findOneBy(['uuid' => $result['uuid']]);
                case 'product':
                    return $this->productRepository->findOneBy(['uuid' => $result['uuid']]);
            }
        }

        return false;
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
