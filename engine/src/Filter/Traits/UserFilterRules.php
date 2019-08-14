<?php

namespace Filter\Traits;

use AEngine\Database\Db;
use Core\Auth;
use Core\Common;
use Ramsey\Uuid\Uuid;
use Slim\App;

trait UserFilterRules
{
    /**
     * Проверяет уникальность E-Mail
     *
     * @return \Closure
     */
    public function UniqueUserEmail()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Entity\User $user */
            $user = $app->getContainer()->get(\Resource\User::class)->search(['email' => str_escape($data[$field])])->first();

            return $user === null || (!empty($data['uuid']) && $user->uuid === $data['uuid']);
        };
    }

    /**
     * Проверяет уникальность E-Mail
     *
     * @return \Closure
     */
    public function UniqueUserUsername()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Entity\User $user */
            $user = $app->getContainer()->get(\Resource\User::class)->search(['username' => str_escape($data[$field])])->first();

            return $user === null || (!empty($data['uuid']) && $user->uuid === $data['uuid']);
        };
    }
}
