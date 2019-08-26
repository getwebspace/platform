<?php

namespace App\Domain\Filters\Traits;

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

            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $userRepository */
            $userRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\App\Domain\Entities\User::class);

            /** @var \App\Domain\Entities\User $user */
            $user = $userRepository->findOneBy(['email' => str_escape($data[$field])]);

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

            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $userRepository */
            $userRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\App\Domain\Entities\User::class);

            /** @var \App\Domain\Entities\User $user */
            $user = $userRepository->findOneBy(['username' => str_escape($data[$field])]);

            return $user === null || (!empty($data['uuid']) && $user->uuid === $data['uuid']);
        };
    }
}
