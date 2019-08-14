<?php

namespace Filter\Traits;

use AEngine\Support\Str;
use Core\Auth;
use Core\Common;
use Ramsey\Uuid\Uuid;
use Slim\App;

trait PageFilterRules
{
    /**
     * Проверяет уникальность адреса публикации
     *
     * @return \Closure
     */
    public function UniquePageAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Entity\Publication $publication */
            $publication = $app->getContainer()->get(\Resource\Publication::class)->search(['address' => str_escape($data[$field])])->first();

            return $publication === null || (!empty($data['uuid']) && $publication->uuid === $data['uuid']);
        };
    }
}
