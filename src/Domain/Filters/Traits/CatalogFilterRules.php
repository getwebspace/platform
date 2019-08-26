<?php

namespace Domain\Filters\Traits;

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
            $category = $categoryRepository->findOneBy(['address' => str_escape($data[$field]), 'status' => \Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]);

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
            $product = $productRepository->findOneBy(['address' => str_escape($data[$field]), 'status' => \Domain\Types\Catalog\ProductStatusType::STATUS_WORK]);

            return $product === null || (!empty($data['uuid']) && $product->uuid === $data['uuid']);
        };
    }

    /**
     * Генерирует строку криптографически случайных байт произвольной длины
     *
     * @param int $length
     *
     * @return \Closure
     */
    public function UniqueSerialID($length = 7)
    {
        return function (&$data, $field) use ($length) {
            $value = &$data[$field];

            if (!$value) {
                $value = strtoupper(substr(bin2hex(random_bytes(10)), 0, $length));
            }

            return true;
        };
    }

    /**
     * Проверяет наличие имени или UUID пользователя
     *
     * @return \Closure
     */
    public function CheckClient()
    {
        return function (&$data, $field) {
            if(empty($data['delivery']['client']) && empty($data['user_uuid'])) {
                return false;
            }

            return true;
        };
    }

    /**
     * Проверяет поле product у категории
     *
     * @return \Closure
     */
    public function ValidOrderDelivery()
    {
        return function (&$data, $field) {
            $buf = [
                'client' => '',
                'address' => '',
            ];
            $value = &$data[$field];

            if (!is_array($value)) {
                $value = $buf;

                return true;
            }

            if (isset($value['client'])) {
                $buf['client'] = $value['client'];
            }
            if (isset($value['address'])) {
                $buf['address'] = $value['address'];
            }

            $value = $buf;

            return true;
        };
    }

    /**
     * Проверяет UUID товаров заказа
     *
     * @return \Closure
     */
    public function ValidOrderList()
    {
        return function (&$data, $field) {
            $value = &$data[$field];

            if (!is_array($value)) {
                $value = [];

                return true;
            }

            foreach ($value as $uuid => $count) {
                if (!\Ramsey\Uuid\Uuid::isValid($uuid)) {
                    return false;
                }
                if (!ctype_digit($count) || $count <= 0) {
                    unset($value[$uuid]);
                }
            }

            return true;
        };
    }
}
