<?php declare(strict_types=1);

namespace App\Domain\Filters\Traits;

use Slim\App;

trait CatalogFilterRules
{
    /**
     * Вставляет адрес родительной категории
     *
     * @return \Closure
     */
    public function InsertParentCategoryAddress()
    {
        return function (&$data, $field) {
            if ($data['parent'] && $data['parent'] !== \Ramsey\Uuid\Uuid::NIL) {
                /** @var App $app */
                $app = $GLOBALS['app'];

                /** @var \Psr\Container\ContainerInterface $container */
                $container = $app->getContainer();

                if ($container->get('parameter')->get('common_auto_generate_address', 'no') === 'yes') {
                    /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $categoryRepository */
                    $categoryRepository = $container->get(\Doctrine\ORM\EntityManager::class)->getRepository(\App\Domain\Entities\Catalog\Category::class);

                    /** @var \App\Domain\Entities\Catalog\Category $category */
                    $category = $categoryRepository->findOneBy(['uuid' => str_escape($data['parent']), 'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]);

                    if ($category && !str_starts_with($category->address, $data[$field])) {
                        $data[$field] = $category->address . '/' . $data[$field];
                    }
                }
            }

            return true;
        };
    }

    /**
     * Проверяет уникальность адреса категории
     *
     * @return \Closure
     */
    public function UniqueCategoryAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $categoryRepository */
            $categoryRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\App\Domain\Entities\Catalog\Category::class);

            /** @var \App\Domain\Entities\Catalog\Category $category */
            $category = $categoryRepository->findOneBy(['address' => str_escape($data[$field]), 'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]);

            return $category === null || (!empty($data['uuid']) && $category->uuid === $data['uuid']) || (!empty($data['external_id']) && $category->external_id === $data['external_id']);
        };
    }

    /**
     * Проверяет поле template у категории
     *
     * @return \Closure
     */
    public function ValidTemplate()
    {
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
     * Вставляет адрес родительной категории
     *
     * @return \Closure
     */
    public function InsertParentProductAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Psr\Container\ContainerInterface $container */
            $container = $app->getContainer();

            if ($container->get('parameter')->get('common_auto_generate_address', 'no') === 'yes') {
                /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $categoryRepository */
                $categoryRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\App\Domain\Entities\Catalog\Category::class);

                /** @var \App\Domain\Entities\Catalog\Category $category */
                $category = $categoryRepository->findOneBy(['uuid' => str_escape($data['category']), 'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]);

                if ($category && !str_starts_with($category->address, $data[$field])) {
                    $data[$field] = $category->address . '/' . $data[$field];
                }
            }

            return true;
        };
    }

    /**
     * Проверяет уникальность адреса продукта
     *
     * @return \Closure
     */
    public function UniqueProductAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $productRepository */
            $productRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\App\Domain\Entities\Catalog\Product::class);

            /** @var \App\Domain\Entities\Catalog\Product $product */
            $product = $productRepository->findOneBy(['address' => str_escape($data[$field]), 'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]);

            return $product === null || (!empty($data['uuid']) && $product->uuid === $data['uuid']) || (!empty($data['external_id']) && $product->external_id === $data['external_id']);
        };
    }

    /**
     * Проверяет строку тегов
     *
     * @return \Closure
     */
    public function ValidTags()
    {
        return function (&$data, $field) {
            $value = &$data[$field];

            if ($value && !is_array($value)) {
                $value = explode(';', (string) $value);
            }

            return true;
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
                if (isset($_ENV['SIMPLE_ORDER_SERIAL']) && $_ENV['SIMPLE_ORDER_SERIAL']) {
                    /** @var App $app */
                    $app = $GLOBALS['app'];

                    /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $categoryRepository */
                    $categoryRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\App\Domain\Entities\Catalog\Order::class);

                    /** @var \App\Domain\Entities\Catalog\Order $order */
                    $order = $categoryRepository->findOneBy([], ['date' => 'desc']) ?? null;

                    $value = $order ? (+$order->serial) + 1 : 1;
                } else {
                    $value = mb_strtoupper(mb_substr(bin2hex(random_bytes(10 + $length)), 0, $length));
                }
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
            if (empty($data['delivery']['client']) && empty($data['user_uuid'])) {
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
                if (!\Ramsey\Uuid\Uuid::isValid($uuid) || gettype((int) $count) !== 'integer' || !$count || $count <= 0) {
                    unset($value[$uuid]);
                }
            }

            return true;
        };
    }
}
