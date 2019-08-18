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
     * Проверяет поле template у категории
     *
     * @return \Closure
     */
    public function ValidTemplate() {
        return function (&$data, $field) {
            $buf = [
                'category' => '',
                'product' => '',
            ];
            $value = &$data[$field];

            if (!is_array($value)) {
                $value = $buf;

                return true;
            }

            if (isset($value['category'])) {
                $buf['category'] = $value['category'];
            }
            if (isset($value['product'])) {
                $buf['product'] = $value['product'];
            }

            $value = $buf;

            return true;
        };
    }

    /**
     * Проверяет поле product у категории
     *
     * @return \Closure
     */
    public function ValidProductFieldNames()
    {
        return function (&$data, $field) {
            $buf = [
                'field_1' => '',
                'field_2' => '',
                'field_3' => '',
                'field_4' => '',
                'field_5' => '',
            ];
            $value = &$data[$field];

            if (!is_array($value)) {
                $value = $buf;

                return true;
            }

            if (isset($value['field_1'])) {
                $buf['field_1'] = $value['field_1'];
            }
            if (isset($value['field_2'])) {
                $buf['field_2'] = $value['field_2'];
            }
            if (isset($value['field_3'])) {
                $buf['field_3'] = $value['field_3'];
            }
            if (isset($value['field_4'])) {
                $buf['field_4'] = $value['field_4'];
            }
            if (isset($value['field_5'])) {
                $buf['field_5'] = $value['field_5'];
            }

            $value = $buf;

            return true;
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
