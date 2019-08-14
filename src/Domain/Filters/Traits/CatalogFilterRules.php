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

            /** @var \Entity\Catalog\Category $category */
            $category = $app->getContainer()->get(\Resource\Catalog\Category::class)->search(['address' => str_escape($data[$field])])->first();

            return $category === null || (!empty($data['uuid']) && $category->uuid === $data['uuid']);
        };
    }
}
