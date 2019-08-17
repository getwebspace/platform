<?php

namespace Domain\Filters\Traits;

use AEngine\Support\Str;
use Core\Auth;
use Core\Common;
use Ramsey\Uuid\Uuid;
use Slim\App;

trait CatalogFilterRules
{
    /**
     * Проверяет уникальность адреса публикации
     *
     * @return \Closure
     */
    public function UniqueCategoryAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $categoryRepository */
            $categoryRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\Domain\Entities\Catalog\Category::class);

            /** @var \Domain\Entities\Page $category */
            $category = $categoryRepository->findOneBy(['address' => str_escape($data[$field])]);

            return $category === null || (!empty($data['uuid']) && $category->uuid === $data['uuid']);
        };
    }

    /**
     * Проверяет уникальность адреса публикации
     *
     * @return \Closure
     */
    public function UniqueProductAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $productRepository */
            $productRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\Domain\Entities\Catalog\Product::class);

            /** @var \Domain\Entities\Page $product */
            $product = $productRepository->findOneBy(['address' => str_escape($data[$field])]);

            return $product === null || (!empty($data['uuid']) && $product->uuid === $data['uuid']);
        };
    }
}
